<?php

declare(strict_types=1);

namespace JardisSupport\Secret;

use JardisSupport\Contract\Secret\SecretResolverInterface;

/**
 * Invokable caster that plugs into the jardissupport/dotenv casting chain.
 *
 * Detects `secret(...)` markers in string values and delegates decryption to
 * the injected resolver. Must be positioned first in the cast chain (prepend)
 * so that decrypted values are subsequently processed by the other casters
 * (e.g. "true" → bool, "42" → int).
 *
 * Usage:
 *   $dotEnv = new DotEnv();
 *   $dotEnv->addHandler(new Secret(new Handler\SecretResolverChain()), prepend: true);
 */
class Secret
{
    public function __construct(
        private readonly ?SecretResolverInterface $resolver = null
    ) {
    }

    /**
     * Resolves a `secret(...)` wrapped value or returns it unchanged.
     *
     * - null input     → returns null
     * - no match       → returns $value as-is
     * - match, no resolver → returns $value as-is
     * - match with resolver → returns decrypted plaintext
     */
    public function __invoke(?string $value = null): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/^secret\((.+)\)$/', $value, $matches) !== 1) {
            return $value;
        }

        if ($this->resolver === null) {
            return $value;
        }

        return $this->resolver->resolve($matches[1]);
    }
}
