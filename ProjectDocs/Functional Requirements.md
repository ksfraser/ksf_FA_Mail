# Functional Requirements — ksf_FA_Mail

**Version:** 2.0.0
**Date:** 2026-06-21

| ID | Description | Status |
|----|-------------|--------|
| FR-001 | System SMTP configuration admin page | Implemented |
| FR-002 | PHPMailer ^6.9 via Composer | Implemented |
| FR-003 | Fallback chain: PHPMailer → send_email → mail() | Implemented |
| FR-004 | iCal multipart email support | Implemented |
| FR-005 | class.mail.inc drop-in replacement | Implemented |
| FR-010 | Personal SMTP per user in fa_preference_values | Implemented |
| FR-011 | My Mail Settings user page | Implemented |
| FR-012 | Sender DDL in compose UI (hook-extensible) | Implemented |
| FR-013 | Sender resolution: system/personal/hook | Implemented |
| FR-020 | get_available_senders hook contract | Implemented |
| FR-021 | resolve_sender_config hook contract | Implemented |
| FR-022 | mail_send legacy hook | Implemented |
| FR-030 | Calendar iCal send integration with sender DDL | Implemented |
| FR-031 | SMTP Test Settings (auth-only connection test, no email sent) | Implemented |
| FR-032 | Send Test Email with visible recipient field and two-step server-side confirm (CASL) | Implemented |
| FR-033 | CASL footer on all outgoing emails (sender name, company, address, phone, email) | Implemented |
| FR-034 | Footer email/phone resolved from FA users table first, company prefs fallback | Implemented |
| FR-035 | Warning (amber) display for validation messages, error (red) for failures, notification (green) for success | Implemented |
| FR-036 | Test recipient field pre-filled with current user's email from session | Implemented |
| FR-040 | `ksf_mail_accounts` table with `local_part`/`domain` split, SMTP + IMAP columns, `owner_type`/`owner_id` | Implemented |
| FR-041 | Auto-migration from `fa_preference_values` to `ksf_mail_accounts` on first read | Implemented |
| FR-042 | Personal SMTP credentials per user (SA_MAIL_PERSONAL); mail managers SA_MAIL_MANAGE | Implemented |
| FR-043 | Personal credentials override system SMTP (FROM); system SMTP + Reply-To set to Calendar inviter as fallback | Implemented |
| FR-044 | iCal `Ical` property (PHPMailer) + `addStringAttachment` for SMTP path; `multipart/alternative` for fallback | Implemented |
| FR-045 | Shared `ComposerDependencies::ensure()` from `ksf_FA_Common` — no inline bootstrap duplication | Implemented |
