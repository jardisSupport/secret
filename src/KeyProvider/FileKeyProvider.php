<?php

declare(strict_types=1);

namespace JardisSupport\Secret\KeyProvider;

use JardisSupport\Secret\Exception\InvalidKeyException;

/**
 * Reads an encryption key from a file.
 *
 * Auto-detects base64 encoding: if the decoded value is exactly 32 bytes, the decoded
 * value is returned. Otherwise, the raw trimmed file contents are returned.
 */
class FileKeyProvider
{
    public function __construct(
        private readonly string $filePath
    ) {
    }

    /**
     * @throws InvalidKeyException
     */
    public function __invoke(): string
    {
        if (!file_exists($this->filePath) || !is_readable($this->filePath)) {
            throw new InvalidKeyException(
                sprintf("Key file '%s' does not exist or is not readable", $this->filePath)
            );
        }

        $contents = file_get_contents($this->filePath);

        if ($contents === false) {
            throw new InvalidKeyException(
                sprintf("Key file '%s' does not exist or is not readable", $this->filePath)
            );
        }

        $trimmed = trim($contents);

        if ($trimmed === '') {
            throw new InvalidKeyException(
                sprintf("Key file '%s' does not exist or is not readable", $this->filePath)
            );
        }

        $decoded = base64_decode($trimmed, strict: true);

        if ($decoded !== false && strlen($decoded) === 32) {
            return $decoded;
        }

        return $trimmed;
    }
}
