# ksf_FA_Mail — Architecture

**Version:** 2.0.0
**Date:** 2026-06-21

## Package Structure

```
ksf_FA_Mail/
├── composer.json              # Dependencies: phpmailer/phpmailer ^6.9
├── hooks.php                  # FA hooks: menu, mail_send, get_available_senders, resolve_sender_config
├── mail_setup.php             # Admin setup page (System → Mail Sending Setup)
├── my_mail_setup.php          # User personal SMTP page
├── class.mail.inc             # Drop-in replacement for FA's mail.class.inc
├── includes/
│   ├── mail_service.inc       # Procedural helpers (ksf_mail_send, ksf_mail_send_ical)
│   └── mail_hooks_db.inc      # Hook implementations
├── src/
│   └── Ksfraser/FA/Mail/
│       ├── MailerService.php          # Core mailer: PHPMailer → FA send_email → PHP mail
│       ├── SetupController.php        # Admin setup business logic + validation
│       ├── OutboundAccountService.php # Multi-tier account resolution + sender DDL rendering
│       └── PersonalMailSetupController.php  # Personal SMTP page logic
├── sql/
│   └── mail_accounts.sql           # ksf_mail_accounts table DDL (v2.0.0)
├── tests/
│   └── Unit/
│       ├── MailerServiceTest.php
│       └── OutboundAccountServiceTest.php
├── ProjectDocs/
│   ├── Business Requirements.md
│   ├── Functional Requirements.md
│   ├── RTM.md
│   ├── Requirements/
│   │   └── requirements.md
│   ├── Architecture/
│   │   └── architecture.md
│   ├── UAT/
│   │   └── uat_test_cases.md
│   └── uml/
│       └── sequence_send_mail.puml
└── AGENTS.md
```

## Design Decisions

### Why PHPMailer via Composer instead of manual download?
- Version management through `composer.json`
- Automatic autoloading
- Easy updates via `composer update`

### Why ksf_mail_accounts instead of fa_preference_values? (v2.0.0)
Starting in v2.0.0, mail account configuration moved from `fa_preference_values` to a
dedicated `ksf_mail_accounts` table. Benefits:
- Relational structure: single row per account, easy to query all users
- `local_part`/`domain` split enables mass domain updates
- SMTP + IMAP columns in one table — IMAP ready for future CRM email import
- `owner_type`/`owner_id` pattern allows other modules (HRM, CRM) to register accounts

Auto-migration from `fa_preference_values` occurs on first read via
`OutboundAccountService::migrateFromLegacy()`.

### Multi-Tier Sender System
Three tiers of outbound accounts, resolved in order:

1. **System** — company prefs configured by admin on `mail_setup.php`
2. **Personal** — per-user SMTP stored in `ksf_mail_accounts` table (`owner_type='fa_user'`); managed via `my_mail_account.php`
3. **Extensible** — other modules inject senders via `get_available_senders` hook and resolve configs via `resolve_sender_config` hook

The sender dropdown (`OutboundAccountService::renderSelector()`) aggregates all three tiers. Selected value is resolved via `resolveConfig()`.

### Fallback Chain
1. **PHPMailer (SMTP)** — when mail_type=SMTP and host is configured
2. **FA's send_email()** — checks `function_exists('send_email')`
3. **PHP mail()** — ultimate fallback

### CASL Footer
All outgoing emails get an appended footer with identification info:

| Field | Priority 1 | Priority 2 | Priority 3 |
|-------|-----------|-----------|-----------|
| Sender name | FA users table (`real_name`) | — | — |
| Email | FA users table (`email`) | Session email | Company prefs (`email`) |
| Phone | FA users table (`phone`) | — | Company prefs (`phone`) |
| Company name | Company prefs (`coy_name`) | — | — |
| Address | Company prefs (`postal_address`) | — | — |

Data sourced via `get_user_by_login()` (fresh from DB) to avoid stale session values. If the FA users DB function is unavailable, falls back to `$_SESSION['wa_current_user']->email`.

### Test & Verification Buttons
Two no-save test buttons on the admin setup page:

1. **Test Settings** — instantiates PHPMailer, calls `smtpConnect()` + `smtpClose()`.
   **No email is sent.** Returns success/failure of SMTP connection + authentication.

2. **Send Test Email** — Two-step flow:
   - Step 1: User enters recipient address, clicks "Send Test Email"
   - Step 2: Server validates address, shows amber warning with CASL notice + Confirm/Cancel buttons
   - Step 3: User clicks "Confirm Send" → email delivered
   - The from address resolves as: `smtp_username` (if valid email) → current user email → recipient email

Both buttons use `$async='nonajax'` to bypass FA's AJAX form handling.

### Test recipient field
A visible "Test recipient" text input below the action buttons, pre-filled with the current FA user's email (from session). This is the address used for the Send Test Email flow.

### Hook Contracts

#### `get_available_senders`
```php
hook_invoke_all('get_available_senders', [&$accounts, $userId]);
```
Responding modules append `['value' => ..., 'label' => ...]` to `$accounts`.

#### `resolve_sender_config`
```php
hook_invoke_all('resolve_sender_config', [&$config, $accountType, $userId]);
```
Responding modules populate `$config` with `MailerService`-compatible array.

#### `mail_send` (legacy)
```php
hook_invoke_first('mail_send', $to, $subject, $body, $headers);
```
Returns `true` on success, `false` on failure, `null` if SMTP not configured.

### Inter-Module Communication

```
┌─────────────┐     get_available_senders     ┌──────────────────┐
│  ksf_FA_CRM │ ────────────────────────────>  │                  │
│  (contacts) │     resolve_sender_config      │  ksf_FA_Mail     │
│             │ ────────────────────────────>  │  (OutboundAccount│
└─────────────┘                                │   Service)       │
                                               │                  │
┌─────────────┐                                │  MailerService   │
│ ksf_FA_HRM  │                                │                  │
│ (teams)     │                                │  hooks.php       │
│             │                                └──────────────────┘
└─────────────┘
```

### iCal MIME Structure (v2.0.0)

For calendar invitations, the email MIME structure changed from `multipart/mixed` with
`Content-Disposition: attachment` (downloadable `.ics`) to `multipart/alternative` with
inline `text/calendar; method=REQUEST` part. This enables Gmail to show interactive
Yes/No/Maybe buttons.

**SMTP path (PHPMailer):** Uses `Ical` property (places calendar in alternative section)
plus `addStringAttachment` (provides downloadable `.ics`).

**Fallback path (PHP `mail()`):** Uses manual `multipart/alternative` construction in
`MailerService::sendIcal()` (`sendViaFallback()`) and `cal_mail_with_ical()`.

**Known Gmail quirk:** First open in reading pane may not show buttons; reload in separate
window fixes it. The MIME structure is correct per RFC 6047 (iMIP).

### Shared ComposerDependencies (v2.0.0)

All KSF modules now use `KsfCommon\Utils\ComposerDependencies::ensure(__DIR__)` from
`ksf_FA_Common/src/Utils/`. This replaces inline `ensure_composer_dependencies()`
functions, reducing duplication across Calendar, Mail, and RBAC modules.

### UML Diagram
See `ProjectDocs/uml/sequence_send_mail.svg` for the full send-message sequence.
