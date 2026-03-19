<?php

declare(strict_types=1);

namespace App\Shopify\Storage;

use App\Entity\ShopifyAccessToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine-backed access token storage for production.
 * Encrypts token and refreshToken at rest when AccessTokenCrypto is configured.
 */
final class DoctrineAccessTokenStorage implements AccessTokenStorageInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?AccessTokenCrypto $crypto = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $shop, string $accessMode = 'offline'): ?array
    {
        $entity = $this->entityManager->getRepository(ShopifyAccessToken::class)->findOneBy(
            ['shop' => $shop, 'accessMode' => $accessMode]
        );

        if ($entity instanceof ShopifyAccessToken) {
            $arr = $entity->toTokenArray();
            $this->decryptTokenArray($arr);
            return $arr;
        }

        return null;
    }

    public function save(array $token): void
    {
        $shop = $token['shop'] ?? '';
        $accessMode = $token['accessMode'] ?? 'offline';
        if ($shop === '') {
            return;
        }

        $entity = $this->entityManager->getRepository(ShopifyAccessToken::class)->findOneBy(
            ['shop' => $shop, 'accessMode' => $accessMode]
        );

        if ($entity === null) {
            $entity = new ShopifyAccessToken();
            $this->entityManager->persist($entity);
        }

        $toSave = $this->encryptTokenArray($token);
        $entity->fromTokenArray($toSave);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $token
     */
    private function encryptTokenArray(array $token): array
    {
        if ($this->crypto === null || !$this->crypto->isEnabled()) {
            return $token;
        }
        $out = $token;
        if (isset($out['token']) && $out['token'] !== '') {
            $out['token'] = $this->crypto->encrypt((string) $out['token']);
        }
        if (isset($out['refreshToken']) && $out['refreshToken'] !== '') {
            $out['refreshToken'] = $this->crypto->encrypt((string) $out['refreshToken']);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $token
     */
    private function decryptTokenArray(array &$token): void
    {
        if ($this->crypto === null || !$this->crypto->isEnabled()) {
            return;
        }
        if (isset($token['token']) && $token['token'] !== '') {
            $token['token'] = $this->crypto->decrypt((string) $token['token']);
        }
        if (isset($token['refreshToken']) && $token['refreshToken'] !== '') {
            $token['refreshToken'] = $this->crypto->decrypt((string) $token['refreshToken']);
        }
    }

    public function delete(string $shop, string $accessMode = 'offline'): void
    {
        $entity = $this->entityManager->getRepository(ShopifyAccessToken::class)->findOneBy(
            ['shop' => $shop, 'accessMode' => $accessMode]
        );

        if ($entity instanceof ShopifyAccessToken) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
