<?php

/**
 * Mail class for ksf_FA_Mail module.
 *
 * Provides a drop-in replacement for FA's core mail.class.inc
 * with PHPMailer SMTP support.
 *
 * Copy this file to FA_ROOT/reporting/includes/mail.class.inc
 * to make it the system-wide mailer.
 *
 * @package Ksfraser\FA\Mail
 */

require_once __DIR__ . '/../../modules/ksf_FA_Mail/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class email
{
    private ?PHPMailer $phpmailer = null;
    public string $charset = 'UTF-8';

    public function __construct(string $name, string $email)
    {
        $this->initPHPMailer($name, $email);
    }

    private function initPHPMailer(string $name, string $email): void
    {
        $mailType = get_company_pref('mail_type');

        try {
            $this->phpmailer = new PHPMailer(true);
            $this->phpmailer->CharSet = $this->charset;
            $this->phpmailer->setFrom($email, $name);

            $bcc = get_company_pref('bcc_email');
            if ($bcc) {
                $this->phpmailer->addBCC($bcc);
            }

            if ($mailType === 'SMTP') {
                $this->phpmailer->isSMTP();
                $this->phpmailer->Host = get_company_pref('smtp_host');
                $this->phpmailer->Port = (int) get_company_pref('smtp_port');

                $username = get_company_pref('smtp_username');
                $password = get_company_pref('smtp_password');
                if ($username && $password) {
                    $this->phpmailer->SMTPAuth = true;
                    $this->phpmailer->Username = $username;
                    $this->phpmailer->Password = $password;
                }

                $secure = get_company_pref('smtp_secure');
                if ($secure === 'tls' || $secure === 'ssl') {
                    $this->phpmailer->SMTPSecure = $secure;
                }

                $this->phpmailer->SMTPDebug = 0;
                $this->phpmailer->Debugoutput = function ($str, $level) {
                    error_log("[ksf_FA_Mail] PHPMailer debug ($level): $str");
                };
            } else {
                $this->phpmailer->isMail();
            }
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] PHPMailer init: ' . $e->getMessage());
            $this->phpmailer = null;
        }
    }

    private function parseRfc822(string $rfc822): array
    {
        $name = '';
        $email = $rfc822;
        if (preg_match('/^"?(.+?)"?\s*<([^>]+)>$/', $rfc822, $m)) {
            $name = trim($m[1]);
            $email = $m[2];
        }
        return ['name' => $name, 'email' => $email];
    }

    public function to(string $mail): void
    {
        if ($this->phpmailer === null) {
            $this->to[] = $mail;
            return;
        }
        $data = $this->parseRfc822($mail);
        try {
            $this->phpmailer->addAddress($data['email'], $data['name']);
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] addAddress: ' . $e->getMessage());
        }
    }

    public function cc(string $mail): void
    {
        if ($this->phpmailer === null) {
            $this->cc[] = $mail;
            return;
        }
        $data = $this->parseRfc822($mail);
        try {
            $this->phpmailer->addCC($data['email'], $data['name']);
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] addCC: ' . $e->getMessage());
        }
    }

    public function bcc(string $mail): void
    {
        if ($this->phpmailer === null) {
            $this->bcc[] = $mail;
            return;
        }
        $data = $this->parseRfc822($mail);
        try {
            $this->phpmailer->addBCC($data['email'], $data['name']);
        } catch (PHPMailerException $e) {
            error_log('[ksf_FA_Mail] addBCC: ' . $e->getMessage());
        }
    }

    public function subject(string $subject): void
    {
        if ($this->phpmailer !== null) {
            $this->phpmailer->Subject = $subject;
        }
        $this->subject = $subject;
    }

    public function attachment(string $file): void
    {
        if ($this->phpmailer !== null) {
            try {
                $this->phpmailer->addAttachment($file);
            } catch (PHPMailerException $e) {
                error_log('[ksf_FA_Mail] addAttachment: ' . $e->getMessage());
            }
        }
        $this->attachment[] = $file;
    }

    public function text(string $text): void
    {
        if ($this->phpmailer !== null) {
            $this->phpmailer->Body = $text;
            $this->phpmailer->isHTML(false);
        }
        $this->body = $text;
    }

    public function html(string $html): void
    {
        if ($this->phpmailer !== null) {
            $this->phpmailer->msgHTML($html);
        }
        $this->body = $html;
    }

    public function send(): int
    {
        if ($this->phpmailer !== null) {
            try {
                $this->phpmailer->send();
                return 1;
            } catch (PHPMailerException $e) {
                error_log('[ksf_FA_Mail] send error: ' . $this->phpmailer->ErrorInfo);
                // Fall through to legacy send
            }
        }

        return $this->legacySend();
    }

    // ---- Legacy fallback (same as FA's default mail class) ----

    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $attachment = [];
    private string $boundary = '';
    private string $header = '';
    private string $subject = '';
    private string $body = '';

    private function legacySend(): int
    {
        $this->boundary = md5(uniqid((string) mt_rand(), true));

        $max = count($this->cc);
        if ($max > 0) {
            $this->header .= 'Cc: ' . $this->cc[0];
            for ($i = 1; $i < $max; $i++) {
                $this->header .= ', ' . $this->cc[$i];
            }
            $this->header .= "\n";
        }

        $max = count($this->bcc);
        if ($max > 0) {
            $this->header .= 'Bcc: ' . $this->bcc[0];
            for ($i = 1; $i < $max; $i++) {
                $this->header .= ', ' . $this->bcc[$i];
            }
            $this->header .= "\n";
        }

        $ret = 0;
        foreach ($this->to as $mail) {
            if (mail($mail, $this->subject, $this->body, $this->header)) {
                $ret++;
            }
        }
        return $ret;
    }
}
