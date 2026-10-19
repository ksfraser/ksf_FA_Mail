<?php

declare(strict_types=1);

namespace Ksfraser\FA\Mail;

use ksfraser\FrontAccounting\Common\Traits\ModulePreferencesTrait;

/**
 * Mail preferences, stored in the shared cross-module preference store.
 *
 * FrontAccounting has no per-module preference table of its own: the shared
 * package `ksfraser/ksf-fa-common` owns a single key/value table,
 * `0_ksf_module_prefs`, keyed by (module_name, user_id, pref_key).  This
 * class is the module's front door to that store and nothing else.
 *
 * History — why this class exists at all:
 *
 *  - v0.1.0 of this module shipped `sql/preference_values.sql`, creating a
 *    table literally named `fa_preference_values`, and read/wrote it as
 *    `TB_PREF . 'fa_preference_values'` (i.e. `0_fa_preference_values`).
 *    The two names never matched, the file was never wired into
 *    `activate_extension()`'s `update_databases()` map, and the SQL it
 *    produced was double-quoted (`db_escape()` already wraps values in
 *    quotes, and the caller added another pair), so every statement was a
 *    silent SQL error.  The store therefore never existed and never received
 *    a row: `fa_preference_values` was dead code from the first release.
 *  - `0_` is the COMPANY prefix, so an `fa` second segment also collides
 *    with FA core's own `0_fa_*` namespace (AGENTS_ARCH.md section 12).
 *
 * The SMTP configuration this module actually uses never lived there: system
 * settings are FA company preferences (`get_company_pref()`), and per-user
 * accounts live in `ksf_mail_accounts` (see OutboundAccountService).
 *
 * @package Ksfraser\FA\Mail
 * @since   2.2.0
 */
class Preferences
{
    use ModulePreferencesTrait;

    /**
     * Module name used as the `module_name` key segment of the shared store.
     *
     * ModulePreferencesTrait derives the segment from the using class's
     * `$module_name` property, which is the FrontAccounting hooks convention.
     *
     * @var string
     * @since 2.2.0
     */
    public $module_name = 'ksf_FA_Mail';

    /**
     * Shared store, named explicitly.
     *
     * The shared package's own default is `0_ksf_module_prefs` since
     * v1.0.15-dev, but the last tagged release (v1.0.14) still defaults to
     * the legacy `fa_preference_values` name.  Naming the table here keeps
     * this module correct whichever copy of the package is loaded first.
     *
     * @var string
     * @since 2.2.0
     */
    private const PREFERENCE_TABLE = 'ksf_module_prefs';

    /**
     * Point the trait at the shared store.
     *
     * The shared package's own default is `0_ksf_module_prefs` since
     * v1.0.15-dev, but the last tagged release (v1.0.14) still defaults to
     * the legacy `fa_preference_values` name.  Naming the table here keeps
     * this module correct whichever copy of the package is loaded first.
     *
     * @since 2.2.0
     */
    public function __construct()
    {
        $this->setPreferenceTable(self::PREFERENCE_TABLE);
    }

    /**
     * Read a Mail preference.
     *
     * @param string $key     Preference key
     * @param mixed  $default Value returned when the key is unset
     * @return mixed Stored value (JSON-decoded) or $default
     * @since 2.2.0
     */
    public function get(string $key, $default = null)
    {
        $this->bindUser();

        return $this->pref($key, $default);
    }

    /**
     * Write a Mail preference.
     *
     * @param string $key   Preference key
     * @param mixed  $value Value to store
     * @return bool True when the write was dispatched or executed
     * @since 2.2.0
     */
    public function set(string $key, $value): bool
    {
        $this->bindUser();

        return $this->setPref($key, $value);
    }

    /**
     * Point the trait at the current FA user.
     *
     * FrontAccounting identifies the signed-in user as
     * `$_SESSION['wa_current_user']->user`; it does not populate
     * `$_SESSION['user_id']`, which is what the trait falls back to.  The
     * explicit binding keeps Mail's preferences per-user instead of
     * silently company-wide.
     *
     * @return void
     * @since 2.2.0
     */
    private function bindUser(): void
    {
        $this->setPreferenceUser($this->currentUserId());
    }

    /**
     * The signed-in FA user id, or '' for a company-wide setting.
     *
     * @return string
     * @since 2.2.0
     */
    private function currentUserId(): string
    {
        if (isset($_SESSION['wa_current_user']) && is_object($_SESSION['wa_current_user'])
            && isset($_SESSION['wa_current_user']->user)) {
            return (string) $_SESSION['wa_current_user']->user;
        }

        if (isset($_SESSION['user_id']) && is_scalar($_SESSION['user_id'])) {
            return (string) $_SESSION['user_id'];
        }

        return '';
    }
}
