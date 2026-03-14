# Requirements: jardissupport/secret

This document describes the full implementation plan for `src/`. The infrastructure (composer.json, Makefile, CI, Docker, etc.) is already in place. This document covers what needs to be built.

---

## Overview

`jardissupport/secret` provides transparent secret resolution for PHP applications. Its primary use case is integration with `jardissupport/dotenv`: encrypted values in `.env` files are decrypted at load time via the DotEnv casting chain, so application code never sees ciphertext.

The package follows the same design principles as the rest of the Jardis ecosystem:
- Strict types, PSR-12, PHPStan Level 8
- Invokable classes, chain-of-responsibility, constructor injection
- Custom exception hierarchy
- No global state

---

## Dependency: jardisport/secret

The package depends on `jardisport/secret ^1.0` which provides the port interfaces:

- `SecretResolverInterface` — contract for resolvers:
  - `supports(string $value): bool` — returns true if this resolver can handle the value
  - `resolve(string $value): string` — decrypts/resolves the value, returns plaintext
- `SecretResolutionException` — base exception from the port layer

All exceptions in this package extend `SecretResolutionException`.

---

## Directory Structure

```
src/
├── SecretCaster.php
├── SecretResolverChain.php
├── Resolver/
│   └── AesSecretResolver.php
├── KeyProvider/
│   ├── FileKeyProvider.php
│   └── EnvKeyProvider.php
└── Exception/
    ├── SecretException.php
    ├── InvalidKeyException.php
    └── DecryptionFailedException.php
```

---

## Core Classes

### SecretCaster (`src/SecretCaster.php`)

Invokable class that plugs into the `jardissupport/dotenv` casting chain.

**Responsibilities:**
- Detect the `secret(...)` marker in a string value via regex: `^secret\((.+)\)$`
- If matched: delegate to the injected `SecretResolverInterface` and return the plaintext
- If not matched: return the original value unchanged
- If no resolver is configured (null): return the original value unchanged

**Constructor:**
```php
public function __construct(
    private readonly ?SecretResolverInterface $resolver = null
) {}
```

**Invokable signature:**
```php
public function __invoke(string $value): string
```

**Behaviour:**
1. Match `secret(...)` via regex. Extract the inner value.
2. If no match → return `$value` as-is.
3. If match and `$resolver` is null → return `$value` as-is (passthrough).
4. If match and resolver exists → call `$resolver->resolve($innerValue)` → return plaintext.

**Notes:**
- Returns a `string` — the cast chain continues after `SecretCaster` and may further convert the plaintext (e.g., `"true"` → `bool`, `"42"` → `int`).
- Must be positioned **first** in the `CastTypeHandler` chain.

---

### SecretResolverChain (`src/SecretResolverChain.php`)

Implements `SecretResolverInterface`. Chains multiple resolvers; delegates to the first that supports the value.

**Constructor:**
```php
public function __construct(
    private readonly array $resolvers = []
) {}
```

**Interface methods:**
```php
public function supports(string $value): bool  // true if any resolver supports it
public function resolve(string $value): string  // delegates to first matching resolver
```

**Behaviour:**
- `supports()`: iterates resolvers, returns `true` as soon as one returns `true` from `supports()`.
- `resolve()`: iterates resolvers, calls `resolve()` on the first one where `supports()` returns `true`.
- If no resolver matches in `resolve()`: throw `SecretResolutionException` with a descriptive message.

**Fluent builder method (optional but recommended):**
```php
public function addResolver(SecretResolverInterface $resolver): static
```

---

### AesSecretResolver (`src/Resolver/AesSecretResolver.php`)

Implements `SecretResolverInterface`. Performs AES-256-GCM decryption.

**Encrypted format:**
```
base64( nonce[12 bytes] + ciphertext[variable] + tag[16 bytes] )
```
The nonce, ciphertext, and authentication tag are concatenated in that order before base64-encoding.

**Constructor:**
```php
public function __construct(
    private readonly string|callable $key
) {}
```

The `$key` parameter accepts:
- A raw string key (32 bytes for AES-256)
- A callable (lazy provider) that returns the key string when invoked

**Interface methods:**
```php
public function supports(string $value): bool
public function resolve(string $value): string
```

