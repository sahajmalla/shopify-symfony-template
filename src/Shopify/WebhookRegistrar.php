<?php

declare(strict_types=1);

namespace App\Shopify;

use App\Shopify\Storage\AccessTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Shopify\App\ShopifyApp;

/**
 * Registers required webhook subscriptions via Admin GraphQL when the config hash changes.
 * Uses WebhookSubscriptionInput.uri (current field; callbackUrl is deprecated).
 * GDPR topics (CUSTOMERS_DATA_REQUEST, CUSTOMERS_REDACT, SHOP_REDACT) cannot be
 * created via GraphQL and must be configured in shopify.app.toml (applied to all shops via CLI/Partner Dashboard).
 */
final class WebhookRegistrar
{
    private const API_VERSION = '2026-01';
    private const APP_UNINSTALLED_TOPIC = 'APP_UNINSTALLED';

    public function __construct(
        private readonly ShopifyAppFactory $shopifyAppFactory,
        private readonly AccessTokenStorageInterface $accessTokenStorage,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * Register webhooks for the shop if not already registered (config hash mismatch).
     * Call after a successful token exchange or refresh in App Home.
     *
     * @param array<string, mixed> $accessToken Decrypted token array from storage (must include 'token' and 'shop')
     */
    public function registerIfNeeded(array $accessToken): void
    {
        $shop = $accessToken['shop'] ?? '';
        $accessTokenValue = $accessToken['token'] ?? '';
        if ($shop === '' || $accessTokenValue === '') {
            return;
        }

        $baseUrl = rtrim($this->baseUrl, '/');
        $webhooks = $this->getWebhookDefinitions($baseUrl);
        $configHash = $this->computeConfigHash($webhooks);

        $storedHash = $accessToken['webhooks_config_hash'] ?? null;
        if ($storedHash === $configHash) {
            $this->logger->info('Webhooks already registered for shop', ['shop' => $shop, 'code' => 'webhooks_skip']);
            return;
        }

        $shopify = $this->shopifyAppFactory->create();

        foreach ($webhooks as $topic => $uri) {
            $result = $shopify->adminGraphQLRequest(
                $this->getCreateMutation(),
                $shop,
                $accessTokenValue,
                self::API_VERSION,
                null,
                [
                    'topic' => $topic,
                    'webhookSubscription' => ['uri' => $uri],
                ],
            );

            if (!$result->ok) {
                $this->logger->warning('Webhook registration failed', [
                    'shop' => $shop,
                    'topic' => $topic,
                    'code' => $result->log->code,
                    'detail' => $result->log->detail,
                ]);
                return;
            }

            $createPayload = \is_array($result->data) ? ($result->data['webhookSubscriptionCreate'] ?? []) : [];
            $userErrors = $createPayload['userErrors'] ?? [];
            if (\is_array($userErrors) && $userErrors !== []) {
                $this->logger->warning('Webhook registration returned userErrors', [
                    'shop' => $shop,
                    'topic' => $topic,
                    'userErrors' => $userErrors,
                ]);
                return;
            }
        }

        $updated = array_merge($accessToken, [
            'webhooks_config_hash' => $configHash,
            'webhooks_registered_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
        $this->accessTokenStorage->save($updated);
        $this->logger->info('Webhooks registered for shop', ['shop' => $shop, 'code' => 'webhooks_registered']);
    }

    /**
     * @return array<string, string> topic => callback URI
     */
    private function getWebhookDefinitions(string $baseUrl): array
    {
        return [
            self::APP_UNINSTALLED_TOPIC => $baseUrl . '/webhooks/app/uninstalled',
        ];
    }

    private function computeConfigHash(array $webhooks): string
    {
        ksort($webhooks);
        return hash('sha256', json_encode($webhooks, JSON_UNESCAPED_SLASHES));
    }

    private function getCreateMutation(): string
    {
        return <<<'GQL'
mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
  webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
    webhookSubscription {
      id
      topic
    }
    userErrors {
      field
      message
    }
  }
}
GQL;
    }
}
