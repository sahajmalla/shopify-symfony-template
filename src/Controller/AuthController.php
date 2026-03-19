<?php

declare(strict_types=1);

namespace App\Controller;

use App\Shopify\RequestResponseHelper;
use App\Shopify\ShopifyAppFactory;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the App Home patch ID token route for embedded app session resilience.
 * See https://shopify.dev/docs/apps/build/security/set-up-iframe-protection
 */
#[Route(path: '/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly ShopifyAppFactory $shopifyAppFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/patch-id-token', name: 'auth_patch_id_token', methods: ['GET'])]
    public function patchIdToken(Request $request): Response
    {
        $shopify = $this->shopifyAppFactory->create();
        $req = RequestResponseHelper::requestToShopifyReq($request);
        $result = $shopify->appHomePatchIdToken($req);

        return RequestResponseHelper::resultToResponse($result, $this->logger);
    }
}
