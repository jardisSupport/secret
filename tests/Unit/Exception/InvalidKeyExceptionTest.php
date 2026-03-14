<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit\Exception;

use JardisSupport\Secret\Exception\InvalidKeyException;
use JardisSupport\Secret\Exception\SecretException;
use PHPUnit\Framework\TestCase;

class InvalidKeyExceptionTest extends TestCase
{
    public function testInvalidKeyExceptionExtendsSecretException(): void
    {
        $exception = new InvalidKeyException('Invalid key');

        $this->assertInstanceOf(SecretException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'AES-256 key must be 32 bytes, got 16 bytes';
        $exception = new InvalidKeyException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessage('Key file not found');

        throw new InvalidKeyException('Key file not found');
    }
}
