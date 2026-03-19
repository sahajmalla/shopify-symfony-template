<?php

declare(strict_types=1);

namespace App\Tests\Shopify;

use App\Shopify\Storage\AccessTokenCrypto;
use PHPUnit\Framework\TestCase;

final class AccessTokenCryptoTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $crypto = new AccessTokenCrypto($key);

        $plaintext = 'tok_123';
        $encrypted = $crypto->encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertStringStartsWith('enc:v1:', $encrypted);
        $this->assertSame($plaintext, $crypto->decrypt($encrypted));
    }

    public function testEncryptWithoutKeyReturnsPlaintext(): void
    {
        $crypto = new AccessTokenCrypto(null);
        $this->assertSame('tok_123', $crypto->encrypt('tok_123'));
        $this->assertFalse($crypto->isEnabled());
    }

    public function testDecryptPlaintextReturnsPlaintext(): void
    {
        $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $crypto = new AccessTokenCrypto($key);

        $this->assertSame('plain', $crypto->decrypt('plain'));
    }

    public function testInvalidKeyDisablesCrypto(): void
    {
        $crypto = new AccessTokenCrypto('not-base64');
        $this->assertFalse($crypto->isEnabled());
        $this->assertSame('tok', $crypto->encrypt('tok'));
    }
}
