<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

class OutboundAccountService
{
    public const TYPE_SYSTEM = 'system';
    public const TYPE_PERSONAL = 'personal';

    private const ACCOUNTS_TABLE = 'ksf_mail_accounts';
    private const PREFS_TABLE = 'fa_preference_values';
    private const MODULE = 'ksf_FA_Mail';

    public static function getAvailableAccounts(string $userId): array
    {
        $accounts = [];

        $accounts[] = [
            'value' => self::TYPE_SYSTEM,
            'label' => _('System Account'),
        ];

        $personal = self::getAccount('user', $userId);
        if (!empty($personal['smtp_host'])) {
            $email = self::buildEmail($personal);
            $label = _('Personal') . ': ' . ($email ?: _('configured'));
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

    public static function resolveConfig(string $userId, string $accountType): array
    {
        if ($accountType === self::TYPE_SYSTEM) {
            return [];
        }

        if ($accountType === self::TYPE_PERSONAL) {
            $account = self::getAccount('user', $userId);
            if (empty($account['smtp_host'])) {
                return [];
            }
            return self::accountToConfig($account);
        }

        if (function_exists('hook_invoke_all')) {
            $config = null;
            hook_invoke_all('resolve_sender_config', $config, $accountType, $userId);
            if (is_array($config) && !empty($config)) {
                return $config;
            }
        }

        return [];
    }

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
    // Account CRUD — ksf_mail_accounts
    // -----------------------------------------------------------------------

    public static function getAccount(string $ownerType, string $ownerId): array
    {
        $row = self::dbGetAccount($ownerType, $ownerId);
        if ($row !== null) {
            return self::rowToArray($row);
        }

        $oldPrefs = self::migrateFromPrefs($ownerType, $ownerId);
        if (!empty($oldPrefs)) {
            return $oldPrefs;
        }

        return [];
    }

    public static function saveAccount(string $ownerType, string $ownerId, array $data): void
    {
        $allowed = [
            'local_part', 'domain', 'from_name',
            'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_username', 'smtp_password',
            'imap_host', 'imap_port', 'imap_secure', 'imap_username', 'imap_password',
            'bcc_email', 'is_default',
        ];

        $columns = [];
        $values = [];
        $updates = [];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $v = $data[$f];
                if (is_bool($v)) {
                    $v = $v ? 1 : 0;
                }
                $escaped = self::esc((string) $v);
                $columns[] = "`{$f}`";
                $values[] = "'{$escaped}'";
                $updates[] = "`{$f}`='{$escaped}'";
            }
        }

        if (empty($columns)) {
            return;
        }

        $sql = "INSERT INTO `" . self::accountsTable() . "`"
            . " (`owner_type`, `owner_id`, " . implode(', ', $columns) . ")"
            . " VALUES ('" . self::esc($ownerType) . "', '" . self::esc($ownerId) . "', " . implode(', ', $values) . ")"
            . " ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        self::dbQuery($sql, 'Failed to save mail account');
    }

    public static function deleteAccount(string $ownerType, string $ownerId): void
    {
        $sql = "DELETE FROM `" . self::accountsTable() . "`"
            . " WHERE owner_type='" . self::esc($ownerType) . "'"
            . " AND owner_id='" . self::esc($ownerId) . "'";
        self::dbQuery($sql, 'Failed to delete mail account');
    }

    // -----------------------------------------------------------------------
    // Backward-compatible convenience — maps old my_mail_setup.php interface
    // -----------------------------------------------------------------------

    public static function getPersonalConfig(string $userId): array
    {
        $account = self::getAccount('user', $userId);
        if (empty($account)) {
            return [];
        }
        return self::accountToConfig($account);
    }

    public static function savePersonalConfig(string $userId, array $data): void
    {
        $account = [];

        if (!empty($data['from_email'])) {
            $parts = explode('@', $data['from_email'], 2);
            $account['local_part'] = $parts[0] ?? '';
            $account['domain'] = $parts[1] ?? '';
        }
        if (!empty($data['from_name'])) {
            $account['from_name'] = $data['from_name'];
        }

        $map = [
            'smtp_host'     => 'smtp_host',
            'smtp_port'     => 'smtp_port',
            'smtp_secure'   => 'smtp_secure',
            'smtp_username' => 'smtp_username',
            'smtp_password' => 'smtp_password',
            'bcc_email'     => 'bcc_email',
        ];

        foreach ($map as $oldKey => $newKey) {
            if (array_key_exists($oldKey, $data)) {
                $account[$newKey] = $data[$oldKey];
            }
        }

        if (empty($account['smtp_username']) && !empty($account['local_part'])) {
            $account['smtp_username'] = $account['local_part'] . '@' . $account['domain'];
        }

        self::saveAccount('user', $userId, $account);
    }

