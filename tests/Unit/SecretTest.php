<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit;

use JardisSupport\Contract\Secret\SecretResolverInterface;
use JardisSupport\Secret\Secret;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\Secret\Secret
 */
class SecretTest extends TestCase
{
    public function testNullInputReturnsNull(): void
    {
        $caster = new Secret();

        self::assertNull($caster(null));
    }

    public function testValueWithoutMarkerIsReturnedUnchanged(): void
    {
        $caster = new Secret();

        self::assertSame('plain-value', $caster('plain-value'));
        self::assertSame('some-other-string', $caster('some-other-string'));
        self::assertSame('', $caster(''));
    }

    public function testValueWithSecretMarkerButNoResolverIsReturnedUnchanged(): void
    {
        $caster = new Secret(null);

        $value = 'secret(some-encrypted-value)';

        self::assertSame($value, $caster($value));
    }

    public function testValueWithSecretMarkerDelegatesToResolver(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->with('some-encrypted-value')
            ->willReturn('decrypted-plaintext');

        $caster = new Secret($resolver);

        self::assertSame('decrypted-plaintext', $caster('secret(some-encrypted-value)'));
    }

    public function testValueWithoutSecretMarkerDoesNotCallResolver(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $caster = new Secret($resolver);

        self::assertSame('plain-value', $caster('plain-value'));
    }

    public function testNestedParenthesesInValueAreFullyCaptured(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver
            ->expects(self::once())
            ->method('resolve')
            ->with('abc(def)')
            ->willReturn('plaintext');

        $caster = new Secret($resolver);

        self::assertSame('plaintext', $caster('secret(abc(def))'));
    }

    public function testPartialSecretMarkerIsNotMatched(): void
    {
        $caster = new Secret();

        // "secret(" without closing ")" — no match
        self::assertSame('secret(missing-close', $caster('secret(missing-close'));

        // Extra text after closing ")" — no match
        self::assertSame('secret(value)extra', $caster('secret(value)extra'));

        // Not starting with "secret(" — no match
        self::assertSame('prefixsecret(value)', $caster('prefixsecret(value)'));
    }

    public function testSecretWithNoResolverOnNullReturnNull(): void
    {
        $caster = new Secret();

        self::assertNull($caster(null));
    }
}
