<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

class OutboundAccountService
{
    public const TYPE_SYSTEM = 'system';
    public const TYPE_PERSONAL = 'personal';

    private const PREFS_TABLE = 'fa_preference_values';
    private const MODULE = 'ksf_FA_Mail';

    /**
     * Get all available outbound accounts for the given FA user.
     *
     * Built-in: system, personal.
     * Extensible via the "get_available_senders" hook – other modules (e.g. HRM)
     * respond with additional sender entries (e.g. team contacts).
     *
     * Each entry has: value (string identifier), label (human-readable).
     */
    public static function getAvailableAccounts(string $userId): array
    {
        $accounts = [];

        $accounts[] = [
            'value' => self::TYPE_SYSTEM,
            'label' => _('System Account'),
        ];

        $personalConfig = self::getPersonalConfig($userId);
        if (!empty($personalConfig['smtp_host'])) {
            $label = _('Personal') . ': ' . ($personalConfig['smtp_username'] ?: _('configured'));
            $accounts[] = [
                'value' => self::TYPE_PERSONAL,
                'label' => $label,
            ];
        } else {
            $accounts[] = [
                'value' => self::TYPE_PERSONAL,
                'label' => _('Personal (not configured)'),
            ];
        }

        // Let other modules add senders (team accounts, department aliases, etc.)
        if (function_exists('hook_invoke_all')) {
            $extra = [];
            hook_invoke_all('get_available_senders', $extra, $userId);
            if (is_array($extra)) {
                foreach ($extra as $sender) {
                    if (isset($sender['value'], $sender['label'])) {
                        $accounts[] = $sender;
                    }
                }
            }
        }

        return $accounts;
    }

    /**
     * Resolve an account type/identifier to a MailerService config array.
     *
     * Built-in: 'system' (empty config → reads company prefs),
     *           'personal' (reads fa_preference_values for $userId).
     * Other values are resolved via the "resolve_sender_config" hook – the
     * responding module returns the SMTP config.
     */
    public static function resolveConfig(string $userId, string $accountType): array
    {
        if ($accountType === self::TYPE_SYSTEM) {
            return [];
        }

        if ($accountType === self::TYPE_PERSONAL) {
            return self::getPersonalConfig($userId);
        }

        // Unknown sender type – ask other modules via hook
        if (function_exists('hook_invoke_all')) {
            $config = null;
            hook_invoke_all('resolve_sender_config', $config, $accountType, $userId);
            if (is_array($config) && !empty($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * Render a <select> dropdown of available outbound accounts.
     */
    public static function renderSelector(string $userId, string $selected = 'system'): string
    {
        $accounts = self::getAvailableAccounts($userId);
        $html = '<select name="mail_account_type">';
        foreach ($accounts as $a) {
            $sel = ((string) $a['value'] === $selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($a['value'], ENT_QUOTES) . '"' . $sel . '>'
                   . htmlspecialchars($a['label'], ENT_QUOTES) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    // -----------------------------------------------------------------------
    // Personal account – stored in fa_preference_values
    // -----------------------------------------------------------------------

    public static function getPersonalConfig(string $userId): array
    {
        $keys = [
            'mail_type', 'smtp_host', 'smtp_port', 'smtp_secure',
            'smtp_username', 'smtp_password', 'bcc_email',
        ];
        $prefs = [];
        foreach ($keys as $key) {
            $prefs[$key] = self::readPref($userId, $key, '');
        }
        if (empty($prefs['smtp_host'])) {
            return [];
        }
        return [
            'mail_type'     => $prefs['mail_type'] ?: 'SMTP',
            'smtp_host'     => $prefs['smtp_host'],
            'smtp_port'     => (int) ($prefs['smtp_port'] ?: 25),
            'smtp_secure'   => $prefs['smtp_secure'] ?: 'none',
            'smtp_username' => $prefs['smtp_username'],
            'smtp_password' => $prefs['smtp_password'],
            'bcc_email'     => $prefs['bcc_email'],
        ];
    }

    public static function savePersonalConfig(string $userId, array $data): void
    {
        $allowed = [
            'mail_type', 'smtp_host', 'smtp_port', 'smtp_secure',
            'smtp_username', 'smtp_password', 'bcc_email',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                self::writePref($userId, $key, $data[$key]);
            }
        }
    }

    public static function personalPrefsExist(string $userId): bool
    {
        $config = self::getPersonalConfig($userId);
        return !empty($config['smtp_host']);
    }

    // -----------------------------------------------------------------------
    // Preference I/O – stored in fa_preference_values
    // -----------------------------------------------------------------------

    private static function readPref(string $userId, string $key, string $default): string
    {
        if (!function_exists('db_query')) {
            return $default;
        }
        $sql = sprintf(
            "SELECT pref_value FROM %s WHERE module_name='%s' AND user_id='%s' AND pref_key='%s' LIMIT 1",
            self::prefsTable(),
            self::esc(self::MODULE),
            self::esc($userId),
            self::esc($key)
        );
        $result = db_query($sql, 'Failed to read mail preference');
        if ($result && function_exists('db_fetch_assoc')) {
            $row = db_fetch_assoc($result);
            if ($row && isset($row['pref_value'])) {
                $val = json_decode($row['pref_value'], true);
                return json_last_error() === JSON_ERROR_NONE ? (string) $val : (string) $row['pref_value'];
            }
        }
        return $default;
    }

    private static function writePref(string $userId, string $key, $value): void
    {
        if (!function_exists('db_query')) {
            return;
        }
        $sql = sprintf(
            "INSERT INTO %s (module_name, user_id, pref_key, pref_value) VALUES ('%s', '%s', '%s', '%s') ON DUPLICATE KEY UPDATE pref_value=VALUES(pref_value)",
            self::prefsTable(),
            self::esc(self::MODULE),
            self::esc($userId),
            self::esc($key),
            self::esc(json_encode($value))
        );
        db_query($sql, 'Failed to save mail preference');
    }

    private static function prefsTable(): string
    {
        return (defined('TB_PREF') ? TB_PREF : '') . self::PREFS_TABLE;
    }

    private static function esc(string $v): string
    {
        return function_exists('db_escape') ? db_escape($v) : addslashes($v);
    }
}
