<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit\Exception;

use JardisSupport\Secret\Exception\EncryptionFailedException;
use JardisSupport\Secret\Exception\SecretException;
use PHPUnit\Framework\TestCase;

class EncryptionFailedExceptionTest extends TestCase
{
    public function testEncryptionFailedExceptionExtendsSecretException(): void
    {
        $exception = new EncryptionFailedException('Encryption failed');

        $this->assertInstanceOf(SecretException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message   = 'AES-256-GCM encryption failed';
        $exception = new EncryptionFailedException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(EncryptionFailedException::class);
        $this->expectExceptionMessage('AES-256-GCM encryption failed');

        throw new EncryptionFailedException('AES-256-GCM encryption failed');
    }
}
