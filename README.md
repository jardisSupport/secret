# Jardis Secret

![Build Status](https://github.com/jardisSupport/secret/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-95.14%25-brightgreen.svg)](https://github.com/jardisSupport/secret)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Secret resolution for encrypted configuration values. Encrypt secrets with AES-256-GCM or Sodium, store them safely in `.env` files, and decrypt transparently at load time. Plugs into the DotEnv cast chain — no manual decryption calls needed.

---

## Features

- **AES-256-GCM Encryption** — authenticated encryption via OpenSSL; `AesSecretResolver` handles encrypt and decrypt
- **Sodium XSalsa20-Poly1305** — libsodium-based encryption via `SodiumSecretResolver` with explicit `sodium:` prefix
- **DotEnv Integration** — `SecretHandler` plugs directly into `DotEnv::addHandler()` as a prepended cast handler
- **Resolver Chain** — `SecretResolverChain` delegates to the first resolver whose prefix matches the encrypted value
- **Key Providers** — `FileKeyProvider` reads a 32-byte key from a file; `EnvKeyProvider` reads from an environment variable; both auto-detect base64 encoding
- **Makefile Tooling** — `make generate-key-file`, `make encrypt`, and `make encrypt-sodium` for setup and secret rotation
- **Typed Exceptions** — `InvalidKeyException` and `DecryptionFailedException` for precise error handling

---

## Installation

```bash
composer require jardissupport/secret
```

## Quick Start

### 1. Generate a key and encrypt a value

```bash
make generate-key-file                      # Creates support/secret.key
make encrypt VALUE="my-database-password"   # Outputs: secret(base64...)
```

### 2. Store the encrypted value in `.env`

```env
DB_PASSWORD=secret(base64encodedEncryptedValue)
```

### 3. Integrate with DotEnv

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\Secret\Handler\SecretHandler;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;

$dotEnv = new DotEnv();
$dotEnv->addHandler(
    new SecretHandler(new FileKeyProvider('support/secret.key')),
    prepend: true,
);

$config = $dotEnv->loadPrivate('/path/to/app');
// $config['DB_PASSWORD'] → decrypted plaintext, no secret() wrapper
```

## Advanced Usage

```php
use JardisSupport\Secret\Handler\SecretHandler;
use JardisSupport\Secret\Handler\SecretResolverChain;
use JardisSupport\Secret\KeyProvider\EnvKeyProvider;
use JardisSupport\Secret\KeyProvider\FileKeyProvider;
use JardisSupport\Secret\Resolver\AesSecretResolver;
use JardisSupport\Secret\Resolver\SodiumSecretResolver;
use JardisSupport\DotEnv\DotEnv;

// Key from environment variable instead of a file
// EnvKeyProvider auto-detects base64-encoded keys
$keyProvider = new EnvKeyProvider('APP_SECRET_KEY');

// Build a custom resolver chain with explicit ordering
// Sodium resolver matches 'sodium:...' prefix; AES is the catch-all fallback
$chain = (new SecretResolverChain())
    ->addResolver(new SodiumSecretResolver($keyProvider))
    ->addResolver(new AesSecretResolver($keyProvider));

// Encrypt a Sodium value (e.g. in a setup script)
// make encrypt-sodium VALUE="my-api-key"  → secret(sodium:base64...)

// .env with mixed encryption algorithms:
//   DB_PASSWORD=secret(base64AesEncryptedValue)
//   API_KEY=secret(sodium:base64SodiumEncryptedValue)
//   PLAIN=no-encryption-needed

$dotEnv = new DotEnv();
$dotEnv->addHandler(new SecretHandler($keyProvider), prepend: true);

// SecretHandler automatically wires both AES and Sodium resolvers;
// use a manual chain only when you need fine-grained resolver control
$config = $dotEnv->loadPrivate('/path/to/app');

// DB_PASSWORD → AES-decrypted string
// API_KEY     → Sodium-decrypted string
// PLAIN       → 'no-encryption-needed' (passed through unchanged)
```

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/secret](https://docs.jardis.io/en/support/secret)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->
