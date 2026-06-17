# ksf_FA_Mail — Requirements

## Overview
SMTP mail module for FrontAccounting using PHPMailer ^6.9, with automatic
fallback chain: PHPMailer (SMTP) → FA's `send_email()` → PHP `mail()`.

Supports three tiers of outbound accounts via a hook-based sender system:
**System** (company prefs), **Personal** (per-user `fa_preference_values`),
and **Extensible** (other modules inject senders via hooks).

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
