<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ShopifyAccessToken;
use PHPUnit\Framework\TestCase;

final class ShopifyAccessTokenTest extends TestCase
{
    public function testFromTokenArrayAndToTokenArray(): void
    {
        $entity = new ShopifyAccessToken();

        $data = [
            'shop' => 'test.myshopify.com',
            'accessMode' => 'offline',
            'token' => 'tok',
            'scope' => 'read_products',
            'refreshToken' => 'ref',
            'expires' => '2026-01-01T00:00:00+00:00',
            'refreshTokenExpires' => '2026-02-01T00:00:00+00:00',
            'userId' => '1',
            'user' => ['id' => 1],
            'webhooks_config_hash' => 'abc',
            'webhooks_registered_at' => '2026-01-02T00:00:00+00:00',
        ];

        $entity->fromTokenArray($data);
        $out = $entity->toTokenArray();

        $this->assertSame('test.myshopify.com', $out['shop']);
        $this->assertSame('offline', $out['accessMode']);
        $this->assertSame('tok', $out['token']);
        $this->assertSame('read_products', $out['scope']);
        $this->assertSame('ref', $out['refreshToken']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $out['expires']);
        $this->assertSame('2026-02-01T00:00:00+00:00', $out['refreshTokenExpires']);
        $this->assertSame('1', $out['userId']);
        $this->assertSame(['id' => 1], $out['user']);
        $this->assertSame('abc', $out['webhooks_config_hash']);
        $this->assertSame('2026-01-02T00:00:00+00:00', $out['webhooks_registered_at']);
    }
}
