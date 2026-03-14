<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Tests\Unit;

use JardisPort\Secret\SecretResolutionException;
use JardisPort\Secret\SecretResolverInterface;
use JardisSupport\Secret\SecretResolverChain;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\Secret\SecretResolverChain
 */
class SecretResolverChainTest extends TestCase
{
    public function testSupportsReturnsFalseWhenChainIsEmpty(): void
    {
        $chain = new SecretResolverChain();

        self::assertFalse($chain->supports('any-value'));
    }

    public function testSupportsReturnsFalseWhenNoResolverMatches(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver->method('supports')->willReturn(false);

        $chain = new SecretResolverChain([$resolver]);

        self::assertFalse($chain->supports('some-value'));
    }

    public function testSupportsReturnsTrueWhenAtLeastOneResolverMatches(): void
    {
        /** @var SecretResolverInterface&MockObject $first */
        $first = $this->createMock(SecretResolverInterface::class);
        $first->method('supports')->willReturn(false);

        /** @var SecretResolverInterface&MockObject $second */
        $second = $this->createMock(SecretResolverInterface::class);
        $second->method('supports')->willReturn(true);

        $chain = new SecretResolverChain([$first, $second]);

        self::assertTrue($chain->supports('some-value'));
    }

    public function testResolveDelegatesToFirstMatchingResolver(): void
    {
        /** @var SecretResolverInterface&MockObject $first */
        $first = $this->createMock(SecretResolverInterface::class);
        $first->method('supports')->willReturn(false);
        $first->expects(self::never())->method('resolve');

        /** @var SecretResolverInterface&MockObject $second */
        $second = $this->createMock(SecretResolverInterface::class);
        $second->method('supports')->willReturn(true);
        $second->expects(self::once())->method('resolve')->with('encrypted-value')->willReturn('plaintext');

        $chain = new SecretResolverChain([$first, $second]);

        self::assertSame('plaintext', $chain->resolve('encrypted-value'));
    }

    public function testResolveThrowsSecretResolutionExceptionWhenNoResolverMatches(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver->method('supports')->willReturn(false);

        $chain = new SecretResolverChain([$resolver]);

        $this->expectException(SecretResolutionException::class);

        $chain->resolve('unresolvable-value');
    }

    public function testResolveThrowsSecretResolutionExceptionOnEmptyChain(): void
    {
        $chain = new SecretResolverChain();

        $this->expectException(SecretResolutionException::class);

        $chain->resolve('any-value');
    }

    public function testAddResolverReturnsFluent(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);

        $chain  = new SecretResolverChain();
        $result = $chain->addResolver($resolver);

        self::assertInstanceOf(SecretResolverChain::class, $result);
    }

    public function testAddResolverDoesNotMutateOriginalInstance(): void
    {
        /** @var SecretResolverInterface&MockObject $resolver */
        $resolver = $this->createMock(SecretResolverInterface::class);
        $resolver->method('supports')->willReturn(true);

        $original = new SecretResolverChain();
        $extended = $original->addResolver($resolver);

        self::assertFalse($original->supports('value'));
        self::assertTrue($extended->supports('value'));
    }

    public function testAddResolverFluentChaining(): void
    {
        /** @var SecretResolverInterface&MockObject $first */
        $first = $this->createMock(SecretResolverInterface::class);
        $first->method('supports')->willReturn(false);

        /** @var SecretResolverInterface&MockObject $second */
        $second = $this->createMock(SecretResolverInterface::class);
        $second->method('supports')->willReturn(true);
        $second->method('resolve')->willReturn('resolved');

        $chain = (new SecretResolverChain())
            ->addResolver($first)
            ->addResolver($second);

        self::assertTrue($chain->supports('value'));
        self::assertSame('resolved', $chain->resolve('value'));
    }

    public function testResolveUsesFirstMatchingResolverNotAllOfThem(): void
    {
        /** @var SecretResolverInterface&MockObject $first */
        $first = $this->createMock(SecretResolverInterface::class);
        $first->method('supports')->willReturn(true);
        $first->expects(self::once())->method('resolve')->willReturn('first-result');

        /** @var SecretResolverInterface&MockObject $second */
        $second = $this->createMock(SecretResolverInterface::class);
        $second->method('supports')->willReturn(true);
        $second->expects(self::never())->method('resolve');

        $chain = new SecretResolverChain([$first, $second]);

        self::assertSame('first-result', $chain->resolve('some-value'));
    }
}
