# jardissupport/secret

Secret resolution for `secret(...)`-markers in `.env` values — decrypts via AES-256-GCM (OpenSSL) or XSalsa20-Poly1305 (Sodium) as a DotEnv cast plugin. Encrypt offline, decrypt at boot, no runtime re-encryption.

## Usage Essentials

- **`SecretHandler` is the recommended entry point:** Wires `SecretResolverChain` (Sodium + AES) + `Secret` caster with a single `callable` key provider. As a DotEnv plugin, always use `addHandler($handler, prepend: true)` — `Secret` must run before `CastStringToValue`, otherwise `${VAR}` substitution and type casting already operate on the encrypted string.
- **Chain order is required:** Register `SodiumSecretResolver` before `AesSecretResolver` in `SecretResolverChain` — AES without prefix is the catch-all fallback (`supports()` matches when no `:` is in the value). Unknown prefixes remain free for future resolvers. `addResolver()` is **immutable** and returns a clone.
- **Encryption format is fixed per resolver:** AES → `[aes:]base64(nonce[12] + ciphertext + tag[16])` (32-byte key, 12-byte nonce). Sodium → `sodium:base64(nonce[24] + ciphertext_with_mac)` (32-byte key, 24-byte nonce, prefix is REQUIRED). Static `encrypt()` helpers are **for tooling/scripts only**, not for runtime calls.
- **Key providers are invokable and lazy:** `FileKeyProvider($path)` reads file contents (auto-detects base64 vs raw), `EnvKeyProvider($varname)` reads `getenv()` (also base64). Alternatively a direct `string` or `callable` (`fn() => file_get_contents('/run/secrets/key')`). **Never** store a key in code/repo/`.env`; `*.key` belongs in `.gitignore`.
- **Caster semantics are deterministic:** `new Secret(?SecretResolverInterface)` → `null`→`null`, `"plain"`→`"plain"` (no marker), `"secret(x)"`→`resolver->resolve("x")`. Without a configured resolver the marker is returned unchanged. The regex is `/^secret\((.+)\)$/`. Decrypted values then pass through the cast chain normally (bool, int, array, `${VAR}`).
- **Exceptions and Contract:** `SecretResolverInterface` (`supports()`/`resolve()`) and the base `SecretResolutionException` come from `jardissupport/contract` — this package throws `SecretException` (extends `SecretResolutionException`) with sub-exceptions `InvalidKeyException` (missing/wrong length/not readable) and `DecryptionFailedException` (Base64 invalid, auth tag wrong). The domain imports **only** the Contract interface, never the package.

## Full Reference

https://docs.jardis.io/en/support/secret
