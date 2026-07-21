<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Integration\Resolver;

use JardisSupport\Secret\Exception\DecryptionFailedException;
use JardisSupport\Secret\Exception\InvalidKeyException;
use JardisSupport\Secret\Resolver\AesSecretResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\Secret\Resolver\AesSecretResolver
 */
class AesSecretResolverTest extends TestCase
{
    private string $validKey;

    protected function setUp(): void
    {
        $this->validKey = str_repeat('k', 32);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'my-super-secret-password';
        $encrypted = AesSecretResolver::encrypt($plaintext, $this->validKey);

        $resolver  = new AesSecretResolver($this->validKey);
        $decrypted = $resolver->resolve($encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesValidBase64(): void
    {
        $encrypted = AesSecretResolver::encrypt('test', $this->validKey);

        self::assertNotEmpty($encrypted);
        self::assertNotFalse(base64_decode($encrypted, true));
    }

    public function testDecryptWithWrongKeyThrowsDecryptionFailedException(): void
    {
        $encrypted = AesSecretResolver::encrypt('secret-value', $this->validKey);

        $wrongKey = str_repeat('x', 32);
        $resolver = new AesSecretResolver($wrongKey);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('AES-256-GCM decryption failed');

        $resolver->resolve($encrypted);
    }

    public function testDecryptWithCorruptedBase64ThrowsDecryptionFailedException(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Failed to base64-decode secret value');

        $resolver->resolve('!!!not-valid-base64!!!');
    }

    public function testDecryptWithTooShortDataThrowsDecryptionFailedException(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        // Valid base64 but too short to contain nonce + tag
        $tooShort = base64_encode('short');

        $this->expectException(DecryptionFailedException::class);

        $resolver->resolve($tooShort);
    }

    public function testResolveWithKeyTooShortThrowsInvalidKeyException(): void
    {
        $resolver = new AesSecretResolver('tooshort');

        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage('AES-256 key must be 32 bytes, got 8 bytes');

        $resolver->resolve('somevalue');
    }

    public function testEncryptWithInvalidKeyLengthThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage('AES-256 key must be 32 bytes, got 16 bytes');

        AesSecretResolver::encrypt('plaintext', str_repeat('k', 16));
    }

    public function testEncryptWithEmptyKeyThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage('AES-256 key must be 32 bytes, got 0 bytes');

        AesSecretResolver::encrypt('plaintext', '');
    }

    public function testKeyFromCallableLazyLoading(): void
    {
        $called      = 0;
        $keyProvider = function () use (&$called): string {
            $called++;
            return str_repeat('k', 32);
        };

        $resolver = new AesSecretResolver($keyProvider);

        $plaintext = 'lazy-key-value';
        $encrypted = AesSecretResolver::encrypt($plaintext, $this->validKey);

        // Key callable is not invoked until resolve() is called
        self::assertSame(0, $called);

        $decrypted = $resolver->resolve($encrypted);

        self::assertSame(1, $called);
        self::assertSame($plaintext, $decrypted);
    }

    public function testSupportsReturnsTrueForAesPrefix(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        self::assertTrue($resolver->supports('aes:somevalue'));
        self::assertTrue($resolver->supports('aes:base64encoded=='));
    }

    public function testSupportsReturnsTrueForValueWithoutPrefix(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        self::assertTrue($resolver->supports('anyvalue'));
        self::assertTrue($resolver->supports('a'));
        self::assertTrue($resolver->supports('some-base64-encoded-secret=='));
    }

    public function testSupportsReturnsFalseForEmptyString(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        self::assertFalse($resolver->supports(''));
    }

    public function testSupportsReturnsFalseForOtherResolverPrefix(): void
    {
        $resolver = new AesSecretResolver($this->validKey);

        self::assertFalse($resolver->supports('sodium:somevalue'));
        self::assertFalse($resolver->supports('vault:secret/data/db'));
    }

    public function testResolveWithAesPrefixRoundTrip(): void
    {
        $plaintext = 'prefixed-secret';
        $encrypted = AesSecretResolver::encrypt($plaintext, $this->validKey);

        $resolver  = new AesSecretResolver($this->validKey);
        $decrypted = $resolver->resolve('aes:' . $encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachCall(): void
    {
        // Each call uses a fresh random nonce, so output differs
        $plaintext  = 'same-plaintext';
        $encrypted1 = AesSecretResolver::encrypt($plaintext, $this->validKey);
        $encrypted2 = AesSecretResolver::encrypt($plaintext, $this->validKey);

        self::assertNotSame($encrypted1, $encrypted2);
    }

    public function testRoundTripWithBinaryPlaintext(): void
    {
        $plaintext = random_bytes(64);
        $encrypted = AesSecretResolver::encrypt($plaintext, $this->validKey);

        $resolver  = new AesSecretResolver($this->validKey);
        $decrypted = $resolver->resolve($encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function testRoundTripWithEmptyPlaintext(): void
    {
        $encrypted = AesSecretResolver::encrypt('', $this->validKey);

        $resolver  = new AesSecretResolver($this->validKey);
        $decrypted = $resolver->resolve($encrypted);

        self::assertSame('', $decrypted);
    }
}
