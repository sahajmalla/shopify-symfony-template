<?php

declare(strict_types=1);

namespace App\Controller;

use App\Shopify\RequestResponseHelper;
use App\Shopify\ShopifyAppFactory;
use App\Shopify\Storage\AccessTokenStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/webhooks')]
final class WebhookController extends AbstractController
{
    public function __construct(
        private readonly ShopifyAppFactory $shopifyAppFactory,
        private readonly AccessTokenStorageInterface $accessTokenStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * App uninstalled webhook. Verify the request and remove stored tokens for the shop.
     */
    #[Route(path: '/app/uninstalled', name: 'webhooks_app_uninstalled', methods: ['POST'])]
    public function appUninstalled(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);

        $result = $shopify->verifyWebhookReq($req);

        if (!$result->ok) {
            $this->logger->warning('Webhook verification failed', [
                'topic' => 'app/uninstalled',
                'code' => $result->log->code,
                'detail' => $result->log->detail,
                'shop' => $result->shop,
            ]);
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        $shop = $result->shop;
        if ($shop !== null) {
            $shopDomain = str_contains($shop, '.myshopify.com') ? $shop : $shop . '.myshopify.com';
            $this->accessTokenStorage->delete($shopDomain, 'offline');
            $this->accessTokenStorage->delete($shopDomain, 'online');
        }

        return new Response('', 200);
    }

    /**
     * Single endpoint for compliance webhooks when using compliance_topics with one URI in shopify.app.toml.
     * Shopify sends X-Shopify-Topic to distinguish: customers/data_request, customers/redact, shop/redact.
     */
    #[Route(path: '', name: 'webhooks_compliance', methods: ['POST'], priority: -10)]
    public function compliance(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);
        $result = $shopify->verifyWebhookReq($req);

        $topic = (string) $request->headers->get('X-Shopify-Topic', '');

        if (!$result->ok) {
            $this->logger->warning('Webhook verification failed', [
                'topic' => $topic !== '' ? $topic : 'compliance',
                'code' => $result->log->code,
                'shop' => $result->shop,
            ]);
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        switch ($topic) {
            case 'customers/data_request':
                // If you store customer data, look it up by shop/customer and return or email it per GDPR.
                return new Response('', 200);
            case 'customers/redact':
                // If you store customer PII, delete it for the customer/shop from the payload.
                return new Response('', 200);
            case 'shop/redact':
                // Tokens are removed on app/uninstalled; remove any other shop data here if needed.
                return new Response('', 200);
            default:
                $this->logger->warning('Compliance webhook with unknown topic', ['topic' => $topic]);
                return new Response('', 200);
        }
    }

    /**
     * GDPR: customer data request. Verify and respond 200; implement data export if you store customer data.
     */
    #[Route(path: '/customers/data_request', name: 'webhooks_customers_data_request', methods: ['POST'])]
    public function customersDataRequest(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);
        $result = $shopify->verifyWebhookReq($req);

        if (!$result->ok) {
            $this->logger->warning('Webhook verification failed', [
                'topic' => 'customers/data_request',
                'code' => $result->log->code,
                'shop' => $result->shop,
            ]);
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        // If you store customer data, look it up by shop/customer and return or email it per GDPR.
        return new Response('', 200);
    }

    /**
     * GDPR: customer redact. Verify and respond 200; delete customer data if stored.
     */
    #[Route(path: '/customers/redact', name: 'webhooks_customers_redact', methods: ['POST'])]
    public function customersRedact(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);
        $result = $shopify->verifyWebhookReq($req);

        if (!$result->ok) {
            $this->logger->warning('Webhook verification failed', [
                'topic' => 'customers/redact',
                'code' => $result->log->code,
                'shop' => $result->shop,
            ]);
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        // If you store customer PII, delete it for the customer/shop from the payload.
        return new Response('', 200);
    }

    /**
     * GDPR: shop redact. Verify and respond 200; delete shop data (e.g. tokens already removed on app/uninstalled).
     */
    #[Route(path: '/shop/redact', name: 'webhooks_shop_redact', methods: ['POST'])]
    public function shopRedact(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);
        $result = $shopify->verifyWebhookReq($req);

        if (!$result->ok) {
            $this->logger->warning('Webhook verification failed', [
                'topic' => 'shop/redact',
                'code' => $result->log->code,
                'shop' => $result->shop,
            ]);
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        // Tokens are removed on app/uninstalled; remove any other shop data here if needed.
        return new Response('', 200);
    }
}
