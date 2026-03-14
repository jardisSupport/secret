<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit\Exception;

use JardisSupport\Secret\Exception\DecryptionFailedException;
use JardisSupport\Secret\Exception\SecretException;
use PHPUnit\Framework\TestCase;

class DecryptionFailedExceptionTest extends TestCase
{
    public function testDecryptionFailedExceptionExtendsSecretException(): void
    {
        $exception = new DecryptionFailedException('Decryption failed');

        $this->assertInstanceOf(SecretException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'AES-256-GCM decryption failed — wrong key or corrupted data';
        $exception = new DecryptionFailedException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Failed to base64-decode secret value');

        throw new DecryptionFailedException('Failed to base64-decode secret value');
    }
}
