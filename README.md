# Prelaunch Checker

**Automated pre-launch QA scanner for WordPress.** Prelaunch Checker runs 30+ checks against a WordPress site and produces a categorized pass/fail report organized by severity — Blockers, Warnings, and Info items. It answers one question: *Is this site ready to go live?*

Prelaunch Checker is not a site auditor, a security scanner, or a performance optimizer. It is a focused launch gate: install it, click **Run Scan**, fix the blockers, re-scan to verify, then go live.

## Status

**Version 1.0.0.** All 35 checks, the scan dashboard, scan history and settings are complete, and the plugin passes WordPress Plugin Check with no errors or warnings. It is being submitted to the WordPress.org plugin directory as Prelaunch Checker (slug `prelaunch-checker`). The project was called PreFlight during development.

## Specification

[BRIEF.md](BRIEF.md) is the canonical product specification. Every implementation decision traces back to a section of it. For questions about scope, architecture, constraints, or design trade-offs, consult the Brief first.

## Local Development

1. Clone the repository:

   ```
   git clone https://github.com/Apetuezekiel/prelaunch-checker.git
   ```

2. Symlink the plugin directory into your WordPress install:

   ```
   ln -s /path/to/prelaunch-checker /path/to/wordpress/wp-content/plugins/prelaunch-checker
   ```

3. Enable debug mode in `wp-config.php` during development:

   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_DISPLAY', true );
   define( 'WP_DEBUG_LOG', true );
   ```

4. Activate the plugin in **Plugins &rarr; Installed Plugins**.

No build step required. No Composer. No npm. The plugin is self-contained PHP with no external dependencies (Brief §5.1, §11.5, §11.6).

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later

## License

GPL v2 or later — see [LICENSE](LICENSE).
