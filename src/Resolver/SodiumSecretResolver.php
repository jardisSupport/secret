<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Resolver;

use JardisSupport\Contract\Secret\SecretResolverInterface;
use JardisSupport\Secret\Exception\DecryptionFailedException;
use JardisSupport\Secret\Exception\InvalidKeyException;

/**
 * XSalsa20-Poly1305 secret resolver using libsodium.
 *
 * Encrypted format: sodium:base64( nonce[24 bytes] + ciphertext_with_mac )
 *
 * The $key parameter accepts either a raw 32-byte string key or a callable
 * that returns the key string when invoked (lazy loading pattern).
 */
class SodiumSecretResolver implements SecretResolverInterface
{
    private const PREFIX       = 'sodium:';
    private const KEY_LENGTH   = SODIUM_CRYPTO_SECRETBOX_KEYBYTES;  // 32
    private const NONCE_LENGTH = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES; // 24

    /** @var string|callable(): string */
    private readonly mixed $key;

    /**
     * @param string|callable(): string $key A raw 32-byte key or a callable that returns the key.
     */
    public function __construct(string|callable $key)
    {
        $this->key = $key;
    }

    /**
     * Returns true if the value starts with the "sodium:" prefix.
     */
    public function supports(string $encryptedValue): bool
    {
        return str_starts_with($encryptedValue, self::PREFIX);
    }

    /**
     * Decrypts a sodium-encrypted value.
     *
     * @throws InvalidKeyException        When the key is not exactly 32 bytes.
     * @throws DecryptionFailedException  When base64 decoding or decryption fails.
     */
    public function resolve(string $encryptedValue): string
    {
        $key = $this->resolveKey();

        self::validateKey($key);

        $payload = substr($encryptedValue, strlen(self::PREFIX));

        $decoded = base64_decode($payload, strict: true);

        if ($decoded === false) {
            throw new DecryptionFailedException('Failed to base64-decode secret value');
        }

        $minLength = self::NONCE_LENGTH + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

        if (strlen($decoded) < $minLength) {
            throw new DecryptionFailedException('Sodium decryption failed — wrong key or corrupted data');
        }

        $nonce      = substr($decoded, 0, self::NONCE_LENGTH);
        $ciphertext = substr($decoded, self::NONCE_LENGTH);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new DecryptionFailedException('Sodium decryption failed — wrong key or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Encrypts a plaintext value using XSalsa20-Poly1305.
     *
     * Returns base64( nonce[24 bytes] + ciphertext_with_mac ).
     * Does NOT include the "sodium:" prefix — that is added by the caller
     * when storing the value in an .env file.
     *
     * @throws InvalidKeyException When the key is not exactly 32 bytes.
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        self::validateKey($key);

        $nonce      = random_bytes(self::NONCE_LENGTH);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return base64_encode($nonce . $ciphertext);
    }

    private function resolveKey(): string
    {
        if (is_callable($this->key)) {
            return ($this->key)();
        }

        return $this->key;
    }

    private static function validateKey(string $key): void
    {
        $length = strlen($key);

        if ($length !== self::KEY_LENGTH) {
            throw new InvalidKeyException(
                sprintf('Sodium key must be %d bytes, got %d bytes', self::KEY_LENGTH, $length)
            );
        }
    }
}
