# Change Log

## Version 1.2.0 *(2026/08/25)*

### Security

* **Simple Auth authentication bypass fixed.** Simple Auth accepted any request that supplied an unknown username (`?u=<anything>`) with no `Authorization` header or an empty password, because the loose `==` comparison evaluated `NULL == NULL`. `isValidUser()` now requires the username to exist in `$users` and compares passwords with a timing-safe, byte-exact comparison. **If you ran Simple Auth on 1.1.0 or earlier, assume every build you distributed was publicly downloadable.** (#1)
* Simple Auth tokens are now HMAC-signed (per-deployment secret, `$simpleAuthSecret` or auto-generated), have their age checked every time they are used, and are stored `0600` in a private `tokens/` directory inside the deployment instead of `/tmp`. Tokens for users removed from `$users` are refused and deleted. (#3, #28, #32)
* `?t=` is validated as a UUID before it is used in a filesystem path. (#12)
* `randomUUID()` uses `random_bytes()` only and fails loudly if no CSPRNG is available, instead of silently issuing an empty, shared token. (#14)
* Every response carries `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer`, so install URLs (which carry credentials by design) aren't leaked via the `Referer` header. `Strict-Transport-Security` is deliberately left to the web server. (#16)

### Upgrade notes

* **PHP 8.1 or newer is required** (PHP 7 reached end of life in November 2022). Older interpreters get a clear "requires PHP 8.1" error rather than a parse error. (#11)
* **Outstanding install links stop working once you upgrade** - the token format and location changed - so testers need to sign in once more. The default token directory is now `tokens/` inside the deployment; if you had set `$simpleAuthTempDirectory` to a shared location such as `/tmp`, move it somewhere only the web server user can read. (#3)
* `$installURLPlacehHolder` / `$ipaURLPlacehHolder` are renamed to `$installURLPlaceholder` / `$ipaURLPlaceholder`. The old names still work but emit a deprecation notice; update your `configuration.php`. (#20)
* Custom auth providers should declare `requestAuthentication(): never`, as the bundled providers now do. (#11, #26)
* No configuration changes are required otherwise. A `composer.json` is included for the development tooling only; OTAgo still has no runtime dependencies.

### New

* **Android builds are served through an authenticated `apk.php`** (matching `ipa.php`), so the `.apk` no longer has to sit in a publicly accessible folder behind a permanent, shareable URL. (#2)
* **Resumable, well-formed downloads.** `ipa.php` and `apk.php` stream the build in chunks with `Content-Type`, `Content-Length`, `Content-Disposition` and `Accept-Ranges: bytes`, and honour single-range `Range:` requests with `206`/`416`. Progress bars work, an interrupted install resumes instead of restarting, and a large build no longer has to fit in PHP's `memory_limit`. A missing file is a `404` instead of an empty `200`. (#6)
* **Hashed passwords.** `$users` values may be `password_hash()` digests (bcrypt / argon2), verified with `password_verify()`. Plaintext values keep working; the shipped `configuration.default.php` and the README now use and recommend hashes. (#7)
* **`$trustProxyHeaders`** (default `false`) honours `X-Forwarded-Proto` / `X-Forwarded-Host` behind a TLS-terminating proxy. (#4)
* Simple Auth reads Basic credentials from `HTTP_AUTHORIZATION` / `REDIRECT_HTTP_AUTHORIZATION` when `PHP_AUTH_USER` isn't set, so php-fpm/CGI setups that forward the header via `SetEnvIf` or `RewriteRule` work without a shim. The README documents the nginx and Apache configuration. (#19, #31)
* `OTAGO_VERSION` constant, shown in the web template footer via the new `{{OTAGOVERSION}}` placeholder. (#25)
* A PHPUnit test suite (`composer test`) and a CI workflow running lint and tests on PHP 8.1 through 8.5. (#21, #22)

### Fixed

* `$baseURL` detection no longer depends on the Apache-only `REQUEST_SCHEME`; it also checks `HTTPS` and `SERVER_PORT`, so nginx/php-fpm and PHP's built-in server no longer produce a scheme-less `://host/` install link. `$baseURL` is resolved after `configuration.php` loads, so an explicit override always wins. (#4)
* The port was duplicated in `$baseURL` on non-standard ports (`host:8443:8443`). (#5)
* Requests without a `User-Agent` header no longer raise a warning. (#13)
* Token-directory housekeeping runs on a successful password sign-in rather than on every request, scans only OTAgo's own token files, and treats an unreadable directory as a no-op instead of a fatal error. (#15)
* The `exit()` calls after `requestAuthentication()` are documented as a deliberate backstop for custom auth providers. (#26)

## Version 1.1.0 *(2021/09/04)*

* Added support for Android

## Version 1.0.0 *(2020/01/26)*

* Initial Release
