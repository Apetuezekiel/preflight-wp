# PreFlight — Product Brief v1.2

**Plugin Name:** PreFlight
**WordPress.org Slug (target):** `preflight-wp`
**GitHub Repository:** `Apetuezekiel/preflight-wp`
**Author:** Ezekiel / Zicstack
**Status:** Pre-development
**Distribution:** WordPress.org free plugin directory
**License:** GPL v2+

**Revision history:**
- v1.0 — initial draft
- v1.1 — structural corrections: severity model formalized, theatrical checks removed or deferred, scope contradiction resolved, failure-handling mechanism specified, accessibility and i18n constraints added, audit-artifact framing corrected, success metrics re-anchored
- v1.2 — §13.3 amendment: `feature/failure-handling` collapsed into `feature/scanner-core`. The failure contract (try/catch wrap, shutdown handler, raised limits) is part of the scanner's interface guarantee, not a separable feature

---

## 1. Problem Statement

Every WordPress developer or agency runs the same manual QA pass before launching a site. They check for broken links, missing favicons, default taglines, debug mode left on, missing SSL, orphaned sample content, SEO meta gaps, mixed content warnings, and a dozen other items — usually from memory, a Notion doc, or a browser tab of bookmarked checklist articles.

This process is:

- **Unrepeatable.** Different developers check different things. No two launches get the same rigor.
- **Invisible.** There is no document proving QA was done. When a client reports a post-launch issue that should have been caught, there is no record of what was checked.
- **Time-consuming.** Manual checks across 20–30 items take 30–90 minutes per site, mostly on things a script could detect in seconds.
- **Error-prone.** Fatigue, familiarity bias ("I always set this correctly"), and context-switching between client projects cause misses.

Existing solutions fall into three categories, all with structural gaps:

**Category 1: Full-scope site auditors** (Technical Site Auditor, Health Check & Troubleshooting). These are ongoing monitoring/optimization tools — 200+ checks spanning SEO, performance, security, database health, link analysis. Powerful but overwhelming for the pre-launch use case. A developer about to ship a site doesn't need a database cleanup recommendation or a redirect chain analysis. They need a focused pass on "will this site embarrass me or the client on day one?"

**Category 2: Lightweight checklist plugins** (Launch Check, Launch Checklist, WPAudit.site). These are manual checkbox lists. No automated detection. They digitize the Notion doc but don't eliminate the manual work.

**Category 3: Pre-launch scanners** (LaunchGuard WP). Closest competitor — 50+ automated checks, freemium model. The gap: significant checks gated behind a paid tier, and its focus includes security hardening and performance optimization that overlap with existing security/caching plugins. The pre-launch *workflow* (run scan → review results → fix → re-scan → produce handoff document) is not the product's primary UX.

**The gap PreFlight fills:** A focused, opinionated pre-launch scanner that runs only the checks that matter for "day one readiness," presents results as a pass/fail report the developer can act on (and optionally share with the client as a handoff document), and stores a local scan history so re-runs can show resolved vs new issues. Not a monitoring tool. Not a security plugin. Not a performance optimizer. A launch gate.

---

## 2. Product Definition

PreFlight is a WordPress plugin that runs automated pre-launch checks against a WordPress site and produces a structured pass/fail report. It answers one question: "Is this site ready to go live?"

It is **not** a site auditor, a security scanner, or a performance optimizer. Those tools exist and are better at their jobs. PreFlight is the focused pre-launch pass that sits between "development complete" and "DNS cutover."

### Core Concept

The developer installs PreFlight, clicks "Run Scan," and gets a categorized report of issues organized by severity (blocker, warning, info). Blockers will visibly embarrass the developer or break functionality on day one. Warnings should be fixed before launch but won't cause immediate visible damage. Info items are best-practice suggestions.

The scan is on-demand, not continuous. PreFlight has no cron jobs, no scheduled scans, no background processing at MVP. The developer runs it when they're ready to check.

---

## 3. Feature Scope — MVP

### 3.1 Severity Principle

Every check is graded against this principle. No ad-hoc grading.

- **Blocker** — Breaks functionality on day one OR causes immediate, visible client embarrassment OR exposes data/credentials.
- **Warning** — Should be fixed before launch. Site functions, but the issue creates support load, SEO damage, or follow-on risk.
- **Info** — Best practice, situational, or stylistic. Many sites legitimately leave these as-is.

When grading is ambiguous, default down (Warning → Info), not up. Over-grading erodes trust in the Blocker tier.

### 3.2 Check Categories

All checks run against the current WordPress installation. No external API calls. No loopback HTTP requests at MVP (see Section 5.2 for rationale). All detection is done via WordPress APIs, option reads, file existence checks, class/function existence, and plugin enumeration. Plugin detection is by `class_exists()` / `function_exists()` / `defined()` — never by hard-coded slug or main-file path — so premium variants and forks are detected reliably.