    public static function personalPrefsExist(string $userId): bool
    {
        $config = self::getPersonalConfig($userId);
        return !empty($config['smtp_host']);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private static function accountToConfig(array $account): array
    {
        return [
            'mail_type'     => $account['smtp_host'] !== '' ? 'SMTP' : 'MAIL',
            'smtp_host'     => $account['smtp_host'] ?? '',
            'smtp_port'     => (int) ($account['smtp_port'] ?? 25),
            'smtp_secure'   => $account['smtp_secure'] ?? 'none',
            'smtp_username' => $account['smtp_username'] ?: self::buildEmail($account),
            'smtp_password' => $account['smtp_password'] ?? '',
            'bcc_email'     => $account['bcc_email'] ?? '',
            'from_email'    => self::buildEmail($account),
            'from_name'     => $account['from_name'] ?? '',
        ];
    }

    private static function buildEmail(array $account): string
    {
        $local = $account['local_part'] ?? '';
        $domain = $account['domain'] ?? '';
        if ($local !== '' && $domain !== '') {
            return $local . '@' . $domain;
        }
        return $account['smtp_username'] ?? '';
    }

    private static function rowToArray(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'owner_type'    => $row['owner_type'],
            'owner_id'      => $row['owner_id'],
            'is_default'    => (bool) ($row['is_default'] ?? false),
            'local_part'    => $row['local_part'] ?? '',
            'domain'        => $row['domain'] ?? '',
            'from_name'     => $row['from_name'] ?? '',
            'smtp_host'     => $row['smtp_host'] ?? '',
            'smtp_port'     => (int) ($row['smtp_port'] ?? 25),
            'smtp_secure'   => $row['smtp_secure'] ?? 'none',
            'smtp_username' => $row['smtp_username'] ?? '',
            'smtp_password' => $row['smtp_password'] ?? '',
            'imap_host'     => $row['imap_host'] ?? '',
            'imap_port'     => (int) ($row['imap_port'] ?? 993),
            'imap_secure'   => $row['imap_secure'] ?? 'ssl',
            'imap_username' => $row['imap_username'] ?? '',
            'imap_password' => $row['imap_password'] ?? '',
            'bcc_email'     => $row['bcc_email'] ?? '',
        ];
    }

    private static function dbGetAccount(string $ownerType, string $ownerId): ?array
    {
        if (!function_exists('db_query')) {
            return null;
        }
        $sql = "SELECT * FROM `" . self::accountsTable() . "`"
            . " WHERE owner_type='" . self::esc($ownerType) . "'"
            . " AND owner_id='" . self::esc($ownerId) . "'"
            . " LIMIT 1";
        $result = self::dbQuery($sql, 'Failed to read mail account');
        if ($result && function_exists('db_fetch_assoc')) {
            $row = db_fetch_assoc($result);
            if ($row && is_array($row)) {
                return $row;
            }
        }
        return null;
    }

    private static function migrateFromPrefs(string $ownerType, string $ownerId): array
    {
        if ($ownerType !== 'user') {
            return [];
        }

        $keys = [
            'mail_type', 'smtp_host', 'smtp_port', 'smtp_secure',
            'smtp_username', 'smtp_password', 'bcc_email',
        ];
        $prefs = [];
        foreach ($keys as $key) {
            $prefs[$key] = self::readOldPref($ownerId, $key, '');
        }
        if (empty($prefs['smtp_host'])) {
            return [];
        }

        $account = [
            'smtp_host'     => $prefs['smtp_host'],
            'smtp_port'     => (int) ($prefs['smtp_port'] ?: 25),
            'smtp_secure'   => $prefs['smtp_secure'] ?: 'none',
            'smtp_username' => $prefs['smtp_username'] ?: '',
            'smtp_password' => $prefs['smtp_password'] ?: '',
            'bcc_email'     => $prefs['bcc_email'] ?: '',
        ];

        $username = $account['smtp_username'];
        if ($username !== '' && str_contains($username, '@')) {
            $parts = explode('@', $username, 2);
            $account['local_part'] = $parts[0];
            $account['domain'] = $parts[1];
        }

        self::saveAccount('user', $ownerId, $account);
        return $account;
    }

    private static function readOldPref(string $userId, string $key, string $default): string
    {
        if (!function_exists('db_query')) {
            return $default;
        }
        $sql = sprintf(
            "SELECT pref_value FROM %s WHERE module_name='%s' AND user_id='%s' AND pref_key='%s' LIMIT 1",
            self::oldPrefsTable(),
            self::esc(self::MODULE),
            self::esc($userId),
            self::esc($key)
        );
        $result = self::dbQuery($sql, 'Failed to read mail preference');
        if ($result && function_exists('db_fetch_assoc')) {
            $row = db_fetch_assoc($result);
            if ($row && isset($row['pref_value'])) {
                $val = json_decode($row['pref_value'], true);
                return json_last_error() === JSON_ERROR_NONE ? (string) $val : (string) $row['pref_value'];
            }
        }
        return $default;
    }

    private static function accountsTable(): string
    {
        return (defined('TB_PREF') ? TB_PREF : '') . self::ACCOUNTS_TABLE;
    }

    private static function oldPrefsTable(): string
    {
        return (defined('TB_PREF') ? TB_PREF : '') . self::PREFS_TABLE;
    }

    private static function esc(string $v): string
    {
        return function_exists('db_escape') ? db_escape($v) : addslashes($v);
    }

    private static function dbQuery(string $sql, string $msg = '')
    {
        if (!function_exists('db_query')) {
            return null;
        }
        return db_query($sql, $msg);
    }
}
