<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

class SetupController
{
    private const PREFS = [
        'mail_type'     => ['type' => 'varchar', 'size' => 10,  'default' => 'MAIL'],
        'smtp_host'     => ['type' => 'varchar', 'size' => 60,  'default' => ''],
        'smtp_port'     => ['type' => 'int',     'size' => 11,  'default' => 25],
        'smtp_secure'   => ['type' => 'varchar', 'size' => 10,  'default' => 'none'],
        'smtp_username' => ['type' => 'varchar', 'size' => 60,  'default' => ''],
        'smtp_password' => ['type' => 'varchar', 'size' => 60,  'default' => ''],
        'bcc_email'     => ['type' => 'varchar', 'size' => 60,  'default' => ''],
    ];

    public function ensureDefaults(): int
    {
        $init = 0;
        foreach (self::PREFS as $name => $cfg) {
            if (!function_exists('get_company_pref') || get_company_pref($name) === null) {
                $this->setPref($name, $cfg['default'], $cfg['type'], $cfg['size']);
                $init++;
            }
        }
        return $init;
    }

    public function getPrefs(): array
    {
        $prefs = [];
        foreach (self::PREFS as $name => $cfg) {
            $prefs[$name] = function_exists('get_company_pref')
                ? (string) get_company_pref($name)
                : (string) $cfg['default'];
        }
        return $prefs;
    }

    public function update(array $data): void
    {
        if (!function_exists('update_company_prefs')) {
            return;
        }
        $keys = array_keys(self::PREFS);
        $payload = [];
        foreach ($keys as $k) {
            $payload[$k] = $data[$k] ?? '';
        }
        update_company_prefs($payload);
    }

    public function validate(array $data): ?string
    {
        if (($data['mail_type'] ?? '') !== 'SMTP') {
            return null;
        }
        if (empty($data['smtp_host'])) {
            return _('The SMTP host must be entered.');
        }
        $port = (int) ($data['smtp_port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            return _('The SMTP port must be between 1 and 65535.');
        }
        return null;
    }

    public function sendTestEmail(array $data): string
    {
        $error = $this->validate($data);
        if ($error !== null) {
            return $error;
        }

        $userEmail = $this->getCurrentUserEmail();
        if ($userEmail === '') {
            return _('Could not determine your user email address.');
        }

        $mailer = new MailerService($data);
        $ok = $mailer->send(
            $userEmail,
            $userEmail,
            _('Test email from ksf_FA_Mail'),
            _('If you receive this, the SMTP configuration is working correctly.'),
            $userEmail
        );

        return $ok
            ? _('Test email sent successfully to') . ' ' . $userEmail
            : _('Failed to send test email. Check the mail logs for details.');
    }

    private function getCurrentUserEmail(): string
    {
        if (isset($_SESSION['wa_current_user']->email)) {
            return (string) $_SESSION['wa_current_user']->email;
        }
        return '';
    }

    private function setPref(string $name, $value, string $type, int $size): void
    {
        if (!function_exists('set_company_pref')) {
            return;
        }
        set_company_pref($name, 'system.mail', $type, $size, $value);
    }
}
