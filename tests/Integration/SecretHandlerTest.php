<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Integration;

use JardisSupport\Secret\KeyProvider\EnvKeyProvider;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;
use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\Handler\SecretHandler;
use PHPUnit\Framework\TestCase;

class SecretHandlerTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = random_bytes(32);
    }

    public function testWithFileKeyProvider(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'key_');
        file_put_contents($tmpFile, base64_encode($this->key));

        $handler = new SecretHandler(new FileKeyProvider($tmpFile));
        $encrypted = AesSecretResolver::encrypt('file_key_test', $this->key);

        $result = ($handler)('secret(' . $encrypted . ')');

        $this->assertSame('file_key_test', $result);

        unlink($tmpFile);
    }

    public function testWithEnvKeyProvider(): void
    {
        putenv('TEST_SECRET_HANDLER_KEY=' . base64_encode($this->key));

        $handler = new SecretHandler(new EnvKeyProvider('TEST_SECRET_HANDLER_KEY'));
        $encrypted = AesSecretResolver::encrypt('env_key_test', $this->key);

        $result = ($handler)('secret(' . $encrypted . ')');

        $this->assertSame('env_key_test', $result);

        putenv('TEST_SECRET_HANDLER_KEY');
    }
}
