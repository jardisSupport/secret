<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit;

use JardisSupport\Contract\Secret\SecretResolverInterface;
use JardisSupport\Secret\Secret;
use PHPUnit\Framework\TestCase;

/**
 * Covers the detection API Secret::matches() — the format question consumers
 * (e.g. a kernel bootstrap) ask instead of duplicating the marker regex.
 *
 * @covers \JardisSupport\Secret\Secret
 */
class SecretMatchesTest extends TestCase
{
    public function testPlainPayloadIsMatched(): void
    {
        self::assertTrue(Secret::matches('secret(abc)'));
    }

    public function testPrefixedPayloadIsMatched(): void
    {
        self::assertTrue(Secret::matches('secret(aes:xyz)'));
    }

    public function testMissingClosingParenthesisIsNotMatched(): void
    {
        self::assertFalse(Secret::matches('secret(abc'));
    }

    public function testMarkerIsCaseSensitive(): void
    {
        self::assertFalse(Secret::matches('Secret(abc)'));
    }

    public function testLeadingCharacterBeforeMarkerIsNotMatched(): void
    {
        self::assertFalse(Secret::matches('xsecret(abc)'));
    }

    public function testEmptyStringIsNotMatched(): void
    {
        self::assertFalse(Secret::matches(''));
    }

    public function testEmptyPayloadIsNotMatched(): void
    {
        self::assertFalse(Secret::matches('secret()'));
    }

    /**
     * Consistency: matches() must answer exactly the question __invoke() asks
     * before resolving — a resolver that demonstrably changes every value it
     * sees changes the caster output for precisely the matching inputs.
     */
    public function testMatchesAgreesWithCasterResolutionForEveryValue(): void
    {
        $resolver = new class implements SecretResolverInterface {
            public function supports(string $encryptedValue): bool
            {
                return true;
            }

            public function resolve(string $encryptedValue): string
            {
                return 'resolved:' . $encryptedValue;
            }
        };

        $caster = new Secret($resolver);

        $values = [
            'secret(abc)',
            'secret(aes:xyz)',
            'secret(abc(def))',
            'secret(abc',
            'Secret(abc)',
            'xsecret(abc)',
            'secret(value)extra',
            '',
            'secret()',
            'plain-value',
        ];

        foreach ($values as $value) {
            $wasResolved = $caster($value) !== $value;

            self::assertSame(
                Secret::matches($value),
                $wasResolved,
                sprintf('matches() disagrees with caster behaviour for %s', var_export($value, true)),
            );
        }
    }
}
