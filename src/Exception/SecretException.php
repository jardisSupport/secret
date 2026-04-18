<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Exception;

use JardisSupport\Contract\Secret\SecretResolutionException;

/**
 * Base exception for the secret package.
 *
 * All exceptions thrown by this package extend this class.
 */
class SecretException extends SecretResolutionException
{
}
