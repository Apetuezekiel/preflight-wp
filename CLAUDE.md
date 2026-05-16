# CLAUDE.md — Project guidance for Claude Code

This file is read by Claude Code at session start. It documents project rules
that supplement BRIEF.md. For product decisions, architecture decisions, and
scope decisions, BRIEF.md is the canonical source of truth.

---

## Testing rules

### WordPress database access

- **Never modify `wp_users.user_pass` directly in any database.**
  No `UPDATE wp_users SET user_pass = MD5(...)` or any equivalent raw
  password-hash injection. This was done once (PR #4 session) as an improvised
  shortcut — it is now explicitly forbidden.

- **Use WP-CLI for all authenticated test paths:**
  - `wp user reset-password <login> --skip-email` to reset a password
  - `wp eval-file <script>` to run PHP in the WP context
  - `wp shell` for interactive WP-context PHP
  - `wp option update <key> <value>` / `wp option delete <key>` for option manipulation

- **For unauthenticated scanner tests, prefer a standalone PHP script**
  that `require`s `wp-load.php` directly and calls plugin code:
  ```php
  <?php
  require_once '/path/to/wordpress/wp-load.php';
  $envelope = PreFlight_Core::instance()->get_scanner()->run();
  print_r($envelope);
  ```
  Run with: `php scripts/test-<name>.php` (not committed).

- **Browser-driven authentication via injected credentials is forbidden**
  in test plans. Never manipulate the `wp_users` table as a login shortcut.

### WP-CLI invocation on this machine (Local by Flywheel)

The hello.local site runs under Local's bundled PHP (8.2.x) which requires
the correct MySQL socket. Use this wrapper pattern in all test scripts and
manual commands:

```bash
LOCAL_PHP="/Users/mac/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php"
SOCK="/Users/mac/Library/Application Support/Local/run/eRt_S7u4f/mysql/mysqld.sock"
WP_PATH="/Users/mac/Local Sites/hello/app/public"

"$LOCAL_PHP" -d "mysqli.default_socket=$SOCK" ~/bin/wp \
  --path="$WP_PATH" <command>
```

WP-CLI phar is at `~/bin/wp` (installed without sudo). If the socket path
changes (Local recreates the run directory), update the `SOCK` variable above.

### Test script hygiene

- Test scripts go in `scripts/` at the repo root. **Do not commit them.**
  `scripts/` is gitignored.
- MU-plugins used for testing must be removed from `wp-content/mu-plugins/`
  immediately after verification. Never committed.
- Shell history must not contain password literals. If a password is typed
  in a shell command during a test session, scrub it from `~/.zsh_history`
  and `~/.bash_history` before ending the session.

---

## Code quality reminders (Brief §13.5)

- No `var_dump`, `error_log`, `print_r`, `console.log` in committed code.
- All user-facing strings use `__()` / `_e()` / `esc_html__()` with text
  domain `preflight-wp`.
- Type hints on all params and returns where PHP 7.4 supports them.
  No union types (PHP 8.0+ only) — use `@param`/`@return` docblocks instead.
- `php -l` must pass on every PHP file before any commit.
