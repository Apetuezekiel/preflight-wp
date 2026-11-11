=== Prelaunch Checker ===
Contributors:      ezekielapetu
Tags:              pre-launch, launch checklist, qa, site audit, go live
Requires at least: 6.0
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Pre-launch QA scanner for WordPress. Runs 35 checks and gives you a pass/fail report, sorted by severity, before the site goes live.

== Description ==

Prelaunch Checker answers one question: **is this site ready to go live?**

Most developers and agencies run the same manual pass before every launch: is debug mode off, is the site still hidden from search engines, is the sample content gone, is there a favicon, is HTTPS on, does email actually send. Usually from memory or an old checklist.

Prelaunch Checker automates that pass. Install it, click **Run Scan**, and get a report grouped by category and sorted by severity:

* **Blocker**: breaks something on day one or embarrasses you in front of the client. Fix these before launch.
* **Warning**: the site works, but the issue will cause support requests, SEO damage or risk later. Should be fixed before launch.
* **Info**: best-practice suggestions. Many sites reasonably leave these as they are.

Every failed check comes with a plain-language fix hint that tells you where to go and what to change.

**Check categories in 1.0:**

* WordPress Configuration: default tagline, search engine visibility, debug mode, debug display, exposed debug log, timezone, permalinks, site and home URL alignment, staging hostnames, URL scheme consistency, core auto-updates
* Content and Appearance: favicon, site title, 404 template, published content, sample content
* Security Basics: HTTPS, wp-config backup files in the web root, default table prefix, file editing in the dashboard, readme.html, XML-RPC
* SEO Readiness: sitemap, SEO plugin, Open Graph
* Forms and Communication: contact form plugin, transactional email delivery
* Performance Basics: PHP version, caching plugin, object cache, WordPress version
* Plugin Hygiene: inactive plugins, pending updates, development plugins left active

**Key features:**

* Fully automated, no manual checkboxes
* Everything is detected locally with WordPress APIs: no external API calls, no loopback requests, no data leaves your server
* Scan history: the last 10 scans are kept, with a new, resolved and unchanged comparison against the previous scan
* Turn individual checks off in Settings when they don't apply to a site
* Keyboard accessible admin screens that meet WCAG AA contrast
* Free: every check is included, no upsells, no locked features

Prelaunch Checker is not a security scanner, a performance optimiser or a compliance audit. It is a launch gate: run it when development is done, fix the blockers, re-scan to confirm, then go live.

== Installation ==

1. Upload the `prelaunch-checker` folder to `/wp-content/plugins/`, or install it from **Plugins > Add New**.
2. Activate the plugin on the **Plugins** screen.
3. Go to **Tools > Prelaunch Checker** and click **Run Scan**.

No configuration is needed before the first scan.

== Frequently Asked Questions ==

= Does Prelaunch Checker make any external requests? =

No. All checks run against the local WordPress install using WordPress APIs, option reads, constant checks, file existence checks and plugin detection. Nothing is sent anywhere.

= Does it fix issues automatically? =

No. Prelaunch Checker only detects. Each failed check includes a fix hint, and you make the change yourself. A scanner that also changes your site would be a much bigger risk to run on a client's site.

= Does the scan slow the site down or change anything? =

No. The scan is read-only: it never writes settings, edits files or changes configuration, apart from saving its own results. It runs on demand in a single request and targets under 3 seconds on a site with 60 active plugins.

= Can I turn off checks that don't apply? =

Yes. Go to **Tools > Prelaunch Checker > Settings** and untick any check. Disabled checks are skipped on the next scan.

= What happens if a check fails with an error? =

Each check runs in its own error handler. A check that throws an error is reported as skipped, with the error message, and the rest of the scan carries on. If PHP hits a fatal error, the results collected so far are saved and shown instead of a blank screen.

= Does it work on Multisite? =

It scans the site it is activated on. Scanning every site in a network at once is not supported in this version.

== Screenshots ==

1. Scan results: summary counts by severity, changes since the last scan, and the seven check categories with their issue counts.
2. Results for one category: each failed check says what was found and how to fix it.
3. Scan history: the last 10 scans with blocker, warning and info counts.
4. Settings: turn individual checks on or off for sites where they do not apply.

== Changelog ==

= 1.0.0 =
* First public release.
* 35 checks across 7 categories: WordPress Configuration, Content and Appearance, Security Basics, SEO Readiness, Forms and Communication, Performance Basics, Plugin Hygiene.
* Dashboard, History and Settings tabs under Tools > Prelaunch Checker.
* Scan history: last 10 scans stored locally, with new, resolved and unchanged comparison.
* Accessible interface: WCAG AA contrast and the WAI-ARIA disclosure pattern for collapsible check groups.
* Translation ready, with a .pot template included.

== Privacy ==

Prelaunch Checker does not send any data off your server and does not use cookies or tracking.

It stores two options in your WordPress database: `prelaunch_checker_settings` (which checks you have turned off, plus the optional developer name, email and URL you enter in Settings) and `prelaunch_checker_scan_history` (the results of your last 10 scans). Both are deleted when you uninstall the plugin.
