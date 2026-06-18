<?php

/**
 * ksf_FA_Mail — Hook handlers for FrontAccounting.
 *
 * Registers:
 *  - "Mail Sending Setup" admin page (System menu)
 *  - "My Mail Settings" user page
 *  - mail_send hook for inter-module email
 *  - ksf_preference_get / ksf_preference_set for personal mail prefs
 *
 * Hook contracts consumed:
 *   get_available_senders(&$accounts, $userId)
 *     — Other modules add sender entries (value, label) to $accounts[]
 *
 *   resolve_sender_config(&$config, $accountType, $userId)
 *     — Other modules return SMTP config array for a non-built-in sender type
 *
 * @package Ksfraser\FA\Mail
 */

// Load Composer autoloader so namespaced classes are available in hook handlers.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// -----------------------------------------------------------------------
// Self-healing — runs at file-inclusion time (session.inc loads hooks.php
// for ALL root-file extensions regardless of active status).
//
// FA's local_extension() hardcodes version '-', and writes path as
// 'modules/<pkg>'.  Both break activation/functionality:
//   - check_src_ext_version('-')    → false  (blocks activation)
//   - path wrong for deploy layout  → hooks.php never loaded
//
// This function fixes both on every page load so activation succeeds and
// hooks.php remains loadable after any future "Local" re-install.
// -----------------------------------------------------------------------
(function () {
    global $path_to_root;

    $paths = [$path_to_root . '/installed_extensions.php'];
    $companyDir = $path_to_root . '/company';
    if (is_dir($companyDir)) {
        foreach (scandir($companyDir) as $comp) {
            if (is_numeric($comp)) {
                $paths[] = $companyDir . '/' . $comp . '/installed_extensions.php';
            }
        }
    }

    // Detect correct relative path for hooks.php discovery.
    // UAT: modules at root level → 'ksf_FA_Mail'
    // Prod: modules in modules/  → 'modules/ksf_FA_Mail'
    $correctPath = 'ksf_FA_Mail';
    if (file_exists($path_to_root . '/modules/ksf_FA_Mail/hooks.php')) {
        $correctPath = 'modules/ksf_FA_Mail';
    }

    foreach ($paths as $file) {
        if (!file_exists($file) || !is_writable($file)) {
            continue;
        }
        $next_extension_id = null;
        include $file;
        $exts = $installed_extensions ?? [];
        $changed = false;
        foreach ($exts as $k => $ext) {
            if (($ext['package'] ?? '') !== 'ksf_FA_Mail') {
                continue;
            }
            if (($ext['version'] ?? '') === '-') {
                $exts[$k]['version'] = '2.4.0';
                $changed = true;
            }
            if (($ext['path'] ?? '') !== $correctPath) {
                $exts[$k]['path'] = $correctPath;
                $changed = true;
            }
        }
        if ($changed) {
            $content = "<?php\n\n\$installed_extensions = "
                . var_export($exts, true) . ";\n";
            if (isset($next_extension_id)) {
                $content .= "\$next_extension_id = {$next_extension_id};\n";
            }
            file_put_contents($file, $content);
        }
    }
})();

class hooks_ksf_fa_mail extends hooks
{
    public $module_name = 'ksf_FA_Mail';
    public $version = '2.4.0';

    public function install_options($app)
    {
        global $path_to_root;

        switch ($app->id) {
            case 'system':
                $app->add_rapp_function(
                    0,
                    _('Mail Sending Setup'),
                    $path_to_root . '/modules/' . $this->module_name . '/mail_setup.php?',
                    'SA_SETUPCOMPANY',
                    MENU_SETTINGS
                );
                break;
        }
    }

    public function install_tabs($module = null)
    {
        if (!defined('FA_LOGGED_IN') || !FA_LOGGED_IN) {
            return;
        }
        if (!defined('SA_ksf_FA_MailPERSONAL')) {
            define('SA_ksf_FA_MailPERSONAL', true);
        }
    }

    public function hook_mail_send($to, $subject, $body, $headers)
    {
        $service = new \Ksfraser\FA\Mail\MailerService();
        if (!$service->isAvailable()) {
            return null;
        }

        $name = '';
        $email = $to;
        if (preg_match('/^"?(.+?)"?\s*<([^>]+)>$/', $to, $m)) {
            $name = $m[1];
            $email = $m[2];
        }

        $fromEmail = '';
        if (preg_match('/^From:\s*(.+?)$/m', $headers, $m)) {
            $fromEmail = trim($m[1]);
            if (preg_match('/<([^>]+)>/', $fromEmail, $fm)) {
                $fromEmail = $fm[1];
            }
        }

        if ($fromEmail === '') {
            return null;
        }

        return $service->send($email, $name, $subject, $body, $fromEmail);
    }

