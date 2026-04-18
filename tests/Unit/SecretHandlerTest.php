<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit;

use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\Resolver\SodiumSecretResolver;
use JardisSupport\Secret\Handler\SecretHandler;
use PHPUnit\Framework\TestCase;

class SecretHandlerTest extends TestCase
{
    private string $key;
    private SecretHandler $handler;

    protected function setUp(): void
    {
        $this->key = random_bytes(32);
        $key = $this->key;
        $this->handler = new SecretHandler(fn() => $key);
    }

    public function testPlainValuePassesThrough(): void
    {
        $result = ($this->handler)('plain_value');

        $this->assertSame('plain_value', $result);
    }

    public function testNullPassesThrough(): void
    {
        $result = ($this->handler)(null);

        $this->assertNull($result);
    }

    public function testAesSecretIsDecrypted(): void
    {
        $encrypted = AesSecretResolver::encrypt('my_password', $this->key);

        $result = ($this->handler)('secret(' . $encrypted . ')');

        $this->assertSame('my_password', $result);
    }

    public function testAesPrefixedSecretIsDecrypted(): void
    {
        $encrypted = AesSecretResolver::encrypt('my_password', $this->key);

        $result = ($this->handler)('secret(aes:' . $encrypted . ')');

        $this->assertSame('my_password', $result);
    }

    public function testSodiumSecretIsDecrypted(): void
    {
        $encrypted = SodiumSecretResolver::encrypt('my_token', $this->key);

        $result = ($this->handler)('secret(sodium:' . $encrypted . ')');

        $this->assertSame('my_token', $result);
    }

}
