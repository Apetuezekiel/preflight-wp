# Changelog

All notable changes to PreFlight are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## v1.0.0 — 2026-05-17

First stable release. 35 checks across 7 categories, admin UI with dashboard/history/settings tabs, scan history with delta comparison, WCAG AA–compliant interface, i18n-ready (.pot included).

### Added

- **WordPress Configuration** (11 checks): debug mode, default tagline, search visibility, staging URL detection, permalink structure, WordPress auto-updates, sample page/post, WordPress address vs. site URL mismatch, admin email placeholder, timezone set, and HTTPS enforcement
- **Content & Appearance** (5 checks): favicon, sample content removed, site not empty, 404 template present, site title set
- **Security Basics** (4 checks): SSL active, backup config files not exposed (`wp-config.php.bak` / `.old`), `readme.html` not publicly accessible, file editing disabled
- **SEO Readiness** (3 checks): XML sitemap plugin active, SEO plugin detected, Open Graph plugin detected
- **Forms & Communication** (2 checks): contact form plugin active, SMTP/email delivery plugin detected
- **Performance Basics** (5 checks): caching plugin active, PHP version ≥ 8.0, WordPress version current, object cache enabled, `WP_DEBUG_LOG` off
- **Plugin Hygiene** (3 checks): no plugins with pending updates, no known dev/debug plugins active (Query Monitor, Debug Bar, WP Crontrol, Log Deprecated Notices, Developer), no inactive plugins installed
- Admin UI — three-tab layout under **Tools → PreFlight**: Dashboard, History, Settings
- Dashboard: grouped results by category, severity pills (Blocker/Warning/Info/Pass/Skip), collapsible sections with WAI-ARIA disclosure pattern, summary bar with counts, delta bar (new/resolved issues since previous scan), AJAX re-scan with status feedback
- History tab: last 10 scans in a table with blocker/warning/info/pass/skip counts; click any row to drill into that scan; latest scan flagged
- Settings tab: per-check enable/disable toggles preserved across scans; optional developer info (name, email, URL) for future report export
- Historical scan view: banner identifying the viewed scan date; link back to latest; Re-scan button hidden when browsing history
- Scan runner with try/catch per check — failing checks produce a skip result instead of crashing the scan; shutdown handler catches PHP fatals and renders partial results
- `PreFlight_Check_Result` value object with `pass()`, `fail($message, $fix_hint)`, `skip($reason)` factory methods
- Delta comparison: new/resolved/unchanged issue sets calculated between the two most recent scans
- `languages/preflight-wp.pot` — 180 translatable strings, zero `make-pot` warnings
- WCAG AA color contrast on all severity pills and status indicators (`#007a1f` green = 5.98:1 on white)
- `uninstall.php` — multisite-aware cleanup of `preflight_settings` and `preflight_scan_history`

### Changed

- Version: 0.1.0 → 1.0.0

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