    /**
     * Send an iCal calendar invitation via the configured mail service.
     *
     * Called by hook_invoke_first('mail_send_ical', $data) from the Calendar
     * module.  Builds a multipart/mixed MIME message with text/plain body and
     * text/calendar (iCal) attachment, adds CASL footer, BCCs all invitees,
     * and sends via SMTP (PHPMailer) or fallback.
     *
     * @param array $data {
     *   string to_email     TO recipient (organiser).
     *   string to_name      TO recipient display name.
     *   string subject      Email subject.
     *   string text_body    Plain text body.
     *   string ical_content iCal VCALENDAR content.
     *   string from_email   Sender email.
     *   array  bcc_emails   List of BCC recipient emails.
     *   string account_type Account selector value (system/personal/…).
     * }
     * @return bool|null True on success, false on failure, null if SMTP not configured.
     */
    public function mail_send_ical($data)
    {
        if (!is_array($data)) {
            return null;
        }

        $config = [];
        $accountType = isset($data['account_type']) ? (string) $data['account_type'] : '';
        if ($accountType !== '' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
            $userId = isset($_SESSION['wa_current_user']->user)
                ? (string) $_SESSION['wa_current_user']->user
                : '';
            if ($userId !== '') {
                $config = \Ksfraser\FA\Mail\OutboundAccountService::resolveConfig($userId, $accountType);
            }
        }

        $service = new \Ksfraser\FA\Mail\MailerService($config);
        if (!$service->isAvailable()) {
            return null;
        }

        return $service->sendIcal(
            isset($data['to_email'])     ? (string) $data['to_email']     : '',
            isset($data['to_name'])      ? (string) $data['to_name']      : '',
            isset($data['subject'])      ? (string) $data['subject']      : '',
            isset($data['text_body'])    ? (string) $data['text_body']    : '',
            isset($data['ical_content']) ? (string) $data['ical_content'] : '',
            isset($data['from_email'])   ? (string) $data['from_email']   : '',
            isset($data['bcc_emails'])   ? (array)  $data['bcc_emails']   : []
        );
    }

    // -----------------------------------------------------------------------
    // Preference hooks – store personal mail prefs in fa_preference_values
    // -----------------------------------------------------------------------

    public function ksf_preference_get(&$payload, $opts = array())
    {
        if (!is_array($payload) || !isset($payload['module_name'], $payload['user_id'], $payload['pref_key'])) {
            return null;
        }
        if ($payload['module_name'] !== 'ksf_FA_Mail') {
            return null;
        }
        $value = $this->readMailPref((string) $payload['user_id'], (string) $payload['pref_key']);
        if ($value !== null) {
            $payload['pref_value'] = $value;
            $payload['handled'] = true;
        }
        return null;
    }

    public function ksf_preference_set(&$payload, $opts = array())
    {
        if (!is_array($payload) || !isset($payload['module_name'], $payload['user_id'], $payload['pref_key'])) {
            return null;
        }
        if ($payload['module_name'] !== 'ksf_FA_Mail') {
            return null;
        }
        $this->writeMailPref(
            (string) $payload['user_id'],
            (string) $payload['pref_key'],
            array_key_exists('pref_value', $payload) ? $payload['pref_value'] : null
        );
        $payload['handled'] = true;
        return true;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function prefsTable(): string
    {
        return (defined('TB_PREF') ? TB_PREF : '') . 'fa_preference_values';
    }

    private function esc(string $v): string
    {
        return function_exists('db_escape') ? db_escape($v) : addslashes($v);
    }

    private function readMailPref(string $userId, string $prefKey)
    {
        if (!function_exists('db_query')) {
            return null;
        }
        $sql = "SELECT pref_value FROM `" . $this->prefsTable() . "`"
            . " WHERE module_name='ksf_FA_Mail'"
            . " AND user_id='" . $this->esc($userId) . "'"
            . " AND pref_key='" . $this->esc($prefKey) . "'"
            . " LIMIT 1";
        $result = db_query($sql);
        if (!$result || !function_exists('db_fetch_assoc')) {
            return null;
        }
        $row = db_fetch_assoc($result);
        if (!$row || !array_key_exists('pref_value', $row)) {
            return null;
        }
        $decoded = json_decode((string) $row['pref_value'], true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row['pref_value'];
    }

    private function writeMailPref(string $userId, string $prefKey, $prefValue): void
    {
        if (!function_exists('db_query')) {
            return;
        }
        $sql = "INSERT INTO `" . $this->prefsTable() . "`"
            . " (module_name, user_id, pref_key, pref_value) VALUES ("
            . "'ksf_FA_Mail', "
            . "'" . $this->esc($userId) . "', "
            . "'" . $this->esc($prefKey) . "', "
            . "'" . $this->esc(json_encode($prefValue)) . "')"
            . " ON DUPLICATE KEY UPDATE pref_value=VALUES(pref_value)";
        db_query($sql);
    }
}
