# Changelog — ksf_FA_Mail

## v2.2.0 — 2026-10-19

### Bug Fixes
- **Preference store repointed at the shared table** — the module read and wrote `TB_PREF . 'fa_preference_values'` (i.e. `0_fa_preference_values`). No release of this module ever created that table: `sql/preference_values.sql` created a table named `fa_preference_values` (no company prefix) and was never wired into `activate_extension()`'s `update_databases()` map. The statements were double-quoted as well (`db_escape()` already wraps values in quotes and the caller added a second pair), so every statement was a SQL error that FrontAccounting's `db_query()` swallowed — reads came back empty and writes went nowhere, silently. Preferences now live in the shared cross-module store `0_ksf_module_prefs`, reached through `ksfraser/ksf-fa-common`'s `ModulePreferencesTrait` (`\Ksfraser\FA\Mail\Preferences`). `hooks.php` no longer registers the `ksf_preference_get` / `ksf_preference_set` responders: the shared `PreferenceRepository` owns that table, and re-implementing it inside the hook handler would either duplicate the SQL or recurse into the hook dispatch.
- **`ksf_mail_accounts` schema now carries the company prefix** — `sql/mail_accounts.sql` created `ksf_mail_accounts` without the literal `0_` that `db_import()` replaces, so the table was created unprefixed while the code read `TB_PREF . 'ksf_mail_accounts'`. The module's own store never existed.
- **Removed the legacy `fa_preference_values` migration** — `OutboundAccountService` used to fall back to the legacy preference store when a user had no row in `ksf_mail_accounts`. That store never received a row (see above), so the fallback could only ever return nothing — and because it queried a table that may not exist with an error message, it turned a missing table into a fatal `E_USER_ERROR` page.

### Removed
- `sql/preference_values.sql` — schema for the store that never existed.
- `hooks.php`: `ksf_preference_get()`, `ksf_preference_set()`, `prefsTable()`, `esc()`, `readMailPref()`, `writeMailPref()` — replaced by `getMailPref()` / `setMailPref()`.

### Dependencies
- Added `ksfraser/ksf-fa-common` (`dev-main`) for the shared preference store and `ModulePreferencesTrait`.

### Testing
- 33 tests, 58 assertions. New: `PreferencesTest` (shared-store round trip, module-name segment, no unknown table) and `TableNamingTest` (every table the code addresses is created by the module's schema; schema files carry the `0_` company prefix; the legacy name is gone).

## v2.1.0 — 2026-07-05

### Bug Fixes
- **My Mail Account accessible to all users** — `$page_security` changed from `SA_MAIL_PERSONAL` to `SA_OPEN` so any authenticated user can update their own mail settings without requiring a specific security role. Menu registration in `hooks.php` updated to match.
- **`sendIcal` empty-address guard** — returns `false` early when `toEmail` is empty or lacks `@`, preventing PHPMailer exceptions on invalid recipients.
- **BCC address filtering** — `array_filter` applied to BCC lists in `sendIcal` to silently skip empty or invalid addresses instead of passing them to PHPMailer.

## v2.0.0 — 2026-06-21

### Major Features
- **Centralized Mail Account Storage** — dedicated `ksf_mail_accounts` table (split `local_part`/`domain`, SMTP + IMAP columns, `owner_type`/`owner_id` for multi-module extensibility). `OutboundAccountService` reads/writes new table; auto-migrates from legacy `fa_preference_values` on first read.
- **Personal SMTP Credentials** — per-user SMTP host/port/username/password stored in `ksf_mail_accounts`. `SA_MAIL_PERSONAL` security area for users, `SA_MAIL_MANAGE` for mail managers.
- **iCal Email Delivery with Gmail Button Support** — SMTP path uses PHPMailer `Ical` property + `addStringAttachment` for both interactive calendar buttons and downloadable `.ics`. Fallback path uses `multipart/alternative` with inline `text/calendar; method=REQUEST` part.
- **Shared ComposerDependencies Utility** — `ComposerDependencies::ensure(__DIR__)` adopted from `ksf_FA_Common`, replaces inline bootstrap.

### Enhancements
- `MailerService::sendIcal()` — full CASL footer, BCC support, configurable FROM/Reply-To.
- `hooks.php` — `mail_send_ical` hook handler resolves sender via `OutboundAccountService::resolveConfig()` (system FROM + Reply-To override; personal FROM from resolved config).
- `SS_MAIL = 144 << 8` security section (no conflict with `SS_EMAIL`).
- `FileLogger` (PSR-3) replaces all `error_log()` calls.

### Bug Fixes
- Fixed `sendViaFallback` duplicate method (rebased).
- Fixed `OutboundAccountService::esc()` double-quoting SQL bug — uses `mysqli_real_escape_string()` directly (no `db_escape()` wrapper).

### Testing
- 21 tests, 33 assertions — all passing. Full suite covers `MailerService` (SMTP/fallback/iCal) and `OutboundAccountService` (CRUD, resolution, migration).

### Known Issues
- **Gmail calendar button display quirk**: When an iCal email is first opened in Gmail's reading pane (side panel), the interactive Yes/No/Maybe buttons may not appear immediately. Reloading the email (or opening it in a separate window) causes them to render. This appears to be a Gmail rendering/timing behaviour and is not reproducible via standard MIME validation.
- **SMTP delivery fails for `adm@ksfraser.com` recipient**: The SMTP server rejects this recipient with "No Such User Here" (recipient verification). The fallback `mail()` path delivers successfully. Affects only the `adm@ksfraser.com` mailbox; other recipients work via SMTP.

---

## v1.0.0 — 2026-06-08

- Initial release: PHPMailer SMTP with fallback chain, system SMTP config, sender DDL, iCal attachment support, CASL footer.

## v0.1.0 — 2026-06-05

- Pre-release: basic SMTP sending, FA class.mail.inc drop-in.
