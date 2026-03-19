<?php

declare(strict_types=1);

namespace App\Shopify\Storage;

/**
 * Encrypts/decrypts access token and refresh token values at rest using libsodium.
 * Uses a base64-encoded 32-byte key from SHOPIFY_TOKEN_ENCRYPTION_KEY.
 * Encrypted values are prefixed with "enc:v1:" for backward compatibility.
 */
final class AccessTokenCrypto
{
    private const PREFIX = 'enc:v1:';
    private const KEY_BYTES = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

    public function __construct(
        private readonly ?string $encryptionKeyBase64 = null,
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = $this->getKey();
        if ($key === null) {
            return $plaintext;
        }

        $nonce = call_user_func('random_bytes', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (!str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        $key = $this->getKey();
        if ($key === null) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return $value;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $decrypted = @sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        return $decrypted !== false ? $decrypted : $value;
    }

    public function isEnabled(): bool
    {
        return $this->getKey() !== null;
    }

    /**
     * @return string|null 32-byte key or null if not configured
     */
    private function getKey(): ?string
    {
        if ($this->encryptionKeyBase64 === null || $this->encryptionKeyBase64 === '') {
            return null;
        }
        $key = base64_decode($this->encryptionKeyBase64, true);
        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            return null;
        }
        return $key;
    }
}
