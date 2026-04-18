<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Exception;

/**
 * Thrown when encryption fails.
 *
 * Examples:
 * - "AES-256-GCM encryption failed"
 */
class EncryptionFailedException extends SecretException
{
}
