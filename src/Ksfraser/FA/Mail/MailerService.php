<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MailerService
{
    private ?PHPMailer $mailer = null;
    private string $charset = 'UTF-8';
    private array $bccEmails = [];

    /**
     * @param array $config Optional account config. Empty = read from company prefs.
     *   Keys: mail_type, smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, bcc_email
     */
    public function __construct(array $config = [])
    {
        $this->init($config);
    }

    private function init(array $config): void
    {
        $mailType = $this->getConfigValue($config, 'mail_type', 'MAIL');

        if ($mailType !== 'SMTP') {
            return;
        }

        $host = $this->getConfigValue($config, 'smtp_host', '');
        if ($host === '') {
            return;
        }

        $bcc = $this->getConfigValue($config, 'bcc_email', '');
        if ($bcc !== '') {
            $this->bccEmails[] = $bcc;
        }

        try {
            $this->mailer = new PHPMailer(true);
            $this->mailer->isSMTP();
            $this->mailer->Host = $host;
            $this->mailer->Port = (int) $this->getConfigValue($config, 'smtp_port', '25');
            $this->mailer->CharSet = $this->charset;

            $secure = $this->getConfigValue($config, 'smtp_secure', 'none');
            if ($secure === 'tls' || $secure === 'ssl') {
                $this->mailer->SMTPSecure = $secure;
            }

            $username = $this->getConfigValue($config, 'smtp_username', '');
            $password = $this->getConfigValue($config, 'smtp_password', '');
            if ($username !== '' && $password !== '') {
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $username;
                $this->mailer->Password = $password;
            }

            $this->mailer->SMTPDebug = 0;
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] PHPMailer init failed: ' . $e->getMessage());
            $this->mailer = null;
        }
    }

    public function isAvailable(): bool
    {
        return $this->mailer !== null;
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        string $fromEmail,
        string $fromName = ''
    ): bool {
        $body .= $this->buildCaslFooter();

        if ($this->mailer !== null) {
            return $this->sendViaPHPMailer($toEmail, $toName, $subject, $body, $fromEmail, $fromName);
        }

        return $this->sendViaFallback($toEmail, $toName, $subject, $body, $fromEmail);
    }

    private function sendViaPHPMailer(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        string $fromEmail,
        string $fromName,
        array $extraBccEmails = []
    ): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();

            $this->mailer->setFrom($fromEmail, $fromName ?: $toName);
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML(false);

            foreach ($this->bccEmails as $bcc) {
                $this->mailer->addBCC($bcc);
            }

            foreach ($extraBccEmails as $bcc) {
                if ($bcc !== '') {
                    $this->mailer->addBCC($bcc);
                }
            }

            $this->mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] PHPMailer send failed: ' . $e->getMessage());
            return $this->sendViaFallback($toEmail, $toName, $subject, $body, $fromEmail);
        }
    }

    private function sendViaFallback(
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        string $fromEmail
    ): bool {
        $to = $toName !== '' ? '"' . addslashes($toName) . '" <' . $toEmail . '>' : $toEmail;

        $headers = 'From: ' . $fromEmail . "\r\n";

        if (function_exists('send_email')) {
            return send_email($to, $subject, $headers, $body);
        }

        return mail($to, $subject, $body, $headers);
    }

    public function sendIcal(
        string $toEmail,
        string $toName,
        string $subject,
        string $textBody,
        string $icalContent,
        string $fromEmail,
        array $bccEmails = []
    ): bool {
        $textBody .= $this->buildCaslFooter();

        $boundary = 'ksf_cal_' . md5(uniqid((string) mt_rand(), true));
        $to       = $toName !== '' ? '"' . addslashes($toName) . '" <' . $toEmail . '>' : $toEmail;

        $headers  = 'From: ' . $fromEmail . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

        if (!empty($bccEmails)) {
            $headers .= 'Bcc: ' . implode(', ', $bccEmails) . "\r\n";
        }

        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/calendar; charset=UTF-8; method=REQUEST' . "\r\n";
        $body .= 'Content-Disposition: attachment; filename="invite.ics"' . "\r\n";
        $body .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
        $body .= chunk_split(base64_encode($icalContent)) . "\r\n";

        $body .= '--' . $boundary . '--' . "\r\n";

        if ($this->mailer !== null) {
            return $this->sendViaPHPMailer($toEmail, $toName, $subject, $body, $fromEmail, '', $bccEmails);
        }

        if (function_exists('send_email')) {
            return send_email($to, $subject, $headers, $body);
        }

        return mail($to, $subject, $body, $headers);
    }

    private function buildCaslFooter(): string
    {
        $parts = [];

        $userName = $_SESSION['wa_current_user']->name ?? '';
        if ($userName !== '') {
            $parts[] = _('Sent by') . ' ' . $userName;
        }

        $userData = $this->getCurrentUserData();

        if (function_exists('get_company_pref')) {
            $coyName = (string) get_company_pref('coy_name');
            if ($coyName !== '') {
                $parts[] = $coyName;
            }

            $address = (string) get_company_pref('postal_address');
            if ($address !== '') {
                $parts[] = str_replace(["\r\n", "\r"], "\n", $address);
            }

            $phone = isset($userData['phone']) && $userData['phone'] !== ''
                ? (string) $userData['phone']
                : (string) get_company_pref('phone');
            if ($phone !== '') {
                $parts[] = _('Phone:') . ' ' . $phone;
            }

            $email = isset($userData['email']) && $userData['email'] !== ''
                ? (string) $userData['email']
                : (string) get_company_pref('email');
            if ($email !== '') {
                $parts[] = _('Email:') . ' ' . $email;
            }
        }

        if (count($parts) === 0) {
            return '';
        }

        return "\n\n-- \n" . implode("\n", $parts) . "\n";
    }

    private function getCurrentUserData(): array
    {
        $loginname = $_SESSION['wa_current_user']->loginname ?? '';
        if ($loginname !== '' && function_exists('get_user_by_login')) {
            $userData = get_user_by_login($loginname);
            if (is_array($userData)) {
                return $userData;
            }
        }
        $email = $_SESSION['wa_current_user']->email ?? '';
        return $email !== '' ? ['email' => $email] : [];
    }

    /**
     * Read a config value. If config is empty (system mode), fall back to company prefs.
     */
    private function getConfigValue(array $config, string $key, string $default): string
    {
        if (isset($config[$key])) {
            return (string) $config[$key];
        }

        if (function_exists('get_company_pref')) {
            $val = get_company_pref($key);
            if ($val !== null && $val !== false) {
                return (string) $val;
            }
        }

        return $default;
    }
}
