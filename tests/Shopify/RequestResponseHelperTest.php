<?php

declare(strict_types=1);

namespace App\Tests\Shopify;

use App\Shopify\RequestResponseHelper;
use PHPUnit\Framework\TestCase;
use Shopify\App\Types\Log;
use Shopify\App\Types\ResponseInfo;
use Symfony\Component\HttpFoundation\Request;

final class RequestResponseHelperTest extends TestCase
{
    public function testRequestToShopifyReq(): void
    {
        $request = Request::create('https://example.com/path?foo=bar', 'POST', [], [], [], [], 'body');
        $request->headers->set('X-Test', 'one');
        $request->headers->set('X-Multi', ['a', 'b']);

        $req = RequestResponseHelper::requestToShopifyReq($request);

        $this->assertSame('POST', $req['method']);
        $this->assertSame('https://example.com/path?foo=bar', $req['url']);
        $this->assertSame('body', $req['body']);
        $this->assertSame('one', $req['headers']['x-test']);
        $this->assertSame('a, b', $req['headers']['x-multi']);
    }

    public function testResultToResponse(): void
    {
        $log = new Log('ok', 'test');
        $responseInfo = new ResponseInfo(200, 'ok', ['X-Test' => '1']);
        $result = new class($log, $responseInfo) {
            public function __construct(
                public Log $log,
                public ResponseInfo $response,
            ) {
            }
        };

        $response = RequestResponseHelper::resultToResponse($result);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
        $this->assertSame('1', $response->headers->get('X-Test'));
    }
}
