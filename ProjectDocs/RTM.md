# Requirements Traceability Matrix — ksf_FA_Mail

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