**Behaviour:**
- `supports()`: returns `true` for any non-empty string (this resolver is a catch-all for AES-encoded values). In a chain, a more specific resolver should precede it if needed.
- `resolve()`:
  1. Resolve the key — call `$this->key` if callable, use directly if string.
  2. Validate key length: must be 32 bytes. Throw `InvalidKeyException` if not.
  3. Base64-decode the value. Throw `DecryptionFailedException` if decoding fails.
  4. Extract nonce (first 12 bytes), tag (last 16 bytes), ciphertext (middle bytes).
  5. Call `openssl_decrypt()` with `aes-256-gcm`, the key, nonce and tag.
  6. If decryption returns `false`: throw `DecryptionFailedException`.
  7. Return the plaintext string.

**Static encrypt helper:**
```php
public static function encrypt(string $plaintext, string $key): string
```

- Validates key length (32 bytes), throws `InvalidKeyException` if invalid.
- Generates a random 12-byte nonce via `random_bytes(12)`.
- Encrypts with `openssl_encrypt()` using `aes-256-gcm`.
- Returns `base64(nonce + ciphertext + tag)`.
- This method is intended for tooling/setup scripts to create encrypted values for `.env` files.

---

### FileKeyProvider (`src/KeyProvider/FileKeyProvider.php`)

Invokable. Reads an encryption key from a file.

**Constructor:**
```php
public function __construct(
    private readonly string $filePath
) {}
```

**Invokable signature:**
```php
public function __invoke(): string
```

**Behaviour:**
1. Check that `$filePath` exists and is readable. Throw `InvalidKeyException` if not.
2. Read file contents via `file_get_contents()`.
3. Trim whitespace from the result.
4. Attempt base64-decode: if the decoded value is exactly 32 bytes, return the decoded value. Otherwise return the raw (trimmed) string.
5. Throw `InvalidKeyException` if the result is empty.

**Usage with AesSecretResolver:**
```php
$keyProvider = new FileKeyProvider('/run/secrets/app_key');
$resolver    = new AesSecretResolver($keyProvider);
```

---

### EnvKeyProvider (`src/KeyProvider/EnvKeyProvider.php`)

Invokable. Reads an encryption key from an environment variable.

**Constructor:**
```php
public function __construct(
    private readonly string $envVarName
) {}
```

**Invokable signature:**
```php
public function __invoke(): string
```

**Behaviour:**
1. Call `getenv($this->envVarName)`. If `false` or empty string: throw `InvalidKeyException` with the variable name.
2. Trim the value.
3. Attempt base64-decode: if decoded value is exactly 32 bytes, return it. Otherwise return raw string.
4. Throw `InvalidKeyException` if the result is empty.

**Usage:**
```php
$keyProvider = new EnvKeyProvider('APP_SECRET_KEY');
$resolver    = new AesSecretResolver($keyProvider);
```

---

## Exception Classes

### SecretException (`src/Exception/SecretException.php`)

Base exception for this package.

```php
class SecretException extends SecretResolutionException {}
```

`SecretResolutionException` comes from `jardisport/secret`.

---

### InvalidKeyException (`src/Exception/InvalidKeyException.php`)

Thrown when the encryption key is invalid, missing, or of wrong length.

```php
class InvalidKeyException extends SecretException {}
```

Should include a descriptive message, e.g.:
- `"AES-256 key must be 32 bytes, got N bytes"`
- `"Environment variable 'APP_SECRET_KEY' is not set or empty"`
- `"Key file '/run/secrets/key' does not exist or is not readable"`

---

### DecryptionFailedException (`src/Exception/DecryptionFailedException.php`)

Thrown when AES decryption fails.

```php
class DecryptionFailedException extends SecretException {}
```

Should include a descriptive message, e.g.:
- `"Failed to base64-decode secret value"`
- `"AES-256-GCM decryption failed — wrong key or corrupted data"`

---

## Integration Pattern

### Setup with jardissupport/dotenv

`SecretCaster` is added to `CastTypeHandler` with `prepend: true` so it runs before all other casters (before variable substitution, before type conversion):

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Casting\CastTypeHandler;
use JardisSupport\Secret\SecretCaster;
use JardisSupport\Secret\SecretResolverChain;
use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\KeyProvider\EnvKeyProvider;

