# Changelog

All notable changes to Prelaunch Checker are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## v1.0.0 — 2026-05-17

Renamed from PreFlight to Prelaunch Checker before the first public release (2026-11-11): display name, slug and text domain `prelaunch-checker`, and all code prefixes. Tested up to WordPress 7.1. The "WP_DEBUG_DISPLAY enabled" check now fails only when WP_DEBUG is also on; WordPress defines WP_DEBUG_DISPLAY as true by default, so it used to flag every default install.

First stable release. 35 checks across 7 categories, admin UI with dashboard/history/settings tabs, scan history with delta comparison, WCAG AA–compliant interface, i18n-ready (.pot included).

### Added

- **WordPress Configuration** (11 checks): debug mode, default tagline, search visibility, staging URL detection, permalink structure, WordPress auto-updates, sample page/post, WordPress address vs. site URL mismatch, admin email placeholder, timezone set, and HTTPS enforcement
- **Content & Appearance** (5 checks): favicon, sample content removed, site not empty, 404 template present, site title set
- **Security Basics** (4 checks): SSL active, backup config files not exposed (`wp-config.php.bak` / `.old`), `readme.html` not publicly accessible, file editing disabled
- **SEO Readiness** (3 checks): XML sitemap plugin active, SEO plugin detected, Open Graph plugin detected
- **Forms & Communication** (2 checks): contact form plugin active, SMTP/email delivery plugin detected
- **Performance Basics** (5 checks): caching plugin active, PHP version ≥ 8.0, WordPress version current, object cache enabled, `WP_DEBUG_LOG` off
- **Plugin Hygiene** (3 checks): no plugins with pending updates, no known dev/debug plugins active (Query Monitor, Debug Bar, WP Crontrol, Log Deprecated Notices, Developer), no inactive plugins installed
- Admin UI — three-tab layout under **Tools → Prelaunch Checker**: Dashboard, History, Settings
- Dashboard: grouped results by category, severity pills (Blocker/Warning/Info/Pass/Skip), collapsible sections with WAI-ARIA disclosure pattern, summary bar with counts, delta bar (new/resolved issues since previous scan), AJAX re-scan with status feedback
- History tab: last 10 scans in a table with blocker/warning/info/pass/skip counts; click any row to drill into that scan; latest scan flagged
- Settings tab: per-check enable/disable toggles preserved across scans; optional developer info (name, email, URL) for future report export
- Historical scan view: banner identifying the viewed scan date; link back to latest; Re-scan button hidden when browsing history
- Scan runner with try/catch per check — failing checks produce a skip result instead of crashing the scan; shutdown handler catches PHP fatals and renders partial results
- `Prelaunch_Checker_Check_Result` value object with `pass()`, `fail($message, $fix_hint)`, `skip($reason)` factory methods
- Delta comparison: new/resolved/unchanged issue sets calculated between the two most recent scans
- `languages/prelaunch-checker.pot` — 180 translatable strings, zero `make-pot` warnings
- WCAG AA color contrast on all severity pills and status indicators (`#007a1f` green = 5.98:1 on white)
- `uninstall.php` — multisite-aware cleanup of `prelaunch_checker_settings` and `prelaunch_checker_scan_history`

### Changed

- Version: 0.1.0 → 1.0.0

---

## v0.1.0 — Plugin bootstrap (unreleased)

This tag establishes the repository structure and boot infrastructure. **The plugin activates cleanly in WordPress but performs no checks.** No scan, no admin UI, no settings page, no results.

### Added

- `prelaunch-checker.php` — plugin main file with WordPress plugin headers, four defined constants (`PRELAUNCH_CHECKER_VERSION`, `PRELAUNCH_CHECKER_FILE`, `PRELAUNCH_CHECKER_PATH`, `PRELAUNCH_CHECKER_URL`), text domain registration on `plugins_loaded`, and boot call to `Prelaunch_Checker_Core::instance()->boot()`
- `includes/interface-check.php` — severity constants (`PRELAUNCH_CHECKER_SEVERITY_BLOCKER`, `PRELAUNCH_CHECKER_SEVERITY_WARNING`, `PRELAUNCH_CHECKER_SEVERITY_INFO`), status constants (`PRELAUNCH_CHECKER_STATUS_PASS`, `PRELAUNCH_CHECKER_STATUS_FAIL`, `PRELAUNCH_CHECKER_STATUS_SKIP`), `Prelaunch_Checker_Check_Result` final value object with factory methods (`pass()`, `fail()`, `skip()`), `Prelaunch_Checker_Check` interface, `Prelaunch_Checker_Check_Category` interface
- `includes/class-prelaunch-checker-core.php` — singleton (`instance()`), category registry (`register_category()`, `get_categories()`), `boot()` firing the `prelaunch_checker_register_categories` action, `get_setting()` reading from the `prelaunch_checker_settings` option
- `uninstall.php` — multisite-aware cleanup deleting `prelaunch_checker_settings` and `prelaunch_checker_scan_history` option rows on plugin removal
- `readme.txt` — WordPress.org format skeleton with data disclosure section (Phase 3 export placeholder)
- Directory scaffolding: `includes/checks/`, `assets/css/`, `assets/js/`, `templates/`, `languages/`

### Not included — implemented in subsequent branches

- Check implementations → `feature/checks-*`
- Scan runner with failure handling → `feature/scanner-core`, `feature/failure-handling`
- Admin UI and results dashboard → `feature/admin-ui`
- Scan history and delta comparison → `feature/scan-history-and-delta`
- `.pot` translation template → `feature/i18n-pot`
- Accessibility audit on admin UI → `feature/accessibility-audit`
