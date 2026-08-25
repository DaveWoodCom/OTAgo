# Change Log

* **1.1.1 (unreleased)**: Android downloads are now served by a new authenticated `apk.php` (matching `ipa.php`), so the `.apk` no longer has to sit in a publicly accessible folder behind a permanent, shareable URL.
* **Unreleased**: Renamed `$installURLPlacehHolder` / `$ipaURLPlacehHolder` to `$installURLPlaceholder` / `$ipaURLPlaceholder` (typo fix). The old names still work but emit a deprecation notice; update your `configuration.php`.
* **Version 1.1.0**: *(2021/09/04)* - Added support for Android
* **Version 1.0.0**: *(2020/01/26)* - Initial Release
