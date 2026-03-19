<?php

declare(strict_types=1);

namespace App\Shopify;

use Shopify\App\ShopifyApp;

/**
 * Factory for ShopifyApp instance using environment configuration.
 */
final class ShopifyAppFactory
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $oldClientSecret = null,
    ) {
    }

    public function create(): ShopifyApp
    {
        return new ShopifyApp(
            clientId: $this->clientId,
            clientSecret: $this->clientSecret,
            oldClientSecret: $this->oldClientSecret !== '' ? $this->oldClientSecret : null,
        );
    }
}