**Category: WordPress Configuration**

| Check | Method | Severity | Notes |
|---|---|---|---|
| Default tagline ("Just another WordPress site") | `get_bloginfo('description')` | Blocker | |
| Search engine visibility disabled | `get_option('blog_public')` | Blocker | |
| Debug mode enabled | `WP_DEBUG` constant | Blocker | |
| Debug display enabled | `WP_DEBUG_DISPLAY` constant | Blocker | Leaks errors to visitors |
| Debug log exposed | File existence on `wp-content/debug.log` + `WP_DEBUG_LOG` | Warning | |
| Incorrect timezone | `get_option('timezone_string')` empty or UTC offset only | Warning | |
| Permalink structure set to plain | `get_option('permalink_structure')` | Warning | |
| Site URL / Home URL mismatch | Compare `site_url()` and `home_url()` schemes/domains | Warning | WP officially supports separate install vs public locations. Suppressible via ignore list. |
| WordPress address on known-staging host | Match `site_url()` against curated host list: `*.wpengine.com`, `*.kinsta.cloud`, `*.flywheelsites.com`, `*.flywheel.local`, `*.pantheonsite.io`, `*.cloudwaysapps.com`, `*.test`, `*.local`, `localhost`, `127.0.0.1` | Blocker | High-confidence staging signals only |
| WordPress address contains generic dev/staging substring | Pattern match for `dev.`, `staging.`, `-staging`, `-dev` not covered by the host list above | Warning | Generic heuristic; false-positive prone. Suppressible. |
| URL scheme consistency | Compare schemes of `site_url()`, `home_url()`, and `WP_CONTENT_URL` constant | Warning | Renamed from "Mixed content risk" — only detects URL-level mismatches, not in-content mixed content. Real mixed-content detection requires loopback (Phase 4). |
| Auto-updates disabled for core | `wp_is_auto_update_enabled('core')` or constant check | Info | |

**Category: Content & Appearance**

| Check | Method | Severity | Notes |
|---|---|---|---|
| Favicon missing | `get_site_icon_url()` returns empty | Blocker | |
| Sample content present | Query for posts/pages with default WP content ("Hello world!", "Sample Page") by title match | Warning | |
| Empty site (zero published posts or pages) | `wp_count_posts()` | Warning | |
| 404 template missing | `locate_template('404.php')` | Warning | Verifies existence only, not quality |
| Missing site title | `get_bloginfo('name')` empty | Blocker | |

**Category: Security Basics**

| Check | Method | Severity | Notes |
|---|---|---|---|
| SSL not active | `is_ssl()` + check `home_url()` scheme | Blocker | |
| `readme.html` accessible | File existence in ABSPATH | Info | Exposes WP version |
| Backup config files in webroot | Check for `wp-config.php.bak`, `wp-config.php.old`, `wp-config.php~`, `wp-config.php.save` in ABSPATH | Warning | |
| XML-RPC enabled | `xmlrpc_enabled` filter | Info | |
| File editing enabled | `DISALLOW_FILE_EDIT` constant | Info | |
| Database table prefix is default `wp_` | `$wpdb->prefix` | Info | |

**Category: SEO Readiness**

| Check | Method | Severity | Notes |
|---|---|---|---|
| Search engine visibility disabled (cross-listed signal) | Covered in WordPress Configuration | — | Single source of truth |
| No sitemap generator detected | Detect SEO plugins by class existence (Yoast: `WPSEO_Options`, Rank Math: `RankMath`, AIOSEO: `AIOSEO\Plugin\AIOSEO`, SEOPress: `SEOPRESS_VERSION`). If none detected and WP version < 5.5, flag. WP 5.5+ has core sitemaps via `wp-sitemap.xml`. | Warning | Detection only — does not verify the sitemap responds. Loopback verification deferred to Phase 4. |
| No SEO plugin active | Same class-existence detection as above | Info | |
| Open Graph plugin absent | Class existence for OG-specific plugins or SEO plugins with OG support | Info | Does not inspect rendered output — Phase 4 |

**Category: Forms & Communication**

| Check | Method | Severity | Notes |
|---|---|---|---|
| Contact form plugin absent | Class existence: `WPCF7`, `GFForms`, `WPForms`, `Ninja_Forms`, `FluentForm\App`, `Formidable_Forms`, Elementor Pro form widget | Info | Not all sites need forms |
| Email delivery not configured | Class existence: `WPMailSMTP\Core`, `FluentMail\App`, `PostmanEmail`, `EasyWPSMTP` | Warning | Default PHP `mail()` is unreliable on most hosts |

**Category: Performance Basics**

