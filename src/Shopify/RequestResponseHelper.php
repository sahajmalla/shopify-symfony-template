<?php

declare(strict_types=1);

namespace App\Shopify;

use Shopify\App\Types\AppHomePatchIdTokenResult;
use Shopify\App\Types\GQLResult;
use Shopify\App\Types\LogWithReq;
use Shopify\App\Types\ResponseInfo;
use Shopify\App\Types\ResultForReq;
use Shopify\App\Types\ResultWithExchangeableIdToken;
use Shopify\App\Types\ResultWithLoggedInCustomerId;
use Shopify\App\Types\ResultWithNonExchangeableIdToken;
use Shopify\App\Types\TokenExchangeResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converts Symfony Request to Shopify package request format and
 * Shopify result types to Symfony Response.
 */
final class RequestResponseHelper
{
    /**
     * Convert a Symfony Request to the array format expected by the Shopify package.
     */
    public static function requestToShopifyReq(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = \is_array($values) ? implode(', ', $values) : $values;
        }

        return [
            'method' => $request->getMethod(),
            'headers' => $headers,
            'url' => $request->getUri(),
            'body' => $request->getContent(),
        ];
    }

    /**
     * Convert a Shopify result (with log and response) to a Symfony Response.
     *
     * @param ResultForReq|ResultWithExchangeableIdToken|ResultWithLoggedInCustomerId|ResultWithNonExchangeableIdToken|TokenExchangeResult|GQLResult|AppHomePatchIdTokenResult $result
     */
    public static function resultToResponse(object $result, ?\Psr\Log\LoggerInterface $logger = null): Response
    {
        $log = $result->log;
        $code = $log->code;
        $detail = $log->detail;

        if ($logger !== null) {
            $context = ['code' => $code];
            if (property_exists($result, 'shop') && $result->shop !== null) {
                $context['shop'] = $result->shop;
            }
            $logger->info("{$code} - {$detail}", $context);
        }

        $resp = $result->response;
        if (!$resp instanceof ResponseInfo) {
            return new Response('Internal Server Error', 500);
        }

        $headers = $resp->headers;
        if (\is_object($headers)) {
            $headers = (array) $headers;
        }

        return new Response(
            $resp->body,
            $resp->status,
            $headers
        );
    }
}
