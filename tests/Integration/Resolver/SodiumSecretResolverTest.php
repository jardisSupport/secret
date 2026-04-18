<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Integration\Resolver;

use JardisSupport\Secret\Exception\DecryptionFailedException;
use JardisSupport\Secret\Exception\InvalidKeyException;
use JardisSupport\Secret\Resolver\SodiumSecretResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\Secret\Resolver\SodiumSecretResolver
 */
class SodiumSecretResolverTest extends TestCase
{
    private string $validKey;

    protected function setUp(): void
    {
        $this->validKey = str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'my-super-secret-password';
        $encrypted = SodiumSecretResolver::encrypt($plaintext, $this->validKey);

        $resolver  = new SodiumSecretResolver($this->validKey);
        $decrypted = $resolver->resolve('sodium:' . $encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesValidBase64(): void
    {
        $encrypted = SodiumSecretResolver::encrypt('test', $this->validKey);

        self::assertNotEmpty($encrypted);
        self::assertNotFalse(base64_decode($encrypted, true));
    }

    public function testDecryptWithWrongKeyThrowsDecryptionFailedException(): void
    {
        $encrypted = SodiumSecretResolver::encrypt('secret-value', $this->validKey);

        $wrongKey = str_repeat('x', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $resolver = new SodiumSecretResolver($wrongKey);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Sodium decryption failed');

        $resolver->resolve('sodium:' . $encrypted);
    }

    public function testDecryptWithCorruptedBase64ThrowsDecryptionFailedException(): void
    {
        $resolver = new SodiumSecretResolver($this->validKey);

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Failed to base64-decode secret value');

        $resolver->resolve('sodium:!!!not-valid-base64!!!');
    }

    public function testDecryptWithTooShortDataThrowsDecryptionFailedException(): void
    {
        $resolver = new SodiumSecretResolver($this->validKey);

        $tooShort = base64_encode('short');

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Sodium decryption failed');

        $resolver->resolve('sodium:' . $tooShort);
    }

    public function testResolveWithKeyTooShortThrowsInvalidKeyException(): void
    {
        $resolver = new SodiumSecretResolver('tooshort');

        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage(
            sprintf('Sodium key must be %d bytes, got 8 bytes', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        );

        $resolver->resolve('sodium:somevalue');
    }

    public function testEncryptWithInvalidKeyLengthThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage(
            sprintf('Sodium key must be %d bytes, got 16 bytes', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        );

        SodiumSecretResolver::encrypt('plaintext', str_repeat('k', 16));
    }

    public function testEncryptWithEmptyKeyThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage(
            sprintf('Sodium key must be %d bytes, got 0 bytes', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        );

        SodiumSecretResolver::encrypt('plaintext', '');
    }

    public function testKeyFromCallableLazyLoading(): void
    {
        $called      = 0;
        $keyProvider = function () use (&$called): string {
            $called++;
            return str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        };

        $resolver = new SodiumSecretResolver($keyProvider);

        $plaintext = 'lazy-key-value';
        $encrypted = SodiumSecretResolver::encrypt($plaintext, $this->validKey);

        // Key callable is not invoked until resolve() is called
        self::assertSame(0, $called);

        $decrypted = $resolver->resolve('sodium:' . $encrypted);

        self::assertSame(1, $called);
        self::assertSame($plaintext, $decrypted);
    }

    public function testSupportsReturnsTrueForSodiumPrefix(): void
    {
        $resolver = new SodiumSecretResolver($this->validKey);

        self::assertTrue($resolver->supports('sodium:somevalue'));
        self::assertTrue($resolver->supports('sodium:base64encoded=='));
    }

    public function testSupportsReturnsFalseWithoutPrefix(): void
    {
        $resolver = new SodiumSecretResolver($this->validKey);

        self::assertFalse($resolver->supports('anyvalue'));
        self::assertFalse($resolver->supports(''));
        self::assertFalse($resolver->supports('aes:somevalue'));
    }

    public function testEncryptProducesDifferentOutputEachCall(): void
    {
        $plaintext  = 'same-plaintext';
        $encrypted1 = SodiumSecretResolver::encrypt($plaintext, $this->validKey);
        $encrypted2 = SodiumSecretResolver::encrypt($plaintext, $this->validKey);

        self::assertNotSame($encrypted1, $encrypted2);
    }

    public function testRoundTripWithBinaryPlaintext(): void
    {
        $plaintext = random_bytes(64);
        $encrypted = SodiumSecretResolver::encrypt($plaintext, $this->validKey);

        $resolver  = new SodiumSecretResolver($this->validKey);
        $decrypted = $resolver->resolve('sodium:' . $encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function testRoundTripWithEmptyPlaintext(): void
    {
        $encrypted = SodiumSecretResolver::encrypt('', $this->validKey);

        $resolver  = new SodiumSecretResolver($this->validKey);
        $decrypted = $resolver->resolve('sodium:' . $encrypted);

        self::assertSame('', $decrypted);
    }
}
