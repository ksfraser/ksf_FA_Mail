<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Stub for FA's _() translation function.
 */
if (!function_exists('_')) {
    function _(string $msg): string
    {
        return $msg;
    }
}

/**
 * Stub for get_company_pref().
 */
if (!function_exists('get_company_pref')) {
    function get_company_pref(string $name)
    {
        static $prefs = [];
        return $prefs[$name] ?? null;
    }
}

/**
 * Stub for set_company_pref().
 */
if (!function_exists('set_company_pref')) {
    function set_company_pref(string $name, string $module, string $type, int $size, $value): void
    {
        // no-op
    }
}

/**
 * Stub for update_company_prefs().
 */
if (!function_exists('update_company_prefs')) {
    function update_company_prefs(array $data): void
    {
        // no-op
    }
}

/**
 * Stub for refresh_sys_prefs().
 */
if (!function_exists('refresh_sys_prefs')) {
    function refresh_sys_prefs(): void
    {
        // no-op
    }
}

/**
 * Stub for hooks class.
 */
if (!class_exists('hooks')) {
    abstract class hooks
    {
        public $module_name = '';
        public function install_options($app) {}
    }
}

/**
 * In-memory stubs for test tables.
 * @var array<string, array<string, array<string, array<string, string>>>>  [_test_prefs][module][user][key]=value
 * @var array<string, array>  [_test_accounts][ownerType][ownerId]=row
 */
$GLOBALS['_test_prefs'] = [];
$GLOBALS['_test_accounts'] = [];

/**
 * Return the ksf_mail_accounts table name (with optional prefix).
 */
if (!function_exists('_test_accounts_table')) {
    function _test_accounts_table(): string
    {
        return (defined('TB_PREF') ? constant('TB_PREF') : '') . 'ksf_mail_accounts';
    }
}

/**
 * Parse a table name from SQL, stripping backticks and any TB_PREF prefix.
 */
if (!function_exists('_test_parse_table')) {
    function _test_parse_table(string $sql): ?string
    {
        if (preg_match('/`?([a-z_]+)`?\s/', $sql, $m)) {
            $name = $m[1];
            $pref = defined('TB_PREF') ? constant('TB_PREF') : '';
            if ($pref !== '' && str_starts_with($name, $pref)) {
                $name = substr($name, strlen($pref));
            }
            return $name;
        }
        return null;
    }
}

/**
 * Stub for db_query() — handles fa_preference_values AND ksf_mail_accounts.
 */
if (!function_exists('db_query')) {
    function db_query(string $sql, string $msg = '')
    {
        // ---- ksf_mail_accounts SELECT ----
        if (preg_match("/^SELECT \* FROM .*ksf_mail_accounts.* WHERE owner_type='([^']+)' AND owner_id='([^']+)'/", $sql, $m)) {
            $ownerType = $m[1];
            $ownerId = $m[2];
            if (isset($GLOBALS['_test_accounts'][$ownerType][$ownerId])) {
                return [$GLOBALS['_test_accounts'][$ownerType][$ownerId]];
            }
            return [];
        }

        // ---- ksf_mail_accounts INSERT ... ON DUPLICATE KEY UPDATE ----
        if (preg_match("/^INSERT INTO .*ksf_mail_accounts.* ON DUPLICATE KEY UPDATE/", $sql)) {
            // Extract owner_type and owner_id
            preg_match("/VALUES\s*\('([^']+)',\s*'([^']+)'/s", $sql, $m);
            if (!empty($m[1])) {
                $ownerType = $m[1];
                $ownerId = $m[2];
                // Extract column=value pairs from ON DUPLICATE KEY UPDATE
                preg_match("/ON DUPLICATE KEY UPDATE\s+(.+)$/s", $sql, $upd);
                $row = [
                    'id' => 42,
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'is_default' => '0',
                    'local_part' => '', 'domain' => '', 'from_name' => '',
                    'smtp_host' => '', 'smtp_port' => '25', 'smtp_secure' => 'none',
                    'smtp_username' => '', 'smtp_password' => '',
                    'imap_host' => '', 'imap_port' => '993', 'imap_secure' => 'ssl',
                    'imap_username' => '', 'imap_password' => '',
                    'bcc_email' => '',
                ];
                if (!empty($upd[1])) {
                    foreach (explode(',', $upd[1]) as $pair) {
                        if (preg_match("/`?(\w+)`?\s*=\s*'([^']*)'/", trim($pair), $p)) {
                            $row[$p[1]] = $p[2];
                        }
                    }
                }
                $GLOBALS['_test_accounts'][$ownerType][$ownerId] = $row;
            }
            return true;
        }

        // ---- ksf_mail_accounts DELETE ----
        if (preg_match("/^DELETE FROM .*ksf_mail_accounts.* WHERE owner_type='([^']+)' AND owner_id='([^']+)'/", $sql, $m)) {
            unset($GLOBALS['_test_accounts'][$m[1]][$m[2]]);
            return true;
        }

        // ---- Legacy fa_preference_values SELECT ----
        if (preg_match("/^SELECT pref_value FROM .*fa_preference_values.* WHERE module_name='([^']+)' AND user_id='([^']+)' AND pref_key='([^']+)'/", $sql, $m)) {
            $module = $m[1];
            $uid = $m[2];
            $key = $m[3];
            if (isset($GLOBALS['_test_prefs'][$module][$uid][$key])) {
                return [['pref_value' => $GLOBALS['_test_prefs'][$module][$uid][$key]]];
            }
            return [];
        }

        // ---- Legacy fa_preference_values INSERT ----
        if (preg_match("/^INSERT INTO .*fa_preference_values.* VALUES\s*\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*?)'\)/s", $sql, $m)) {
            $GLOBALS['_test_prefs'][$m[1]][$m[2]][$m[3]] = $m[4];
            return true;
        }

        // ---- Legacy fa_preference_values INSERT ... ON DUPLICATE KEY UPDATE ----
        if (preg_match("/^INSERT INTO .*fa_preference_values.* ON DUPLICATE KEY UPDATE/", $sql)) {
            preg_match("/VALUES\s*\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*?)'/s", $sql, $m);
            if (!empty($m[1])) {
                $GLOBALS['_test_prefs'][$m[1]][$m[2]][$m[3]] = $m[4];
            }
            return true;
        }

        return true;
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result)
    {
        if (is_array($result) && !empty($result)) {
            return array_shift($result);
        }
        return false;
    }
}

if (!function_exists('db_insert_id')) {
    function db_insert_id(): int
    {
        return 42;
    }
}

if (!function_exists('db_escape')) {
    function db_escape(string $s): string
    {
        // Simple escape: wrap in quotes and escape single quotes and backslashes
        $s = str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
        return $s;
    }
}
