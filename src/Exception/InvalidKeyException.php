<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Exception;

/**
 * Thrown when the encryption key is invalid, missing, or of wrong length.
 *
 * Examples:
 * - "AES-256 key must be 32 bytes, got N bytes"
 * - "Environment variable 'APP_SECRET_KEY' is not set or empty"
 * - "Key file '/run/secrets/key' does not exist or is not readable"
 */
class InvalidKeyException extends SecretException
{
}
