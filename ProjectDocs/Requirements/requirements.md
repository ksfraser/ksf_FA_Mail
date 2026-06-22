# ksf_FA_Mail — Requirements

**Version:** 2.0.0
**Date:** 2026-06-21

## Overview
SMTP mail module for FrontAccounting using PHPMailer ^6.9, with automatic
fallback chain: PHPMailer (SMTP) → FA's `send_email()` → PHP `mail()`.

Supports three tiers of outbound accounts via a hook-based sender system:
**System** (company prefs), **Personal** (per-user `fa_preference_values`),
and **Extensible** (other modules inject senders via hooks).

All outgoing emails include a CASL-compliant footer.

## Functional Requirements

### FR-001: System SMTP Configuration
An admin setup page (System → Mail Sending Setup) allowing configuration of:
- Mail type (SMTP or PHP mail)
- SMTP host, port, encryption (none/TLS/SSL)
- SMTP username and password
- BCC email address

### FR-002: PHPMailer Integration
Use PHPMailer ^6.9 for SMTP sending via Composer (PSR-4 autoloading).

### FR-003: Fallback Chain
When SMTP is unavailable or fails:
1. FA's `send_email()` function (if available)
2. PHP's `mail()` function

### FR-004: iCal Support
Send multipart emails with `text/calendar` (method=REQUEST) iCal attachments.

### FR-005: System-Wide Mail Class
Provide `class.mail.inc` as a drop-in replacement for
`FA_ROOT/reporting/includes/mail.class.inc`.

### FR-010: Personal SMTP per User
Each FA user may configure their own SMTP server/account/password stored in
`fa_preference_values` (module `ksf_FA_Mail`), accessible via
`ksf_preference_get`/`ksf_preference_set` hooks.

### FR-011: Personal Mail Setup Page
A "My Mail Settings" page where users configure their personal SMTP settings.

### FR-012: Sender Drop-Down (DDL)
When composing an email, a `<select>` dropdown lists all available outbound
accounts: System Account, Personal (if configured), and any entries injected
by other modules via the `get_available_senders` hook.

### FR-013: Sender Resolution
Selected account value is resolved to a MailerService config array.
- `system` → reads company prefs (empty config)
- `personal` → reads `fa_preference_values` for the current user
- Other values → dispatched via `resolve_sender_config` hook

### FR-020: Hook — get_available_senders
Fires `hook_invoke_all('get_available_senders', &$accounts, $userId)`.
Responding modules append `['value' => ..., 'label' => ...]` entries.

### FR-021: Hook — resolve_sender_config
Fires `hook_invoke_all('resolve_sender_config', &$config, $accountType, $userId)`.
Responding modules return a `MailerService`-compatible config array.

### FR-022: Hook — mail_send
Legacy `mail_send` hook for inter-module email (uses system account).

### FR-030: Calendar iCal Integration
The calendar's event modal shows the sender DDL when `OutboundAccountService`
class is detected. Selected account is forwarded through
`EventController::sendInvites()` → `cal_send_ical_invites()` →
`cal_mail_with_ical()` → `ksf_mail_send_ical()`.

### FR-031: SMTP Test Settings
A "Test Settings" button on the admin setup page that connects to the SMTP
server and authenticates (if credentials provided) but sends no email.
Returns green notification on success, red error on failure.

### FR-032: Send Test Email with CASL Confirm
A "Send Test Email" button with a visible "Test recipient" text input field.
Two-step server-side flow:
1. User enters recipient, clicks "Send Test Email"
2. Server validates address, shows amber warning with recipient and CASL notice,
   plus Confirm Send and Cancel buttons
3. User clicks "Confirm Send" → email is actually sent

The from address resolves as: `smtp_username` (if valid email) → current FA
user email → test recipient email (last resort).

### FR-033: CASL Footer
All outgoing emails (via `MailerService::send()` and `sendIcal()`) get a footer
appended with:
- Sender name from FA users table
- Company name from company prefs
- Company postal address from company prefs
- Phone number from FA users table (fallback company prefs)
- Email from FA users table (fallback company prefs)

The footer is built by `MailerService::buildCaslFooter()`.

### FR-034: Footer User Data Priority
Footer email and phone are resolved from the FA users table first (via
`get_user_by_login()` for fresh DB data), with fallback to session email and
company prefs. Avoids stale session values from the FA default installation
(`adm@example.com`).

### FR-035: Display Levels for Test Results
Test email flow differentiates result display:
- Validation messages ("Please enter a valid email") → `display_warning()` (amber)
- Operation failures ("Could not connect", "SMTP test failed") → `display_error()` (red)
- Success → `display_notification()` (green)

### FR-036: Test Recipient Default
The test recipient field on the admin setup page is pre-filled with the current
FA user's email from `$_SESSION['wa_current_user']->email` if available.

---

### FR-040: Centralized Mail Account Storage (v2.0.0)

A dedicated `ksf_mail_accounts` table replaces `fa_preference_values` for storing
outbound email account configuration:

| Column | Type | Purpose |
|--------|------|---------|
| id | INT PK | Unique account identifier |
| owner_type | VARCHAR(32) | Module scope: `fa_user`, `hrm_staff`, `crm_contact`, etc. |
| owner_id | VARCHAR(64) | FK into the owning module's table |
| local_part | VARCHAR(255) | Email local part (before @) |
| domain | VARCHAR(255) | Email domain (after @) |
| smtp_host | VARCHAR(255) | SMTP server hostname |
| smtp_port | INT | SMTP server port |
| smtp_secure | VARCHAR(16) | Encryption: none/TLS/SSL |
| smtp_username | VARCHAR(255) | SMTP auth username (defaults to `local_part@domain`) |
| smtp_password | VARCHAR(512) | SMTP auth password |
| imap_host | VARCHAR(255) | IMAP server hostname (future CRM email import) |
| imap_port | INT | IMAP server port |
| imap_secure | VARCHAR(16) | IMAP encryption |
| imap_username | VARCHAR(255) | IMAP auth username |
| imap_password | VARCHAR(512) | IMAP auth password |
| bcc_email | VARCHAR(255) | Default BCC for outgoing mail |
| created_at | DATETIME | Row creation timestamp |
| updated_at | DATETIME | Last update timestamp |

The `local_part`/`domain` split enables mass domain updates. `OutboundAccountService`
auto-migrates from `fa_preference_values` on first read, and falls back to system
SMTP when no personal account is configured.

### FR-044: iCal MIME Structure (v2.0.0)

For Gmail interactive calendar buttons, the email uses `multipart/alternative` with
an inline `text/calendar; method=REQUEST` part (not `Content-Disposition: attachment`).
The SMTP path additionally provides a downloadable `.ics` via `addStringAttachment`.
The fallback `mail()` path uses `multipart/alternative` only.

### FR-045: Shared ComposerDependencies (v2.0.0)

All KSF modules use `KsfCommon\Utils\ComposerDependencies::ensure(__DIR__)` from
`ksf_FA_Common/src/Utils/` instead of inline `ensure_composer_dependencies()`
functions, reducing code duplication.
