<?php

declare(strict_types=1);

namespace App\Controller;

use App\Shopify\RequestResponseHelper;
use App\Shopify\Storage\AccessTokenStorageInterface;
use App\Shopify\ShopifyAppFactory;
use App\Shopify\WebhookRegistrar;
use Psr\Log\LoggerInterface;
use Shopify\App\Types\TokenExchangeAccessToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/')]
final class AppHomeController extends AbstractController
{
    private const APP_HOME_PATCH_ID_TOKEN_PATH = '/auth/patch-id-token';
    private const API_VERSION = '2026-01';

    public function __construct(
        private readonly ShopifyAppFactory $shopifyAppFactory,
        private readonly AccessTokenStorageInterface $accessTokenStorage,
        private readonly WebhookRegistrar $webhookRegistrar,
        private readonly LoggerInterface $logger,
        private readonly string $shopifyClientId,
    ) {
    }

    /**
     * Embedded app home. Verifies the request, ensures an offline access token exists,
     * and renders the app with App Bridge and Polaris.
     */
    #[Route(path: '', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);

        $result = $shopify->verifyAppHomeReq($req, appHomePatchIdTokenPath: self::APP_HOME_PATCH_ID_TOKEN_PATH);

        if (!$result->ok) {
            return RequestResponseHelper::resultToResponse($result, $this->logger);
        }

        $shop = $result->shop;
        if ($shop === null) {
            return new Response('Forbidden', 403);
        }

        // Ensure shop domain includes .myshopify.com for storage key consistency
        $shopDomain = str_contains($shop, '.myshopify.com') ? $shop : $shop . '.myshopify.com';

        $accessToken = $this->accessTokenStorage->get($shopDomain, 'offline');

        if ($accessToken !== null) {
            $refreshResult = $shopify->refreshTokenExchangedAccessToken($accessToken);

            if (!$refreshResult->ok) {
                return RequestResponseHelper::resultToResponse($refreshResult, $this->logger);
            }

            if ($refreshResult->accessToken !== null) {
                $this->accessTokenStorage->save(self::tokenToArray($refreshResult->accessToken, null));
            }
            $token = $this->accessTokenStorage->get($shopDomain, 'offline');
            if ($token !== null) {
                $this->webhookRegistrar->registerIfNeeded($token);
            }
        } else {
            $idToken = $result->idToken;
            if ($idToken === null || !$idToken->exchangeable) {
                return new Response('Unauthorized', 401);
            }

            $exchangeResult = $shopify->exchangeUsingTokenExchange(
                accessMode: 'offline',
                idToken: $idToken,
                invalidTokenResponse: $result->newIdTokenResponse,
            );

            if (!$exchangeResult->ok || $exchangeResult->accessToken === null) {
                return RequestResponseHelper::resultToResponse($exchangeResult, $this->logger);
            }

            $this->accessTokenStorage->save(self::tokenToArray($exchangeResult->accessToken, $result->userId));
            $token = $this->accessTokenStorage->get($shopDomain, 'offline');
            if ($token !== null) {
                $this->webhookRegistrar->registerIfNeeded($token);
            }
        }

        $response = $this->render('app_home/index.html.twig', [
            'client_id' => $this->shopifyClientId,
        ]);

        // Copy security headers from verify result to response (iframe protection)
        $resultHeaders = $result->response->headers;
        $headers = \is_object($resultHeaders) ? (array) $resultHeaders : $resultHeaders;
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Convert TokenExchangeAccessToken to stored array shape (with optional userId).
     *
     * @return array{shop: string, accessMode: string, token: string, scope: string, refreshToken: string, expires: ?string, refreshTokenExpires: ?string, userId: ?string, user: ?array}
     */
    private static function tokenToArray(TokenExchangeAccessToken $token, ?string $userId): array
    {
        return [
            'shop' => $token->shop,
            'accessMode' => $token->accessMode,
            'token' => $token->token,
            'scope' => $token->scope,
            'refreshToken' => $token->refreshToken,
            'expires' => $token->expires,
            'refreshTokenExpires' => $token->refreshTokenExpires,
            'userId' => $userId,
            'user' => $token->user,
        ];
    }
}
