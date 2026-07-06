# Changelog — ksf_FA_Mail

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