// Key loaded lazily from environment
$keyProvider = new EnvKeyProvider('APP_SECRET_KEY');
$aesResolver = new AesSecretResolver($keyProvider);

// Chain (single resolver in this example)
$chain  = (new SecretResolverChain())->addResolver($aesResolver);
$caster = new SecretCaster($chain);

// Build cast handler with SecretCaster first
$castTypeHandler = new CastTypeHandler();
$castTypeHandler->setCastTypeClass(SecretCaster::class, prepend: true);

// Use with DotEnv
$dotEnv = new DotEnv(castTypeHandler: $castTypeHandler);
$config = $dotEnv->loadPrivate('/path/to/app');
```

**Note:** `setCastTypeClass(SecretCaster::class, prepend: true)` requires that `CastTypeHandler` supports the `prepend` option. Verify the current API in `jardissupport/dotenv`. If not supported, add `SecretCaster` manually as the first element.

### Example .env

```dotenv
# Plaintext (no encryption)
APP_NAME=MyApp

# Encrypted with AES-256-GCM
DB_PASSWORD=secret(base64encodedencryptedvaluehere==)
API_KEY=secret(anotherbase64encodedencryptedvalue==)

# Decrypted values are still processed by the rest of the cast chain
# So this would be decrypted AND then cast to bool:
FEATURE_FLAG=secret(encryptedTrueValue==)
```

### Creating Encrypted Values

Use the static helper to encrypt values for `.env` files:

```php
use JardisSupport\Secret\Resolver\AesSecretResolver;

$key       = random_bytes(32);  // Generate a 32-byte key
$encrypted = AesSecretResolver::encrypt('my-database-password', $key);

echo 'DB_PASSWORD=secret(' . $encrypted . ')';
echo 'APP_SECRET_KEY=' . base64_encode($key);
```

---

## Test Coverage Requirements

Each class must have a corresponding test in `tests/Unit/`:

| Class | Test File |
|-------|-----------|
| `SecretCaster` | `tests/Unit/SecretCasterTest.php` |
| `SecretResolverChain` | `tests/Unit/SecretResolverChainTest.php` |
| `AesSecretResolver` | `tests/Unit/Resolver/AesSecretResolverTest.php` |
| `FileKeyProvider` | `tests/Unit/KeyProvider/FileKeyProviderTest.php` |
| `EnvKeyProvider` | `tests/Unit/KeyProvider/EnvKeyProviderTest.php` |
| `SecretException` | `tests/Unit/Exception/SecretExceptionTest.php` |
| `InvalidKeyException` | `tests/Unit/Exception/InvalidKeyExceptionTest.php` |
| `DecryptionFailedException` | `tests/Unit/Exception/DecryptionFailedExceptionTest.php` |

**Coverage target:** ≥ 90%

### Key test scenarios

**SecretCaster:**
- Value with `secret(...)` marker → delegates to resolver, returns plaintext
- Value without marker → returns unchanged
- No resolver configured → returns unchanged (even with marker)

**AesSecretResolver:**
- Encrypt + decrypt round-trip returns original value
- Wrong key → `DecryptionFailedException`
- Corrupted base64 → `DecryptionFailedException`
- Key too short → `InvalidKeyException`
- Key from callable (lazy loading)

**FileKeyProvider:**
- Valid key file → returns key
- Base64-encoded 32-byte key → decoded correctly
- Missing file → `InvalidKeyException`

**EnvKeyProvider:**
- Set env var → returns key
- Unset env var → `InvalidKeyException`
- Base64-encoded 32-byte key → decoded correctly

**SecretResolverChain:**
- First matching resolver is used
- No matching resolver → `SecretResolutionException`
- `supports()` returns true if any resolver supports

---

## Coding Standards

All classes must follow the same conventions as the rest of the Jardis ecosystem:

- `declare(strict_types=1)` in every file
- PHPStan Level 8 — no errors
- PSR-12 coding standard — no violations
- PHPDoc array annotations: `@param array<int, SecretResolverInterface>`
- Constructor property promotion where applicable
- No mocks in tests except for `SecretResolverInterface` (it is a port/interface)
