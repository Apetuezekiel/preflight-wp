# PreFlight

**Automated pre-launch QA scanner for WordPress.** PreFlight runs 30+ checks against a WordPress site and produces a categorized pass/fail report organized by severity — Blockers, Warnings, and Info items. It answers one question: *Is this site ready to go live?*

PreFlight is not a site auditor, a security scanner, or a performance optimizer. It is a focused launch gate: install it, click **Run Scan**, fix the blockers, re-scan to verify, then go live.

## Status

**Phase 1 — In development.** The plugin bootstrap is complete (v0.1.0). Check implementations, the scan runner, and the admin UI are being built on feature branches and will merge to `develop` before the first WordPress.org submission.

## Specification

[BRIEF.md](BRIEF.md) is the canonical product specification. Every implementation decision traces back to a section of it. For questions about scope, architecture, constraints, or design trade-offs, consult the Brief first.

## Local Development

1. Clone the repository:

   ```
   git clone https://github.com/Apetuezekiel/preflight-wp.git
   ```

2. Symlink the plugin directory into your WordPress install:

   ```
   ln -s /path/to/preflight-wp /path/to/wordpress/wp-content/plugins/preflight-wp
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
