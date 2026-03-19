<?php

declare(strict_types=1);

namespace App\Shopify\Storage;

/**
 * Storage for Shopify access tokens (offline/online).
 * Implemented by DoctrineAccessTokenStorage (Doctrine ORM).
 *
 * Token array shape: shop, accessMode, token, scope, refreshToken, expires,
 * refreshTokenExpires, userId, user (see shopify-app-php README).
 */
interface AccessTokenStorageInterface
{
    /**
     * @return array<string, mixed>|null Token array or null if not found
     */
    public function get(string $shop, string $accessMode = 'offline'): ?array;

    public function save(array $token): void;

    public function delete(string $shop, string $accessMode = 'offline'): void;
}
