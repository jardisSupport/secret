<?php

declare(strict_types=1);

namespace JardisSupport\Secret\KeyProvider;

use JardisSupport\Secret\Exception\InvalidKeyException;

/**
 * Reads an encryption key from an environment variable.
 *
 * Auto-detects base64 encoding: if the decoded value is exactly 32 bytes, the decoded
 * value is returned. Otherwise, the raw trimmed value is returned.
 */
class EnvKeyProvider
{
    public function __construct(
        private readonly string $envVarName
    ) {
    }

    /**
     * @throws InvalidKeyException
     */
    public function __invoke(): string
    {
        $value = getenv($this->envVarName);

        if ($value === false || $value === '') {
            throw new InvalidKeyException(
                sprintf("Environment variable '%s' is not set or empty", $this->envVarName)
            );
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidKeyException(
                sprintf("Environment variable '%s' is not set or empty", $this->envVarName)
            );
        }

        $decoded = base64_decode($trimmed, strict: true);

        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        return $trimmed;
    }
}
