<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Handler;

use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\Resolver\SodiumSecretResolver;
use JardisSupport\Secret\Secret;

/**
 * Convenience handler for secret resolution in .env files.
 * Invokable — register via DotEnv::addHandler().
 *
 * Usage:
 *   $dotEnv = new DotEnv();
 *   $dotEnv->addHandler(new SecretHandler(new FileKeyProvider('support/secret.key')), prepend: true);
 */
class SecretHandler
{
    private Secret $caster;

    /**
     * @param callable(): string $keyProvider An invokable that returns the 32-byte key
     */
    public function __construct(callable $keyProvider)
    {
        $chain = (new SecretResolverChain())
            ->addResolver(new SodiumSecretResolver($keyProvider))
            ->addResolver(new AesSecretResolver($keyProvider));

        $this->caster = new Secret($chain);
    }

    public function __invoke(?string $value = null): ?string
    {
        return ($this->caster)($value);
    }
}
