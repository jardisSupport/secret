<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Resolver;

use JardisPort\Secret\SecretResolverInterface;
use JardisSupport\Secret\Exception\DecryptionFailedException;
use JardisSupport\Secret\Exception\InvalidKeyException;

/**
 * AES-256-GCM secret resolver.
 *
 * Encrypted format: [aes:]base64( nonce[12 bytes] + ciphertext[variable] + tag[16 bytes] )
 *
 * Supports an optional "aes:" prefix for explicit resolver selection in a chain.
 * Without prefix, acts as catch-all fallback for backward compatibility.
 *
 * The $key parameter accepts either a raw 32-byte string key or a callable
 * that returns the key string when invoked (lazy loading pattern).
 */
class AesSecretResolver implements SecretResolverInterface
{
    private const PREFIX       = 'aes:';
    private const CIPHER       = 'aes-256-gcm';
    private const KEY_LENGTH   = 32;
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH   = 16;

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
     * Returns true for values with "aes:" prefix or any non-empty string without
     * a known resolver prefix (catch-all fallback for backward compatibility).
     */
    public function supports(string $encryptedValue): bool
    {
        if ($encryptedValue === '') {
            return false;
        }

        if (str_starts_with($encryptedValue, self::PREFIX)) {
            return true;
        }

        // Catch-all fallback: support values without any known prefix
        return !str_contains($encryptedValue, ':');
    }

    /**
     * Decrypts an AES-256-GCM encrypted value.
     *
     * @throws InvalidKeyException        When the key is not exactly 32 bytes.
     * @throws DecryptionFailedException  When base64 decoding or decryption fails.
     */
    public function resolve(string $encryptedValue): string
    {
        $key = $this->resolveKey();

        $this->validateKey($key);

        $payload = str_starts_with($encryptedValue, self::PREFIX)
            ? substr($encryptedValue, strlen(self::PREFIX))
            : $encryptedValue;

        $decoded = base64_decode($payload, strict: true);

        if ($decoded === false) {
            throw new DecryptionFailedException('Failed to base64-decode secret value');
        }

        $minLength = self::NONCE_LENGTH + self::TAG_LENGTH;

        if (strlen($decoded) < $minLength) {
            throw new DecryptionFailedException('AES-256-GCM decryption failed — wrong key or corrupted data');
        }

        $nonce      = substr($decoded, 0, self::NONCE_LENGTH);
        $tag        = substr($decoded, -self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::NONCE_LENGTH, strlen($decoded) - self::NONCE_LENGTH - self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new DecryptionFailedException('AES-256-GCM decryption failed — wrong key or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Encrypts a plaintext value using AES-256-GCM.
     *
     * Returns base64( nonce[12 bytes] + raw_ciphertext + tag[16 bytes] ).
     * Intended for tooling/setup scripts to create encrypted values for .env files.
     *
     * @throws InvalidKeyException When the key is not exactly 32 bytes.
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        self::validateKey($key);

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag   = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new DecryptionFailedException('AES-256-GCM encryption failed');
        }

        return base64_encode($nonce . $ciphertext . $tag);
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
                sprintf('AES-256 key must be 32 bytes, got %d bytes', $length)
            );
        }
    }
}
