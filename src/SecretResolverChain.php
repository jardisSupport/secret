<?php

declare(strict_types=1);

namespace JardisSupport\Secret;

use JardisPort\Secret\SecretResolutionException;
use JardisPort\Secret\SecretResolverInterface;

/**
 * Chains multiple resolvers and delegates to the first one that supports the value.
 *
 * Usage:
 *   $chain = (new SecretResolverChain())
 *       ->addResolver(new AesSecretResolver($key));
 */
class SecretResolverChain implements SecretResolverInterface
{
    /** @var array<int, SecretResolverInterface> */
    private array $resolvers;

    /**
     * @param array<int, SecretResolverInterface> $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers;
    }

    /**
     * Returns true if at least one resolver in the chain supports the given value.
     */
    public function supports(string $encryptedValue): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($encryptedValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delegates to the first resolver that supports the value.
     *
     * @throws SecretResolutionException When no resolver in the chain supports the value.
     */
    public function resolve(string $encryptedValue): string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($encryptedValue)) {
                return $resolver->resolve($encryptedValue);
            }
        }

        throw new SecretResolutionException(
            sprintf('No resolver found for secret value: %s', $encryptedValue)
        );
    }

    /**
     * Adds a resolver to the chain and returns a new instance (fluent builder).
     */
    public function addResolver(SecretResolverInterface $resolver): static
    {
        $clone            = clone $this;
        $clone->resolvers = [...$this->resolvers, $resolver];

        return $clone;
    }
}
