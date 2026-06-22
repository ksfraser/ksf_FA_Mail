# Requirements Traceability Matrix — ksf_FA_Mail

**Version:** 2.0.0
**Date:** 2026-06-21

| FR ID | Requirement | Source File(s) | Test(s) | UAT |
|-------|-------------|----------------|---------|-----|
| FR-001 | System SMTP config | `mail_setup.php`, `SetupController.php` | `MailerServiceTest` | TC-001–TC-005 |
| FR-002 | PHPMailer ^6.9 | `composer.json`, `MailerService.php` | `MailerServiceTest::testConstructWithSmtpConfig` | — |
| FR-003 | Fallback chain | `MailerService.php` `sendViaFallback()` | `MailerServiceTest::testSendWithoutSmtpFallsBackGracefully` | TC-007 |
| FR-004 | iCal support | `MailerService.php` `sendIcal()`, `includes/mail_service.inc` `ksf_mail_send_ical` | `MailerServiceTest::testSendIcalWithoutSmtpFallsBackGracefully` | TC-006 |
| FR-005 | class.mail.inc | `class.mail.inc` | — | — |
| FR-010 | Personal SMTP | `OutboundAccountService.php`, `hooks.php` | `OutboundAccountServiceTest::testSaveAndGetPersonalConfigRoundTrip` | TC-010 |
| FR-011 | My Mail Settings | `my_mail_setup.php` | — | TC-010 |
| FR-012 | Sender DDL | `OutboundAccountService.php::renderSelector()`, `AttendeesBlockView.php` | `OutboundAccountServiceTest::testRenderSelectorReturnsHtml` | TC-011 |
| FR-013 | Sender resolution | `OutboundAccountService.php::resolveConfig()` | `OutboundAccountServiceTest::testResolveSystemConfigReturnsEmpty` | TC-011 |
| FR-020 | get_available_senders hook | `hooks.php`, `OutboundAccountService.php::getAvailableAccounts()` | — | TC-012 |
| FR-021 | resolve_sender_config hook | `hooks.php`, `OutboundAccountService.php::resolveConfig()` | — | TC-012 |
| FR-022 | mail_send hook | `hooks.php::hook_mail_send()` | — | — |
| FR-030 | Calendar iCal integration | `cal_ical.php`, `EventController.php`, `AttendeesBlockView.php` | — | TC-006 |
| FR-031 | SMTP Test Settings | `SetupController.php::testSettings()`, `mail_setup.php` | — | TC-013 |
| FR-032 | Send Test Email with confirm | `SetupController.php::sendTestEmailTo()`, `mail_setup.php` | — | TC-014 |
| FR-033 | CASL footer | `MailerService.php::buildCaslFooter()` | — | TC-015 |
| FR-034 | Footer user data priority | `MailerService.php::getCurrentUserData()` | — | TC-015 |
| FR-035 | Warning/error/notification display | `mail_setup.php` | — | TC-014 |
| FR-036 | Test recipient default | `mail_setup.php` | — | TC-014 |
| FR-040 | `ksf_mail_accounts` table | `sql/mail_accounts.sql`, `OutboundAccountService.php` | `OutboundAccountServiceTest::testSaveAndGetRoundTrip` | TC-020 |
| FR-041 | Auto-migration from fa_preference_values | `OutboundAccountService.php::migrateFromLegacy()` | `OutboundAccountServiceTest::testMigrateFromLegacy` | TC-021 |
| FR-042 | Personal SMTP + security areas | `hooks.php`, `my_mail_account.php` | `OutboundAccountServiceTest` | TC-010 |
| FR-043 | Sender resolution: system vs personal | `OutboundAccountService.php::resolveConfig()` | `OutboundAccountServiceTest::testResolveSystemConfigReturnsEmpty` | — |
| FR-044 | iCal multipart/alternative + Ical property | `MailerService.php::sendIcal()` | `MailerServiceTest::testSendIcalWithoutSmtpFallsBackGracefully`, `CalIcalMailTest` (Calendar repo) | TC-006 |
| FR-045 | Shared ComposerDependencies::ensure | `hooks.php` bootstrap | `ComposerDependenciesTest` (ksf_FA_Common) | — |
