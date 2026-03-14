<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit\KeyProvider;

use JardisSupport\Secret\Exception\InvalidKeyException;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;
use PHPUnit\Framework\TestCase;

class FileKeyProviderTest extends TestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'secret_key_test_');
        if ($tempFile === false) {
            $this->fail('Could not create temporary file');
        }
        $this->tempFile = $tempFile;
    }

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testValidRawKeyFileReturnsKey(): void
    {
        $rawKey = str_repeat('A', 32);
        file_put_contents($this->tempFile, $rawKey);

        $provider = new FileKeyProvider($this->tempFile);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
    }

    public function testBase64Encoded32ByteKeyIsDecoded(): void
    {
        $rawKey    = str_repeat('B', 32);
        $b64Key    = base64_encode($rawKey);

        file_put_contents($this->tempFile, $b64Key);

        $provider = new FileKeyProvider($this->tempFile);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
        $this->assertSame(32, strlen($result));
    }

    public function testBase64KeyWithWhitespaceIsDecoded(): void
    {
        $rawKey = str_repeat('C', 32);
        $b64Key = base64_encode($rawKey);

        file_put_contents($this->tempFile, "\n" . $b64Key . "\n");

        $provider = new FileKeyProvider($this->tempFile);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
    }

    public function testBase64ThatDoesNotDecodeToExactly32BytesReturnsRaw(): void
    {
        // base64 of 16 bytes — decoded is only 16 bytes, not 32 → raw returned
        $rawKey = str_repeat('D', 16);
        $b64Key = base64_encode($rawKey);

        file_put_contents($this->tempFile, $b64Key);

        $provider = new FileKeyProvider($this->tempFile);
        $result   = $provider();

        $this->assertSame($b64Key, $result);
    }

    public function testMissingFileThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessageMatches('/does not exist or is not readable/');

        $provider = new FileKeyProvider('/nonexistent/path/to/key.file');
        $provider();
    }

    public function testEmptyFileThrowsInvalidKeyException(): void
    {
        file_put_contents($this->tempFile, '');

        $this->expectException(InvalidKeyException::class);

        $provider = new FileKeyProvider($this->tempFile);
        $provider();
    }

    public function testFileContainingOnlyWhitespaceThrowsInvalidKeyException(): void
    {
        file_put_contents($this->tempFile, "   \n\t  \n");

        $this->expectException(InvalidKeyException::class);

        $provider = new FileKeyProvider($this->tempFile);
        $provider();
    }
}
