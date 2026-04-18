<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit\Exception;

use JardisSupport\Contract\Secret\SecretResolutionException;
use JardisSupport\Secret\Exception\SecretException;
use PHPUnit\Framework\TestCase;

class SecretExceptionTest extends TestCase
{
    public function testSecretExceptionExtendsSecretResolutionException(): void
    {
        $exception = new SecretException('Test error message');

        $this->assertInstanceOf(SecretResolutionException::class, $exception);
    }

    public function testExceptionMessage(): void
    {
        $message = 'An error occurred';
        $exception = new SecretException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(SecretException::class);
        $this->expectExceptionMessage('Test exception');

        throw new SecretException('Test exception');
    }
}
