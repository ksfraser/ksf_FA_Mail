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
    function set_company_pref(string $name, $module, $type, int $size, $value): void
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
 *
 * `_test_prefs` is the shared cross-module preference store
 * (`0_ksf_module_prefs`), keyed by table -> module -> user -> key.
 *
 * `_test_accounts` is this module's own account table (`0_ksf_mail_accounts`),
 * keyed by owner type -> owner id.
 *
 * `_test_sql` records every statement the code under test sent to the
 * database, and `_test_unknown_tables` records every table that no stub
 * knows about.  FrontAccounting's db_query() returns false without an error
 * when a statement fails, so a module that addresses a table that does not
 * exist fails *silently*; recording the statements is what makes that
 * failure visible to a test.
 *
 * @var array<string, array<string, array<string, array<string, string>>>>
 * @var array<string, array>
 * @var string[]
 * @var string[]
 */
$GLOBALS['_test_prefs'] = [];
$GLOBALS['_test_accounts'] = [];
$GLOBALS['_test_sql'] = [];
$GLOBALS['_test_unknown_tables'] = [];

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
 * Return the shared module preference store table name (with optional prefix).
 */
if (!function_exists('_test_prefs_table')) {
    function _test_prefs_table(): string
    {
        return (defined('TB_PREF') ? constant('TB_PREF') : '') . 'ksf_module_prefs';
    }
}

/**
 * Extract the table a statement addresses.
 */
if (!function_exists('_test_statement_table')) {
    function _test_statement_table(string $sql): ?string
    {
        if (preg_match('/\bFROM\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m)) {
            return $m[1];
        }
        if (preg_match('/\bINTO\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m)) {
            return $m[1];
        }
        return null;
    }
}

/**
 * Strip the company prefix from a table name.
 */
if (!function_exists('_test_strip_prefix')) {
    function _test_strip_prefix(string $table): string
    {
        $pref = defined('TB_PREF') ? constant('TB_PREF') : '';
        if ($pref !== '' && strpos($table, $pref) === 0) {
            return substr($table, strlen($pref));
        }
        return $table;
    }
}

/**
 * Stub for db_query() — handles the shared preference store and
 * ksf_mail_accounts.  Any other table is recorded as unknown and the query
 * fails the way FrontAccounting's db_query() fails: silently.
 */
if (!function_exists('db_query')) {
    function db_query(string $sql, string $msg = '')
    {
        $GLOBALS['_test_sql'][] = $sql;

        $table = _test_statement_table($sql);
        $bare = $table === null ? '' : _test_strip_prefix($table);

        // ---- ksf_mail_accounts SELECT ----
        if ($bare === 'ksf_mail_accounts'
            && preg_match("/^SELECT \* FROM .*ksf_mail_accounts.* WHERE owner_type='([^']+)' AND owner_id='([^']+)'/", $sql, $m)) {
            $ownerType = $m[1];
            $ownerId = $m[2];
            if (isset($GLOBALS['_test_accounts'][$ownerType][$ownerId])) {
                return [$GLOBALS['_test_accounts'][$ownerType][$ownerId]];
            }
            return [];
        }

        // ---- ksf_mail_accounts INSERT ... ON DUPLICATE KEY UPDATE ----
        if ($bare === 'ksf_mail_accounts'
            && preg_match("/^INSERT INTO .*ksf_mail_accounts.* ON DUPLICATE KEY UPDATE/", $sql)) {
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
        if ($bare === 'ksf_mail_accounts'
            && preg_match("/^DELETE FROM .*ksf_mail_accounts.* WHERE owner_type='([^']+)' AND owner_id='([^']+)'/", $sql, $m)) {
            unset($GLOBALS['_test_accounts'][$m[1]][$m[2]]);
            return true;
        }

        // ---- Shared preference store SELECT ----
        if ($bare === 'ksf_module_prefs'
            && preg_match("/^SELECT pref_value FROM .*ksf_module_prefs.* WHERE module_name='([^']*)' AND user_id='([^']*)' AND pref_key='([^']+)'/", $sql, $m)) {
            $module = $m[1];
            $uid = $m[2];
            $key = $m[3];
            if (isset($GLOBALS['_test_prefs'][$module][$uid][$key])) {
                return [['pref_value' => $GLOBALS['_test_prefs'][$module][$uid][$key]]];
            }
            return [];
        }

        // ---- Shared preference store INSERT ----
        if ($bare === 'ksf_module_prefs'
            && preg_match("/^INSERT INTO .*ksf_module_prefs.* VALUES\s*\('([^']*)',\s*'([^']*)',\s*'([^']+)',\s*'([^']*?)'\)/s", $sql, $m)) {
            $GLOBALS['_test_prefs'][$m[1]][$m[2]][$m[3]] = $m[4];
            return true;
        }

        // ---- Shared preference store INSERT ... ON DUPLICATE KEY UPDATE ----
        if ($bare === 'ksf_module_prefs'
            && preg_match("/^INSERT INTO .*ksf_module_prefs.* ON DUPLICATE KEY UPDATE/", $sql)) {
            preg_match("/VALUES\s*\('([^']*)',\s*'([^']*)',\s*'([^']+)',\s*'([^']*?)'/s", $sql, $m);
            if (!empty($m[1])) {
                $GLOBALS['_test_prefs'][$m[1]][$m[2]][$m[3]] = $m[4];
            }
            return true;
        }

        // ---- Anything else: a table this module does not own ----
        if ($table !== null) {
            $GLOBALS['_test_unknown_tables'][] = $table;
        }

        return false;
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

if (!function_exists('db_fetch_row')) {
    function db_fetch_row($result)
    {
        if (is_array($result) && !empty($result)) {
            $row = array_shift($result);
            return is_array($row) ? array_values($row) : [$row];
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
    /**
     * Mirror FrontAccounting's db_escape(): the value comes back wrapped in
     * single quotes, because the caller adds none of its own.
     */
    function db_escape(string $s): string
    {
        $s = str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
        return "'" . $s . "'";
    }
}
