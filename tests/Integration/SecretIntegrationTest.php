<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Integration;

use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\Resolver\SodiumSecretResolver;
use JardisSupport\Secret\Secret;
use JardisSupport\Secret\Handler\SecretResolverChain;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\Secret\Secret
 * @covers \JardisSupport\Secret\Handler\SecretResolverChain
 * @covers \JardisSupport\Secret\Resolver\AesSecretResolver
 * @covers \JardisSupport\Secret\Resolver\SodiumSecretResolver
 */
class SecretIntegrationTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = str_repeat('k', 32);
    }

    public function testEndToEndWithAesResolver(): void
    {
        $caster = new Secret(new AesSecretResolver($this->key));

        $plaintext = 'my-database-password';
        $encrypted = AesSecretResolver::encrypt($plaintext, $this->key);

        $result = $caster('secret(' . $encrypted . ')');

        self::assertSame($plaintext, $result);
    }

    public function testEndToEndWithSodiumResolver(): void
    {
        $caster = new Secret(new SodiumSecretResolver($this->key));

        $plaintext = 'my-api-key';
        $encrypted = SodiumSecretResolver::encrypt($plaintext, $this->key);

        $result = $caster('secret(sodium:' . $encrypted . ')');

        self::assertSame($plaintext, $result);
    }

    public function testEndToEndWithResolverChainAesAndSodium(): void
    {
        $chain = (new SecretResolverChain())
            ->addResolver(new SodiumSecretResolver($this->key))
            ->addResolver(new AesSecretResolver($this->key));

        $caster = new Secret($chain);

        $aesPlaintext    = 'aes-secret';
        $sodiumPlaintext = 'sodium-secret';

        $aesEncrypted    = AesSecretResolver::encrypt($aesPlaintext, $this->key);
        $sodiumEncrypted = SodiumSecretResolver::encrypt($sodiumPlaintext, $this->key);

        // AES without prefix (backward compatible catch-all)
        self::assertSame($aesPlaintext, $caster('secret(' . $aesEncrypted . ')'));

        // AES with explicit prefix
        self::assertSame($aesPlaintext, $caster('secret(aes:' . $aesEncrypted . ')'));

        // Sodium with prefix
        self::assertSame($sodiumPlaintext, $caster('secret(sodium:' . $sodiumEncrypted . ')'));
    }

    public function testEndToEndWithFileKeyProvider(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'secret_key_test_');
        self::assertNotFalse($tempFile);

        try {
            file_put_contents($tempFile, base64_encode($this->key));

            $keyProvider = new \JardisSupport\Secret\KeyProvider\FileKeyProvider($tempFile);
            $caster      = new Secret(new AesSecretResolver($keyProvider));

            $plaintext = 'file-key-secret';
            $encrypted = AesSecretResolver::encrypt($plaintext, $this->key);

            self::assertSame($plaintext, $caster('secret(' . $encrypted . ')'));
        } finally {
            unlink($tempFile);
        }
    }

    public function testEndToEndWithEnvKeyProvider(): void
    {
        $envVar = 'JARDIS_SECRET_INTEGRATION_TEST_KEY';
        putenv($envVar . '=' . base64_encode($this->key));

        try {
            $keyProvider = new \JardisSupport\Secret\KeyProvider\EnvKeyProvider($envVar);
            $caster      = new Secret(new AesSecretResolver($keyProvider));

            $plaintext = 'env-key-secret';
            $encrypted = AesSecretResolver::encrypt($plaintext, $this->key);

            self::assertSame($plaintext, $caster('secret(' . $encrypted . ')'));
        } finally {
            putenv($envVar);
        }
    }
}
