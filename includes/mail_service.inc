<?php

/**
 * Helper functions for ksf_FA_Mail module.
 *
 * Provides a simple procedural API for sending email from other modules.
 *
 * @package Ksfraser\FA\Mail
 */

if (function_exists('get_company_pref') && !function_exists('get_user_by_login')) {
    $__path = __DIR__ . '/../../../admin/db/users_db.inc';
    if (file_exists($__path)) {
        include_once $__path;
    }
}

/**
 * Send an email using the configured mail service.
 *
 * Tries: PHPMailer (SMTP) → FA's send_email() → PHP mail()
 *
 * @param string $toEmail     Recipient email address.
 * @param string $toName      Recipient display name.
 * @param string $subject     Email subject.
 * @param string $body        Email body (plain text).
 * @param string $fromEmail   Sender email address.
 * @param string $fromName    Sender display name.
 * @param string $accountType Account selector value (system/personal/team:N).
 *                            Empty = system account.
 * @return bool True if the message was accepted for delivery.
 */
function ksf_mail_send(
    string $toEmail,
    string $toName,
    string $subject,
    string $body,
    string $fromEmail,
    string $fromName = '',
    string $accountType = ''
): bool {
    $config = [];
    if ($accountType !== '' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
        $userId = ksf_mail_current_user_id();
        $config = \Ksfraser\FA\Mail\OutboundAccountService::resolveConfig($userId, $accountType);
    }
    $service = new \Ksfraser\FA\Mail\MailerService($config);
    return $service->send($toEmail, $toName, $subject, $body, $fromEmail, $fromName);
}

/**
 * Send an iCal calendar invitation email.
 *
 * @param string $toEmail      TO recipient (usually the organiser).
 * @param string $toName       TO recipient display name.
 * @param string $subject      Email subject.
 * @param string $textBody     Plain text body.
 * @param string $icalContent  iCal VCALENDAR content.
 * @param string $fromEmail    Sender email.
 * @param array  $bccEmails    List of BCC recipient email addresses.
 * @param string $accountType  Account selector value (system/personal/team:N).
 * @return bool True if accepted for delivery.
 */
function ksf_mail_send_ical(
    string $toEmail,
    string $toName,
    string $subject,
    string $textBody,
    string $icalContent,
    string $fromEmail,
    array $bccEmails = [],
    string $accountType = ''
): bool {
    $config = [];
    if ($accountType !== '' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
        $userId = ksf_mail_current_user_id();
        $config = \Ksfraser\FA\Mail\OutboundAccountService::resolveConfig($userId, $accountType);
    }
    $service = new \Ksfraser\FA\Mail\MailerService($config);
    return $service->sendIcal($toEmail, $toName, $subject, $textBody, $icalContent, $fromEmail, $bccEmails);
}

/**
 * Get the current FA user ID.
 *
 * @return string
 */
function ksf_mail_current_user_id(): string
{
    if (isset($_SESSION['wa_current_user']->user)) {
        return (string) $_SESSION['wa_current_user']->user;
    }
    return '';
}