| Check | Method | Severity | Notes |
|---|---|---|---|
| No caching plugin active | Class/constant existence: WP Super Cache, W3 Total Cache, LiteSpeed Cache (`LSCWP_V`), WP Rocket (`WP_ROCKET_VERSION`), WP Fastest Cache, Autoptimize | Info | Many hosts provide server-level caching |
| PHP version below 8.0 | `phpversion()` | Warning | PHP 7.4 EOL'd Nov 2022 — production sites on 7.4 carry security liability |
| WordPress not on latest major version | `get_bloginfo('version')` vs core update API | Info | |
| Object caching not active | `wp_using_ext_object_cache()` | Info | |

**Category: Plugin Hygiene**

| Check | Method | Severity | Notes |
|---|---|---|---|
| Inactive plugins present | `get_plugins()` vs `get_option('active_plugins')` diff | Info | Legitimate rotation/seasonal/backup use is common |
| Plugins with available updates | `get_plugin_updates()` (requires `require_once ABSPATH . 'wp-admin/includes/update.php'`) | Warning | |
| Development/debugging plugins active | Class existence: Query Monitor (`QM_Plugin`), Debug Bar, WP Crontrol, Log Deprecated Notices, Developer | Info | Query Monitor is widely used in production by experienced developers — Info, not Warning |

### 3.3 Scan Runner

- Single "Run Scan" button on the plugin's admin page
- Synchronous execution at MVP — all checks run in a single request
- Target total execution time: <3 seconds on a site with 60 active plugins (worst-case for the target audience). Profiling against this benchmark is a release gate.
- No AJAX chunking, no background processing, no WP-Cron at MVP
- Results displayed immediately after scan completes
- Each check returns: `{id, category, label, severity, status: 'pass'|'fail'|'skip', message, fix_hint}`
- `skip` status used when a check cannot run (missing dependency, WP version too old) — never a fatal

### 3.4 Results Dashboard

- Categorized results grouped by category, sorted by severity within each category
- Summary bar: X blockers, Y warnings, Z info items, total checks run, timestamp
- Color coding: red (blocker), amber (warning), blue (info), green (pass) — colors must not be the only signal; severity icons/labels accompany them for accessibility
- Each failed check displays: what was detected, why it matters (one sentence), and an actionable fix hint
- Expand/collapse per category — keyboard accessible (Enter/Space toggles, focus visible)
- "Re-scan" button to run again after fixes

### 3.5 Scan History & Comparison (MVP)

- Last 10 scan results stored in a single option row (`preflight_scan_history`, `autoload=no`)
- Each stored scan: timestamp, summary counts, full results array
- **Scan comparison/delta:** on the results dashboard, show new issues (failed now, passed/skipped before), resolved issues (passed now, failed before), and unchanged
- History view: list of timestamped scans with summary counts, ability to drill into a past scan

This subsection corrects the Section 3.4 / Section 10 contradiction in v1.0. History + delta are MVP. Exportable HTML report is Phase 3.

### 3.6 Settings (v1.0)

