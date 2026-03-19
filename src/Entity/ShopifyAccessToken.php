<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shopify_access_token')]
#[ORM\UniqueConstraint(name: 'shop_access_mode', columns: ['shop', 'access_mode'])]
#[ORM\Index(name: 'idx_shopify_access_token_shop', columns: ['shop'])]
class ShopifyAccessToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $shop;

    #[ORM\Column(name: 'access_mode', type: Types::STRING, length: 16)]
    private string $accessMode;

    #[ORM\Column(type: Types::TEXT)]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $scope = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $expires = null;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $refreshTokenExpires = null;

    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userId = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $user = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'webhooks_config_hash', type: Types::STRING, length: 64, nullable: true)]
    private ?string $webhooksConfigHash = null;

    #[ORM\Column(name: 'webhooks_registered_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $webhooksRegisteredAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getShop(): string
    {
        return $this->shop;
    }

    public function setShop(string $shop): self
    {
        $this->shop = $shop;
        return $this;
    }

    public function getAccessMode(): string
    {
        return $this->accessMode;
    }

    public function setAccessMode(string $accessMode): self
    {
        $this->accessMode = $accessMode;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getExpires(): ?string
    {
        return $this->expires;
    }

    public function setExpires(?string $expires): self
    {
        $this->expires = $expires;
        return $this;
    }

    public function getRefreshTokenExpires(): ?string
    {
        return $this->refreshTokenExpires;
    }

    public function setRefreshTokenExpires(?string $refreshTokenExpires): self
    {
        $this->refreshTokenExpires = $refreshTokenExpires;
        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function setUser(?array $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getWebhooksConfigHash(): ?string
    {
        return $this->webhooksConfigHash;
    }

    public function setWebhooksConfigHash(?string $webhooksConfigHash): self
    {
        $this->webhooksConfigHash = $webhooksConfigHash;
        return $this;
    }

    public function getWebhooksRegisteredAt(): ?\DateTimeImmutable
    {
        return $this->webhooksRegisteredAt;
    }

    public function setWebhooksRegisteredAt(?\DateTimeImmutable $webhooksRegisteredAt): self
    {
        $this->webhooksRegisteredAt = $webhooksRegisteredAt;
        return $this;
    }

    /**
     * Convert entity to the array shape expected by shopify-app-php (get/save/refresh).
     *
     * @return array{shop: string, accessMode: string, token: string, scope: string, refreshToken: string, expires: ?string, refreshTokenExpires: ?string, userId: ?string, user: ?array, webhooks_config_hash?: ?string, webhooks_registered_at?: ?string}
     */
    public function toTokenArray(): array
    {
        $arr = [
            'shop' => $this->shop,
            'accessMode' => $this->accessMode,
            'token' => $this->token,
            'scope' => $this->scope ?? '',
            'refreshToken' => $this->refreshToken ?? '',
            'expires' => $this->expires,
            'refreshTokenExpires' => $this->refreshTokenExpires,
            'userId' => $this->userId,
            'user' => $this->user,
        ];
        if ($this->webhooksConfigHash !== null) {
            $arr['webhooks_config_hash'] = $this->webhooksConfigHash;
        }
        if ($this->webhooksRegisteredAt !== null) {
            $arr['webhooks_registered_at'] = $this->webhooksRegisteredAt->format(\DateTimeInterface::ATOM);
        }
        return $arr;
    }

    /**
     * Hydrate entity from token array (from exchange/refresh result).
     *
     * @param array{shop?: string, accessMode?: string, token?: string, scope?: string, refreshToken?: string, expires?: string|null, refreshTokenExpires?: string|null, userId?: string|null, user?: array|null, webhooks_config_hash?: string|null, webhooks_registered_at?: string|null} $data
     */
    public function fromTokenArray(array $data): self
    {
        $this->shop = $data['shop'] ?? '';
        $this->accessMode = $data['accessMode'] ?? 'offline';
        $this->token = $data['token'] ?? '';
        $this->scope = $data['scope'] ?? null;
        $this->refreshToken = $data['refreshToken'] ?? null;
        $this->expires = $data['expires'] ?? null;
        $this->refreshTokenExpires = $data['refreshTokenExpires'] ?? null;
        $this->userId = $data['userId'] ?? null;
        $this->user = $data['user'] ?? null;
        $this->webhooksConfigHash = isset($data['webhooks_config_hash']) ? (string) $data['webhooks_config_hash'] : null;
        if (isset($data['webhooks_registered_at']) && $data['webhooks_registered_at'] !== null && $data['webhooks_registered_at'] !== '') {
            $at = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $data['webhooks_registered_at']);
            $this->webhooksRegisteredAt = $at ?: null;
        } else {
            $this->webhooksRegisteredAt = null;
        }
        $this->touch();
        return $this;
    }
}
