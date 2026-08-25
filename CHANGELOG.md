# Change Log

* **Version 1.1.1 (unreleased)**: **Security fix** - Simple Auth accepted any request that supplied an
  unknown username (`?u=<anything>`) with no `Authorization` header or an empty password, because the
  loose `==` comparison evaluated `NULL == NULL`. `isValidUser()` now requires the username to exist and
  compares passwords with `hash_equals()`, which is byte-exact and timing-safe. Anyone running Simple Auth
  should update; treat any build distributed by an affected install as having been publicly downloadable.
* **Unreleased**: Renamed `$installURLPlacehHolder` / `$ipaURLPlacehHolder` to `$installURLPlaceholder` / `$ipaURLPlaceholder` (typo fix). The old names still work but emit a deprecation notice; update your `configuration.php`.
* **Version 1.1.0**: *(2021/09/04)* - Added support for Android
* **Version 1.0.0**: *(2020/01/26)* - Initial Release
