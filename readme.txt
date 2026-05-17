=== PreFlight ===
Contributors:      apetuezekiel
Tags:              pre-launch, qa, checklist, scanner, launch
Requires at least: 6.0
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Automated pre-launch QA scanner for WordPress. Runs 30+ checks and produces a pass/fail report before go-live.

== Description ==

PreFlight answers one question: **Is this site ready to go live?**

Every WordPress developer or agency runs the same manual QA pass before launching a site — checking for broken links, missing favicons, debug mode left on, missing SSL, sample content, and a dozen other items — usually from memory or a Notion document.

PreFlight automates that pass. Install it, click **Run Scan**, and get a categorized pass/fail report organized by severity:

* **Blocker** — Breaks functionality on day one or causes immediate, visible client embarrassment. Fix these before launch.
* **Warning** — Should be fixed before launch. Site functions, but the issue creates support load, SEO damage, or follow-on risk.
* **Info** — Best-practice suggestions. Many sites legitimately leave these as-is.

**Check categories included in v1.0:**

* WordPress Configuration (debug mode, default tagline, search visibility, staging URL detection, permalink structure, and more)
* Content & Appearance (favicon, sample content, empty site, 404 template, missing site title)
* Security Basics (SSL, backup config files exposed, readme.html exposure)
* SEO Readiness (sitemap generator, SEO plugin detection, Open Graph plugin)
* Forms & Communication (contact form plugin, email delivery configuration)
* Performance Basics (caching plugin, PHP version, WordPress version, object cache)
* Plugin Hygiene (inactive plugins, updates available, development plugins active)

**Key features:**

* Fully automated — no manual checkboxes
* All detection uses internal WordPress APIs — no external API calls, no loopback HTTP requests, no data leaves your server
* Scan history: last 10 scans stored locally with a resolved/new/unchanged comparison view
* Free — all checks included, no upsells, no gated features

PreFlight is not a site auditor, a security scanner, or a performance optimizer. It is a focused launch gate: run it when development is complete, fix the blockers, re-scan to verify, then go live.

== Installation ==

1. Upload the `preflight-wp` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Tools &rarr; PreFlight** to run your first scan.

No configuration is required before running the first scan.

== Frequently Asked Questions ==

= Does PreFlight make any external API calls? =

No. All checks run against the local WordPress installation using internal WordPress APIs, option reads, constant checks, file existence checks, and class/function existence detection. No data leaves your server.

= Does PreFlight fix issues automatically? =

No. PreFlight is detection-only. Each failed check includes a plain-language fix hint — the developer applies the fix manually. Auto-fix conflates scanning with site modification and introduces liability this plugin will not carry.

= Can I disable individual checks? =

Yes. Go to **Tools &rarr; PreFlight &rarr; Settings** and toggle any check off. Disabled checks are skipped entirely on the next scan.

= Does the scan affect site performance or modify anything? =

No. The scan is read-only. It reads site state and never writes options, modifies files, or changes configurations. The entire scan runs in a single synchronous request targeting under 3 seconds on a site with 60 active plugins.

= What happens if a check throws an error? =

The scanner wraps each check invocation in a try/catch block. A check that throws an exception returns a skip result (containing the error class and message) rather than crashing the scan. A shutdown handler catches PHP fatals and renders partial results instead of a blank screen.

= Does PreFlight work with WordPress Multisite? =

The plugin scans the site it is activated on. Network-wide scanning across all sites is out of scope for the current version.

== Screenshots ==

1. Scan results dashboard — checks grouped by category and sorted by severity, with pass/fail/skip per check.
2. Settings page — enable or disable individual checks; optional developer info for future report export.

== Changelog ==

= 1.0.0 =
* First stable release. 35 checks across 7 categories (WordPress Configuration, Content & Appearance, Security Basics, SEO Readiness, Forms & Communication, Performance Basics, Plugin Hygiene).
* Admin UI: Dashboard, History, and Settings tabs under Tools → PreFlight.
* Scan history: last 10 scans stored locally with new/resolved/unchanged delta comparison.
* WCAG AA–compliant interface with WAI-ARIA disclosure pattern for collapsible check groups.
* i18n-ready: 180 translatable strings, .pot template included.
* All detection uses internal WordPress APIs — no external HTTP requests, no data leaves your server.

= 0.1.0 =
* Plugin bootstrap: directory structure, interfaces, core singleton, and uninstall handler. Not yet functional — no checks implemented.

== Data Disclosure ==

PreFlight stores scan results locally in your WordPress database (`wp_options` table, key `preflight_scan_history`). No data is transmitted to external servers at any point.

**When the report export feature ships (Phase 3),** the generated HTML file will contain the following data. The report is produced entirely on your server and saved as a local file — it is not uploaded, transmitted, or indexed anywhere by this plugin:

* Site URL
* WordPress version
* PHP version
* Active plugin list
* Inactive plugin list
* Active theme name
* Optional developer info (name, email, URL — provided voluntarily in plugin settings)

All data stays local. Nothing is sent to external servers. The exported report is an unsigned HTML file; it carries no compliance, legal, or audit weight.
