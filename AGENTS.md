# AGENTS.md - ksf_FA_Mail

## Architecture Overview

SMTP mail module for FrontAccounting using PHPMailer. Supports outbound
accounts via a **hook‑based sender system**:
- **System** — admin-managed, stored in company prefs (built-in)
- **Personal** — per-user SMTP, stored in `fa_preference_values` (built-in)
- **Extensible** — other modules (HRM, CRM) add senders via `get_available_senders`
  and `resolve_sender_config` hooks, using contacts + prefs without coupling

Fallback chain: SMTP → FA's `send_email()` → PHP `mail()`.

## Repository Structure

```
ksf_FA_Mail/
├── composer.json
├── hooks.php              # FA hooks — menu items, mail_send, pref hooks
├── mail_setup.php         # System SMTP configuration (admin)
├── my_mail_setup.php      # Personal SMTP configuration (per-user)
├── class.mail.inc         # Drop-in replacement for FA's mail.class.inc
├── includes/
│   └── mail_service.inc   # Helper functions (ksf_mail_send, ksf_mail_send_ical)
├── src/
│   └── Ksfraser/FA/Mail/
│       ├── MailerService.php            # Core mailer — accepts account config
│       ├── SetupController.php          # System setup page business logic
│       └── OutboundAccountService.php   # Account selector DDL + personal prefs
├── sql/
│   └── preference_values.sql
├── tests/
│   ├── bootstrap.php       # FA function stubs + in-memory pref table
│   └── Unit/
│       ├── MailerServiceTest.php
│       └── OutboundAccountServiceTest.php
├── phpunit.xml
├── ProjectDocs/
└── AGENTS.md
```

## Dependencies
- **phpmailer/phpmailer** ^6.9 — SMTP mail sending
- **ksfraser/exceptions** — shared exception library
- **FrontAccounting 2.4+**

## Account Resolution

1. **System** → `MailerService([])` reads company prefs (`get_company_pref`)
2. **Personal** → `OutboundAccountService::getPersonalConfig($userId)` reads from `fa_preference_values` via `ksf_preference_get`/`ksf_preference_set` hooks (or direct DB fallback)
3. **Other types** (team, department) → resolved via `resolve_sender_config` hook — other modules return SMTP config

The DDL selector (`OutboundAccountService::renderSelector`) fires `get_available_senders`
hook so other modules inject their entries. Built-in: system + personal.

## Integration with ksf_FA_Calendar

The calendar module (`cal_ical.php`, `EventController.php`) passes the
`mail_account_type` POST parameter through the send chain:
- `EventController::sendInvites()` → `cal_send_ical_invites()` → `cal_mail_with_ical()`
- `cal_mail_with_ical()` checks for `ksf_mail_send_ical()` function first (mail module),
  falls back to manual MIME building + FA's send_email/PHP mail

The DDL is rendered in `AttendeesBlockView.php` when `OutboundAccountService` class exists.

## Inter-Module Communication

```php
hook_invoke_first('mail_send', $to, $subject, $body, $headers);

// Direct procedural API (preferred):
ksf_mail_send($toEmail, $toName, $subject, $body, $fromEmail, $fromName, $accountType);
ksf_mail_send_ical($toEmail, $toName, $subject, $textBody, $icalContent, $fromEmail, $accountType);
```

## Development Workflow

1. **Develop** in this repo (feature/fix branches preferred — e.g. `feature/description` or `fix/description`)
2. **Test**: `./vendor/bin/phpunit`
3. **Lint**: `php -l` on modified PHP files
4. **ALWAYS COMMIT** and **PUSH** branch to GitHub (do not wait for user permission)
5. **Merge** to `master` when ready
6. **Push** `master` to GitHub
