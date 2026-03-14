# Jardis Secret

![Build Status](https://github.com/jardisSupport/secret/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![Coverage](https://img.shields.io/badge/coverage->90%25-brightgreen)](https://github.com/jardisSupport/secret)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

A secret resolution library for PHP applications. Integrates with Jardis DotEnv to decrypt encrypted values in `.env` files transparently — without exposing plaintext secrets in your configuration files.

---

## Features

- **DotEnv Integration** — Plugs into `jardissupport/dotenv` casting chain as a `SecretCaster`
- **AES-256-GCM Encryption** — Industry-standard authenticated encryption
- **Resolver Chain** — Chain multiple resolvers; first matching resolver wins
- **Flexible Key Loading** — Load keys from files or environment variables
- **Protected Contexts** — Works with both `loadPublic()` and `loadPrivate()` from DotEnv
- **Custom Exception Hierarchy** — Typed exceptions for invalid keys and decryption failures

---

## Installation

```bash
composer require jardissupport/secret
```

## Quick Start

```php
use JardisSupport\DotEnv\DotEnv;
use JardisSupport\DotEnv\Casting\CastTypeHandler;
use JardisSupport\Secret\SecretCaster;
use JardisSupport\Secret\Resolver\AesSecretResolver;

// Set up AES resolver with a key
$resolver = new AesSecretResolver('your-32-byte-encryption-key-here');

// Create the caster and inject the resolver
$caster = new SecretCaster($resolver);

// Add SecretCaster first in the cast chain
$castTypeHandler = new CastTypeHandler();
$castTypeHandler->setCastTypeClass(SecretCaster::class, prepend: true);

// Use with DotEnv
$dotEnv = new DotEnv(castTypeHandler: $castTypeHandler);
$config = $dotEnv->loadPrivate('/path/to/app');
// DB_PASSWORD=secret(base64encryptedvalue) → decrypted plaintext
```

## Documentation

Full documentation, examples and API reference:

**→ [jardis.io/docs/support/secret](https://jardis.io/docs/support/secret)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category | Packages |
|----------|----------|
| **Core** | Kernel, Entity, Workflow |
| **Support** | DotEnv, Secret, Cache, Logger, Messaging, DbConnection, DbQuery, DbSchema, Validation, Factory, ClassVersion |
| **Generic** | Auth |
| **Tools** | Builder, Migration, Faker |

**→ [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
