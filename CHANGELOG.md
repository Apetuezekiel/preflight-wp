# Changelog

All notable changes to PreFlight are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## v0.1.0 — Plugin bootstrap (unreleased)

This tag establishes the repository structure and boot infrastructure. **The plugin activates cleanly in WordPress but performs no checks.** No scan, no admin UI, no settings page, no results.

### Added

- `preflight-wp.php` — plugin main file with WordPress plugin headers, four defined constants (`PREFLIGHT_VERSION`, `PREFLIGHT_FILE`, `PREFLIGHT_PATH`, `PREFLIGHT_URL`), text domain registration on `plugins_loaded`, and boot call to `PreFlight_Core::instance()->boot()`
- `includes/interface-check.php` — severity constants (`PREFLIGHT_SEVERITY_BLOCKER`, `PREFLIGHT_SEVERITY_WARNING`, `PREFLIGHT_SEVERITY_INFO`), status constants (`PREFLIGHT_STATUS_PASS`, `PREFLIGHT_STATUS_FAIL`, `PREFLIGHT_STATUS_SKIP`), `PreFlight_Check_Result` final value object with factory methods (`pass()`, `fail()`, `skip()`), `PreFlight_Check` interface, `PreFlight_Check_Category` interface
- `includes/class-preflight-core.php` — singleton (`instance()`), category registry (`register_category()`, `get_categories()`), `boot()` firing the `preflight_register_categories` action, `get_setting()` reading from the `preflight_settings` option
- `uninstall.php` — multisite-aware cleanup deleting `preflight_settings` and `preflight_scan_history` option rows on plugin removal
- `readme.txt` — WordPress.org format skeleton with data disclosure section (Phase 3 export placeholder)
- Directory scaffolding: `includes/checks/`, `assets/css/`, `assets/js/`, `templates/`, `languages/`

### Not included — implemented in subsequent branches

- Check implementations → `feature/checks-*`
- Scan runner with failure handling → `feature/scanner-core`, `feature/failure-handling`
- Admin UI and results dashboard → `feature/admin-ui`
- Scan history and delta comparison → `feature/scan-history-and-delta`
- `.pot` translation template → `feature/i18n-pot`
- Accessibility audit on admin UI → `feature/accessibility-audit`
