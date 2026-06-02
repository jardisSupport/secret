<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Integration\KeyProvider;

use JardisSupport\Secret\Exception\InvalidKeyException;
use JardisSupport\Secret\KeyProvider\EnvKeyProvider;
use PHPUnit\Framework\TestCase;

class EnvKeyProviderTest extends TestCase
{
    private const TEST_VAR = 'JARDIS_SECRET_TEST_KEY';

    protected function tearDown(): void
    {
        putenv(self::TEST_VAR);
    }

    public function testSetEnvVarWithRawKeyReturnsKey(): void
    {
        $rawKey = str_repeat('A', 32);
        putenv(self::TEST_VAR . '=' . $rawKey);

        $provider = new EnvKeyProvider(self::TEST_VAR);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
    }

    public function testBase64Encoded32ByteKeyIsDecoded(): void
    {
        $rawKey = str_repeat('B', 32);
        $b64Key = base64_encode($rawKey);
        putenv(self::TEST_VAR . '=' . $b64Key);

        $provider = new EnvKeyProvider(self::TEST_VAR);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
        $this->assertSame(32, strlen($result));
    }

    public function testBase64ThatDoesNotDecodeToExactly32BytesReturnsRaw(): void
    {
        // base64 of 16 bytes — decoded is only 16 bytes, not 32 → raw returned
        $rawKey = str_repeat('C', 16);
        $b64Key = base64_encode($rawKey);
        putenv(self::TEST_VAR . '=' . $b64Key);

        $provider = new EnvKeyProvider(self::TEST_VAR);
        $result   = $provider();

        $this->assertSame($b64Key, $result);
    }

    public function testUnsetEnvVarThrowsInvalidKeyException(): void
    {
        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessageMatches('/is not set or empty/');

        $provider = new EnvKeyProvider('JARDIS_DEFINITELY_NOT_SET_12345');
        $provider();
    }

    public function testEmptyEnvVarThrowsInvalidKeyException(): void
    {
        putenv(self::TEST_VAR . '=');

        $this->expectException(InvalidKeyException::class);
        $this->expectExceptionMessageMatches('/is not set or empty/');

        $provider = new EnvKeyProvider(self::TEST_VAR);
        $provider();
    }

    public function testExceptionMessageContainsVariableName(): void
    {
        $varName = 'JARDIS_MISSING_VAR_XYZ';

        try {
            $provider = new EnvKeyProvider($varName);
            $provider();
            $this->fail('Expected InvalidKeyException was not thrown');
        } catch (InvalidKeyException $e) {
            $this->assertStringContainsString($varName, $e->getMessage());
        }
    }

    public function testEnvVarWithSurroundingWhitespaceIsHandled(): void
    {
        $rawKey = str_repeat('D', 32);
        // putenv does not trim, but __invoke() trims
        putenv(self::TEST_VAR . '=  ' . $rawKey . '  ');

        $provider = new EnvKeyProvider(self::TEST_VAR);
        $result   = $provider();

        $this->assertSame($rawKey, $result);
    }
}
