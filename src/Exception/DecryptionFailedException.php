<?php

declare(strict_types=1);

namespace JardisSupport\Secret\Exception;

/**
 * Thrown when AES decryption fails.
 *
 * Examples:
 * - "Failed to base64-decode secret value"
 * - "AES-256-GCM decryption failed — wrong key or corrupted data"
 */
class DecryptionFailedException extends SecretException
{
}
