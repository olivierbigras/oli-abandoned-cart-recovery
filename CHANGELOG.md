# Changelog

## 1.1.7

* Include `languages/` (POT and French translations) in the GitHub / `git archive` ZIP and in the WordPress.org / `build-zip.sh` package
* Declare `Domain Path: /languages` and call `load_plugin_textdomain()` on `init` only on WordPress < 6.7
* Use the `wp_verify_nonce()` result directly in a conditional on the unsubscribe confirmation form (Plugin Check)
* Show a translatable error and the confirmation form again when an unsubscribe POST has an invalid nonce
