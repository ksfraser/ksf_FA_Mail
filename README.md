# ksf_FA_Mail

SMTP mail module for FrontAccounting using PHPMailer.

## Features
- SMTP email sending via PHPMailer 6.x
- Admin setup page under System → Mail Sending Setup
- Automatic fallback: PHPMailer → FA's `send_email()` → PHP `mail()`
- Inter-module hook for other FA modules to send email
- BCC support for compliance/audit

## Installation

1. Copy the module to `FA_ROOT/modules/ksf_FA_Mail/`
2. Run `composer install` in the module directory
3. Activate the module in FA: Setup → Modules → Installation
4. Go to System → Mail Sending Setup to configure SMTP

## Usage

From any other module:
```php
$result = hook_invoke_first('mail_send', $to, $subject, $body, $headers);
if ($result === null) {
    // Fallback to FA's built-in mail
}
```

## Configuration

| Setting | Description |
|---------|-------------|
| Mail Type | `SMTP` or `PHP's mail function` |
| SMTP Host | SMTP server hostname |
| SMTP Port | SMTP server port (587 for TLS) |
| SMTP Secure | None, TLS, or SSL |
| Username | SMTP authentication username |
| Password | SMTP authentication password |
