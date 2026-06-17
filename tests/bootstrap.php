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
 * In-memory stub for the fa_preference_values table used in tests.
 * @var array<string, array<string, array<string, string>>>
 */
$GLOBALS['_test_prefs'] = [];

/**
 * Stub for db_query() — supports SELECT/INSERT with our prefs table.
 */
if (!function_exists('db_query')) {
    function db_query(string $sql, string $msg = '')
    {
        // Simple SELECT parsing for preference_values table
        if (preg_match("/^SELECT pref_value FROM .*fa_preference_values.* WHERE module_name='([^']+)' AND user_id='([^']+)' AND pref_key='([^']+)'/", $sql, $m)) {
            $module = $m[1];
            $uid = $m[2];
            $key = $m[3];
            if (isset($GLOBALS['_test_prefs'][$module][$uid][$key])) {
                return [['pref_value' => $GLOBALS['_test_prefs'][$module][$uid][$key]]];
            }
            return [];
        }
        // Simple INSERT parsing
        if (preg_match("/^INSERT INTO .*fa_preference_values.* VALUES\s*\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']*?)'\)/s", $sql, $m)) {
            $GLOBALS['_test_prefs'][$m[1]][$m[2]][$m[3]] = $m[4];
            return true;
        }
        // INSERT ... ON DUPLICATE KEY UPDATE
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