- **Enable/disable individual checks** — single toggle per check
- **Developer info** — name, email, URL (included in Phase 3 exported reports; collected at v1.0 so it's available when export ships)

Severity overrides are **deferred to v1.1** pending feedback. v1.0 ships with the fixed severities defined in Section 3.2. Reduces v1.0 settings surface area and avoids a 30+ row override UI at launch.

### 3.7 Handoff Document — Deferred

HTML export of scan results is Phase 3. The deliverable is a handoff document for the developer to share with the client, not an audit artifact. PreFlight makes no claim that the report has compliance, legal, or audit weight: it is an unsigned HTML file generated locally and locally editable. Marketing and readme.txt must reflect this framing.

---

## 4. Feature Scope — Post-MVP

### Phase 2: WooCommerce Checks

Conditional check category that loads only when WooCommerce is active.

| Check | Method | Severity |
|---|---|---|
| Stripe gateway in test mode | `get_option('woocommerce_stripe_settings')['testmode']` | Blocker |
| PayPal (legacy or Payments) in sandbox | `woocommerce_ppec_paypal_settings.environment` and `woocommerce-ppcp-settings.sandbox_on` | Blocker |
| Square in sandbox | Square gateway settings sandbox flag | Blocker |
| Other gateways — test mode unverifiable | Enumerate active gateways via `WC_Payment_Gateways::get_available_payment_gateways()`; for unrecognized gateways, emit Info: "Test/sandbox mode could not be auto-verified — confirm manually" | Info |
| No payment gateway configured | `WC_Payment_Gateways` enumeration with `enabled = yes` filter | Blocker |
| No shipping zones configured | `WC_Shipping_Zones::get_zones()` | Warning |
| Tax not configured (if tax-applicable store) | `wc_tax_enabled()` + tax rate count | Warning |
| Store address incomplete | WooCommerce store address options | Warning |
| Currency not set | `get_woocommerce_currency()` default check | Warning |
| Cart/Checkout/My Account pages missing | WooCommerce page ID options + `get_post_status()` | Blocker |
| No products published | Product post count | Warning |
| Terms & conditions page not set | WooCommerce terms page option | Warning |
| Order notification email not configured | WooCommerce email settings check | Warning |
| Stock management disabled (if physical products) | `get_option('woocommerce_manage_stock')` | Info |

The "test mode" checks cover the three highest-volume gateways. All other gateways fall through to an Info-level "manual verification required" so the brief is not silently making compliance claims it can't keep.

### Phase 3: Handoff Document Export + Severity Overrides

- HTML report generation (static, no JS, no external assets)
- Report header lists exactly what data is included: site URL, WP version, PHP version, active plugin list, inactive plugin list, theme name, developer info if provided
- Download handler with nonce + capability check
- Severity overrides UI — promote/demote individual checks, deferred from v1.0

### Phase 4: Accessibility Basics

| Check | Method | Severity |
|---|---|---|
| Images without alt text | Query attachments referenced in published content, check `_wp_attachment_image_alt` meta | Warning |
| Missing language attribute | `get_bloginfo('language')` + theme `language_attributes()` usage | Warning |
| Missing skip-to-content link | Theme template inspection (best-effort) | Info |

### Phase 5: Deeper Detection (requires loopback or page crawling)

- In-content mixed content scanning (actual page crawl)
- Broken internal links
- Analytics tag verification (inspect rendered `wp_head` output via loopback)
- Open Graph tag verification on rendered output
- Schema.org markup detection
- XML sitemap response verification

### Parked Indefinitely

- Auto-fix capabilities (detection only — see Constraint 1)
- Scheduled/recurring scans (launch tool, not monitoring tool)
- Performance benchmarking (external tools own this)
- Security vulnerability scanning (WPScan, Wordfence, Sucuri own this)
- Multisite network-wide scanning

---

## 5. Technical Architecture

### 5.1 Stack

- **PHP 7.4+** minimum (target PHP 8.0+ patterns where backward-compatible)
- **WordPress 6.0+** minimum supported version
- **No external dependencies** — no Composer, no npm, no external API calls
- **No JavaScript framework** — vanilla JS for results UI interactions
- **Database:** `wp_options` only. Two option rows: `preflight_settings` (`autoload=yes`, small config), `preflight_scan_history` (`autoload=no`, scan results)
- **No custom tables, no custom post types, no cron jobs at MVP**

### 5.2 Architecture Principles

- **No loopback HTTP requests at MVP.** Loopback requests fail on hosts with restricted curl configurations, behind HTTP auth, or where the server can't resolve its own hostname. Every MVP check uses internal WP APIs, option reads, constant checks, file existence checks, or class/function existence. Checks requiring loopback are deferred to Phase 5 and the deferral is stated in the report.
- **No external API calls.** All detection is local. The plugin never phones home, never hits a remote service, never sends site data anywhere.
- **Non-destructive.** The plugin reads state. It never writes options, modifies files, changes configurations, or "fixes" anything. Fix hints are textual instructions, not executable actions. Auto-fix conflates scanning with site modification and introduces a liability surface the plugin will not carry.
- **Performance-bounded.** All checks complete in a single synchronous request. Target: <3 seconds on a 60-active-plugin site. No check performs a database query heavier than `wp_count_posts()`. No check reads more than one file from disk.
- **Idempotent.** Running the scan twice produces the same results if site state is unchanged. No side effects.
- **Graceful degradation, with a defined mechanism:**
  - Each `PreFlight_Check::run()` invocation is wrapped in `try { ... } catch (\Throwable $e) { ... }` by the scanner. Caught throwables return a `skip` result containing the exception class and message.
  - The scanner registers a `register_shutdown_function` callback at scan start that detects `E_ERROR` / `E_PARSE` / `E_COMPILE_ERROR` / `E_USER_ERROR` via `error_get_last()`. On fatal, it persists the partial results collected up to that point and renders a partial-results notice instead of leaving a blank screen.
  - At scan start the scanner calls `wp_raise_memory_limit('admin')` and `@set_time_limit(60)`, each guarded by `function_exists()`.
  - `get_plugin_updates()` requires `require_once ABSPATH . 'wp-admin/includes/update.php'` — the plugin hygiene category loads this explicitly before invocation.

### 5.3 Data Model

```
wp_options:
  preflight_settings (autoload=yes) → native PHP array {
    developer_info: {name, email, url},
    disabled_checks: string[]    // namespaced check IDs to skip entirely
    // severity_overrides: deferred to v1.1
  }
  preflight_scan_history (autoload=no) → native PHP array [
    {
      timestamp: string (ISO 8601),
      wp_version: string,
      php_version: string,
      site_url: string,
      summary: {blockers: int, warnings: int, info: int, passed: int, skipped: int},
      results: [
        {
          id: string,                // namespaced: '{category-id}.{check-slug}', e.g. 'wp-config.default-tagline'
          category: string,          // e.g., 'wp-config'
          label: string,             // human-readable check name
          severity: string,          // 'blocker' | 'warning' | 'info'
          status: string,            // 'pass' | 'fail' | 'skip'
          message: string,           // what was detected
          fix_hint: string           // actionable fix instruction
        }
      ]
    }
    // max 10 entries, oldest pruned on new scan
  ]
```

**Check ID namespacing rule:** every check ID is of the form `{category-id}.{check-slug}` (e.g., `wp-config.default-tagline`, `seo.no-sitemap-generator`). Category prefixes are stable; recategorizing a check requires migrating the ID. This protects history comparison from category restructuring.

### 5.4 File Structure

```
preflight-wp/
├── preflight-wp.php                // Plugin bootstrap, constants, hooks
├── readme.txt                       // WordPress.org readme (includes data-disclosure note)
├── uninstall.php                    // Clean removal: delete both option rows
├── languages/
│   └── preflight-wp.pot             // Generated translation template
├── includes/
│   ├── class-preflight-core.php        // Settings load, category registry, orchestration
│   ├── class-preflight-scanner.php     // Scan runner: try/catch wrap, shutdown handler, history persistence
│   ├── class-preflight-admin.php       // Admin page: settings tab, scan button, results display
│   ├── class-preflight-report.php      // HTML report generation (Phase 3)
│   ├── interface-check.php             // PreFlight_Check + PreFlight_Check_Category interfaces
│   └── checks/                         // One file per check category
│       ├── class-checks-wp-config.php
│       ├── class-checks-content.php
│       ├── class-checks-security.php
│       ├── class-checks-seo.php
│       ├── class-checks-forms.php
│       ├── class-checks-performance.php
│       └── class-checks-plugins.php
├── assets/
│   ├── css/
│   │   ├── admin.css                // Results dashboard styles (WCAG AA contrast)
│   │   └── report.css               // Exported report styles (Phase 3, inlined in HTML export)
│   └── js/
│       └── admin.js                 // Expand/collapse (keyboard-accessible), re-scan AJAX
└── templates/
    ├── results-dashboard.php        // Scan results display template
    └── report-export.php            // HTML report export template (Phase 3)
```

### 5.5 Check Architecture

```php
interface PreFlight_Check_Category {
    public function get_category_id(): string;       // e.g. 'wp-config'
    public function get_category_label(): string;
    public function get_check_ids(): array;          // returns namespaced IDs only — does not instantiate
    public function get_check(string $id): ?PreFlight_Check;  // lazy instantiation
}

interface PreFlight_Check {
    public function get_id(): string;                // namespaced: '{category-id}.{check-slug}'
    public function get_label(): string;
    public function get_default_severity(): string;
    public function run(): PreFlight_Check_Result;
}

// PreFlight_Check_Result is a value object:
// {status: 'pass'|'fail'|'skip', message: string, fix_hint: string}
```

**Lazy instantiation:** category classes are registered at boot. Individual `PreFlight_Check` objects are instantiated only when the scanner executes a scan. The admin page does not instantiate check objects to render the settings list — it reads `get_check_ids()` plus a static metadata map (label, default severity) provided by each category.

### 5.6 Key WordPress Hooks

| Feature | Hook | Notes |
|---|---|---|
| Admin page registration | `admin_menu` | Submenu under Tools |
| Admin page rendering | Callback from `add_submenu_page` | |
| Settings save | `admin_init` + Settings API or manual POST handler with nonce | |
| Report download | `admin_init` with action param + nonce + capability check → headers + output | Phase 3 |
| Re-scan AJAX | `wp_ajax_preflight_rescan` | Nonce + `manage_options` cap check, returns JSON results |
| Text domain load | `plugins_loaded` | `load_plugin_textdomain('preflight-wp', false, dirname(plugin_basename(__FILE__)) . '/languages')` |

---

## 6. WordPress.org Submission Requirements

- **Plugin Check (PCP) compliance threshold:** zero errors, zero warnings on the release commit. Any deviation must be documented in the release notes with justification. PCP version used for pre-release validation is pinned per release tag (recorded in `CHANGELOG.md` and the release PR description).
- No external API calls
- No tracking, analytics, or phone-home
- No upsell nags in admin
- `uninstall.php` removes both option rows
- `readme.txt` follows wordpress.org format and includes a **data disclosure section** listing every piece of data that appears in the exported HTML report (Phase 3): site URL, WP version, PHP version, active plugin list, inactive plugin list, theme name, optional developer info
- All strings use `__()` / `_e()` / `esc_html__()` etc. with text domain `preflight-wp`
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`)
- All input sanitized (`sanitize_text_field`, `absint`, etc.)
- Nonce verification on all form submissions and AJAX
- Capability checks on all admin pages and AJAX handlers (minimum: `manage_options`)
- `.pot` file generated and shipped in `languages/`
- Admin UI passes WCAG AA contrast and is keyboard-navigable (see Constraint 12)

---

## 7. Competitive Landscape

| Plugin | Category | Installs | What It Does | PreFlight's Differentiation |
|---|---|---|---|---|
| Technical Site Auditor | Full-scope site auditor | New | 260+ checks: SEO, performance, security, links, database. Free + premium auto-fix. | Overwhelming for pre-launch. Ongoing monitoring tool, not a launch gate. PreFlight runs ~30 focused checks with a launch-day handoff document. |
| LaunchGuard WP | Pre-launch scanner | New | 50+ checks, freemium, one-click fixes in Pro. | Closest competitor. PreFlight differentiates on: (1) fully free with no gated checks, (2) scan history + comparison in MVP, (3) no auto-fix liability, (4) handoff document framing (not "audit artifact" overreach). |
| Health Check & Troubleshooting | WP core companion | 200k+ | Site health checks, troubleshooting mode. | General site health, not pre-launch focused. No history, no launch-specific checks. |
| Launch Check | Lightweight checklist | 10 | Checks tagline, analytics, search visibility. Last updated 2015. | Abandoned. 4 checks. |
| Launch Checklist | Manual checklist | 20 | Interactive manual checklist with A11y integration. Last updated 2023. | Manual only — no automated detection. |
| WPAudit.site | External checklist | N/A (website) | Browser-based manual checklist. Not a plugin. | Not installable. No automated detection. |

**Positioning:** PreFlight is the only plugin that treats the pre-launch QA pass as a discrete workflow event — run once, get a focused pass/fail result, fix the blockers, re-scan to verify, hand off. Not a monitoring tool. Not a security suite. Not a manual checklist. A launch gate.

**Install count claims:** Competitor install figures are from public wordpress.org listings at the time of this brief revision. Re-verify before publishing any comparative marketing.

---

## 8. Success Metrics

Anchored, attributable metrics only — no aspirational install counts.

- **Plugin Check passes cleanly** (zero errors, zero warnings) on every release tag
- **Zero security vulnerabilities** reported through the wordpress.org submission process or post-release
- **Support response time** under 48 hours on wordpress.org forums for the first 6 months
- **Rating ≥ 4.0 stars** sustained once review count exceeds 10
- **Scan execution time ≤ 3 seconds** on a WordPress install with 60 active plugins (release gate, measured against a fixture site before each tag)
- **Crash-free scans:** zero blank-screen / fatal-error reports in the first 90 days post-release; partial-results fallback (Section 5.2) handles unexpected throwables without user-visible failure

Install-count targets are deferred. They depend on directory placement, search ranking, and review activity that cannot be forecast from a pre-launch position. Revisit after 90 days of public availability with actual data.

---

## 9. Naming & Slug

**Primary:** `preflight-wp`
**Display name:** PreFlight
**Fallback slugs:** `prelaunch-checker`, `launch-check-wp`, `site-readiness`, `wp-preflight`

**Trademark check required before submission.** "PreFlight" is in active use across aviation SaaS, print production (PreFlight by Enfocus — established product), and dev tooling. Trademark exposure for a free GPL wordpress.org plugin is low but non-zero. Run a USPTO TESS search and a Google search for the styled term before submission. If a conflict is identified, fall back to one of the alternatives above. Avoid logo or wordmark styling that mimics any existing PreFlight product.

**Slug availability:** Confirm `https://wordpress.org/plugins/preflight-wp/` returns 404 before submission.

**Search terms to target:** "wordpress pre-launch checklist", "wordpress go live checklist", "wordpress launch audit", "wordpress QA plugin", "wordpress site readiness check", "before launch wordpress".

---

## 10. Development Phases

### Phase 1 — Core Scanner + Results Dashboard + Scan History (MVP / v1.0)

All MVP checks (Section 3.2), scan runner with failure-handling mechanism (Section 5.2), results dashboard, scan history storage (last 10), scan comparison/delta display, settings (enable/disable per check, developer info), re-scan AJAX, uninstall.php, readme.txt, `.pot` generation. **This phase produces a shippable plugin submitted to wordpress.org.**

### Phase 2 — WooCommerce Checks

Conditional check category that loads only when WooCommerce is active. All WooCommerce checks from Section 4. No changes to scanner or admin UI code — just register the new category.

### Phase 3 — Handoff Document Export + Severity Overrides

HTML report generation, download handler, severity override UI.

### Phase 4 — Accessibility Basics

Image alt text audit, language attribute check, skip-to-content detection. Separate check category class.

### Phase 5 — Deeper Detection (Loopback)

Mixed content page crawl, broken link detection, rendered-output inspection (analytics tags, Open Graph, schema.org), XML sitemap response verification. Requires a loopback strategy with explicit failure handling for hosts where loopback is blocked.

### Phase 6 — Polish & Iteration

i18n translation outreach, screenshot updates, readme finalization for major versions, response to user feedback from the first 90 days post-launch.

---

## 11. Constraints & Non-Negotiables

1. **Detection only. Never modification.** The plugin reads site state. It never writes to options, modifies files, changes configurations, installs anything, or "fixes" anything. Structural, not a gap to be filled later.
2. **No external API calls.** All detection is local. No phone-home, no remote service calls, no data leaves the server.
3. **No loopback HTTP requests at MVP.** All MVP checks use internal WordPress APIs. Loopback-dependent checks are deferred to Phase 5.
4. **No cron jobs.** Scans are on-demand only. Launch tool, not monitoring tool.
5. **No JavaScript build toolchain.** Vanilla JS only.
6. **No Composer autoload.** Plugin is self-contained.
7. **Clean uninstall.** Both option rows deleted on uninstall.
8. **No premium/pro version at MVP.** All checks are free. No gated features. Monetization decisions come after traction.
9. **Performance-bounded.** All checks complete in ≤3 seconds on a 60-plugin site. No check performs a database query heavier than `wp_count_posts()`. No check reads more than one file from disk.
10. **Graceful degradation with defined mechanism.** Per-check try/catch returning `skip`, shutdown function for fatals returning partial results, memory/time limits raised at scan start with `function_exists` guards. Specified in Section 5.2.
11. **Extensible check architecture.** New categories added by registering a new class file. No modification to scanner, admin UI, or report code. Lazy instantiation of check objects.
12. **Admin UI accessibility (WCAG AA).** The plugin's own admin pages, settings UI, results dashboard, and exported report (Phase 3) must:
    - Pass WCAG AA contrast for all text and meaningful UI elements
    - Be fully keyboard-navigable with visible focus indicators
    - Use semantic HTML with appropriate ARIA labels for expand/collapse controls and severity indicators
    - Communicate severity through label/icon in addition to color (never color alone)
    - Provide screen-reader-accessible scan progress and result counts
    Phase 4 adds accessibility checks for users' sites; the plugin must meet the same bar it asks others to meet.
13. **The exported report is a handoff document, not an audit artifact.** Marketing, readme.txt, and in-product copy must not claim audit, compliance, or legal weight. The file is unsigned, locally generated, and locally editable.

---

## 12. Toolchain Workflow: Cowork + Claude Code

### 12.1 Role Separation

**Cowork (Reasoning Layer)**

- Owns product decisions, architecture decisions, scope decisions, and trade-off analysis
- Reviews and critiques implementation plans before they reach Claude Code
- Stress-tests feature designs for structural weakness, edge cases, and scope creep
- Approves or rejects proposed changes to the brief, architecture, or constraints
- Handles naming, positioning, competitive analysis, and strategic questions
- Reviews code quality, architecture compliance, and constraint adherence at the PR level
- Ezekiel makes all final decisions; Cowork provides opinionated recommendations

**Claude Code (Implementation Arm)**

- Executes implementation tasks as directed by decisions made in Cowork
- Writes code, creates files, runs tests, builds the plugin
- Does not make product decisions, architecture changes, or scope changes independently
- When encountering an implementation ambiguity, Claude Code flags it and waits for a decision — does not guess
- Follows the brief as source of truth

### 12.2 Workflow

```
1. Ezekiel raises a question or task in Cowork
2. Cowork discusses, analyzes, recommends
3. Ezekiel decides
4. Ezekiel gives Claude Code a clear implementation instruction referencing the decision
5. Claude Code implements, commits, pushes
6. If Claude Code hits an ambiguity → flags to Ezekiel → Ezekiel takes it to Cowork if needed → decision → back to Claude Code
```

### 12.3 Brief as Source of Truth — with Escalation Protocol

This document is the canonical product specification. All implementation traces back to a section of this brief.

**Escalation when implementation discovers a contradiction:** if Claude Code, during implementation, finds that a brief assumption is wrong (an API doesn't behave as specified, a hook fires at a different time, a constant is named differently, a check method is technically impossible as written), the protocol is:

1. Stop work on the affected component
2. Document the discovered contradiction in the PR or commit message (what the brief says, what is actually true, evidence)
3. Flag to Ezekiel
4. Ezekiel takes the contradiction to Cowork
5. Brief is updated to resolve the contradiction
6. Implementation resumes against the updated brief

The brief is updated *before* contradictory code is merged. "Most recent Cowork decision wins" still holds — but the decision must be made and the brief updated, not deferred. Implementation-grounded objections cannot be silently overridden by an older brief assumption.

---

## 13. Git & GitHub Standards

### 13.1 Why This Matters

WordPress.org plugin review includes examining development history. A clean, progressive commit trail demonstrates professional practice. This is also a public portfolio piece — the git history is visible to anyone evaluating the work.

### 13.2 Repository Setup

- **Repository:** `Apetuezekiel/preflight-wp` (public, GitHub)
- **Default branch:** `main` — always deployable, never committed to directly
- **License:** GPL v2 (set in repo creation)
- **.gitignore:** WordPress template (set in repo creation)

### 13.3 Branch Strategy

```
main                          ← always stable, tagged releases only
├── develop                   ← integration branch
│   ├── feature/scanner-core              ← scan orchestrator + failure handling (Brief §5.2)
│   ├── feature/checks-wp-config
│   ├── feature/checks-content
│   ├── feature/checks-security
│   ├── feature/checks-seo
│   ├── feature/checks-forms
│   ├── feature/checks-performance
│   ├── feature/checks-plugins
│   ├── feature/admin-ui
│   ├── feature/scan-history-and-delta   ← MVP per Section 3.5
│   ├── feature/i18n-pot                  ← .pot generation, text domain wiring
│   ├── feature/accessibility-audit       ← WCAG AA pass on admin UI
│   ├── feature/report-export             ← Phase 3
│   ├── feature/severity-overrides        ← Phase 3
│   ├── feature/woocommerce-checks        ← Phase 2
│   └── fix/[issue-description]
```

### 13.4 Branch Rules

- Never commit directly to `main` or `develop`
- One feature branch per logical unit of work
- Branch from `develop`, merge back to `develop`. Only `develop` merges to `main` for releases
- Delete branches after merge

### 13.5 Commit Standards

**Format:**
```
type(scope): short description

[optional body explaining why, not what]
```

**Types:** `feat`, `fix`, `refactor`, `docs`, `style`, `test`, `chore`

**Rules:**
- Atomic commits — one logical change per commit
- No monolithic "initial commit" dumps after the bootstrap
- Commit messages describe intent, not mechanics
- No committed debug code (`var_dump`, `error_log`, `console.log`)
- No committed IDE/editor config (`.vscode/`, `.idea/`, `*.swp` in `.gitignore`)

### 13.6 Merge & PR Process

- Feature branches merge to `develop` via pull request (even solo — the PR is the review artifact)
- PR description references the brief section being implemented
- Squash merge for small branches; regular merge where commit history is meaningful
- `develop` merges to `main` only for tagged releases

### 13.7 Release Tagging

- Semantic versioning: `v1.0.0`, `v1.1.0`, `v1.0.1`
- `v1.0.0` = Phase 1 complete, submitted to wordpress.org
- `v1.1.0` = Phase 2 (WooCommerce)
- `v1.2.0` = Phase 3 (Report export + severity overrides)
- `v1.3.0` = Phase 4 (Accessibility)
- Patch versions for bug fixes
- Each `main` tag corresponds to a wordpress.org release

### 13.8 Instructions for Claude Code

1. Always work on a feature branch — never commit to `main` or `develop`
2. Branch from `develop` using the naming convention above
3. Make progressive commits — not one giant commit at the end
4. Write descriptive commit messages following the format above
5. Push the branch after commits so work is visible on GitHub
6. When a feature is complete, open a PR from the feature branch to `develop` referencing the relevant brief section
7. Do not merge PRs without Ezekiel's approval
8. If a task spans sessions, commit and push at the end of each session
9. If a decision isn't covered by the brief OR the brief contradicts implementation reality (Section 12.3 escalation), commit what you have, document the issue, and flag to Ezekiel — do not guess

---

## 14. Relationship to Handoff WP

PreFlight and Handoff WP are separate plugins with separate audiences and purposes.

| | Handoff WP | PreFlight |
|---|---|---|
| **Audience** | Developers handing off sites to non-technical clients | Any developer shipping a WordPress site |
| **Purpose** | Structure the developer-to-client transition | Verify the site is ready to go live |
| **Mechanism** | Dashboard, help notes, restrictions, manual checklist | Automated scanner with pass/fail results |
| **When used** | After launch, ongoing | Before launch, one-time per launch |

The Handoff WP checklist is a manual to-do list. PreFlight is an automated scanner. They can be used together — run PreFlight to verify, then activate Handoff WP to transition to the client — but neither depends on the other.

If both are active on the same site, no integration is required at MVP. Post-MVP consideration: PreFlight could surface its scan summary on the Handoff WP dashboard. Nice-to-have, not a dependency.
