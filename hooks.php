<?php

/**
 * ksf_FA_Mail — Hook handlers for FrontAccounting.
 *
 * Registers:
 *  - "Mail Sending Setup" admin page (System menu)
 *  - "My Mail Settings" user page
 *  - mail_send hook for inter-module email
 *  - getMailPref / setMailPref for the shared preference store
 *
 * Preferences live in the shared cross-module store `0_ksf_module_prefs`
 * (see \Ksfraser\FA\Mail\Preferences); this module owns no preference table.
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

// Shared utility: ensure Composer dependencies are installed (runs once).
$composerDepsPath = dirname(__DIR__) . '/ksf_FA_Common/src/Utils/ComposerDependencies.php';
if (file_exists($composerDepsPath)) {
    require_once $composerDepsPath;
    \ksfraser\FrontAccounting\Common\Utils\ComposerDependencies::ensure(__DIR__);
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

define('SS_MAIL', 144 << 8);

class hooks_ksf_fa_mail extends hooks
{
    public $module_name = 'ksf_FA_Mail';
    public $version = '2.4.0';

    /**
     * Lazily created accessor for the shared preference store.
     *
     * @var \Ksfraser\FA\Mail\Preferences|null
     */
    private $mailPreferenceStore;

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

    public function install_tabs($app)
    {
        set_ext_domain('modules/' . $this->module_name);
        $app->add_application(new email_app());
        set_ext_domain();
        if (!defined('SA_ksf_FA_MailPERSONAL')) {
            define('SA_ksf_FA_MailPERSONAL', true);
        }
    }

    public function install_access()
    {
        $security_sections[SS_MAIL] = _('E-Mail');

        $security_areas['SA_MAIL_PERSONAL'] = [
            SS_MAIL | 1,
            _('Configure Personal Mail Account'),
        ];

        $security_areas['SA_MAIL_MANAGE'] = [
            SS_MAIL | 2,
            _('Manage Mail Accounts'),
        ];

        return [$security_areas, $security_sections];
    }

    public function activate_extension($company, $check_only = true)
    {
        if (!file_exists(__DIR__ . '/sql/mail_accounts.sql')) {
            return true;
        }

        $updates = [
            'mail_accounts.sql' => ['ksf_mail_accounts'],
        ];
        return $this->update_databases($company, $updates, $check_only);
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
     *   string from_email   Sender email (Calendar inviter).
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

        $inviterEmail = isset($data['from_email']) ? (string) $data['from_email'] : '';
        $accountType  = isset($data['account_type']) ? (string) $data['account_type'] : '';
        $userId       = isset($_SESSION['wa_current_user']->user)
            ? (string) $_SESSION['wa_current_user']->user
            : '';

        $config = [];
        if ($accountType !== '' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
            if ($userId !== '') {
                $config = \Ksfraser\FA\Mail\OutboundAccountService::resolveConfig($userId, $accountType);
            }
        }

        // Determine effective FROM and optional Reply-To.
        $fromEmail    = $inviterEmail;
        $replyToEmail = '';

        if ($accountType === \Ksfraser\FA\Mail\OutboundAccountService::TYPE_SYSTEM || $accountType === '') {
            $replyToEmail = $inviterEmail;
            $systemEmail  = self::getSystemFromEmail($config);
            if ($systemEmail !== '') {
                $fromEmail = $systemEmail;
            }
        } else {
            // Personal or third-party account: prefer the account's own from_email
            // (the SMTP-authenticated identity).  Fall back to the inviter email.
            $accountFrom = $config['from_email'] ?? '';
            if ($accountFrom !== '') {
                $fromEmail = $accountFrom;
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
            $fromEmail,
            isset($data['bcc_emails'])   ? (array)  $data['bcc_emails']   : [],
            $replyToEmail
        );
    }

    /**
     * Derive the system FROM email from SMTP config or company prefs.
     */
    private static function getSystemFromEmail(array $config): string
    {
        $smtpUser = $config['smtp_username'] ?? '';
        if ($smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
            return $smtpUser;
        }
        if (function_exists('get_company_pref')) {
            $coEmail = (string) get_company_pref('email');
            if ($coEmail !== '') {
                return $coEmail;
            }
        }
        return '';
    }

    // -----------------------------------------------------------------------
    // Account hooks — allow other modules to manage accounts for their owners
    // -----------------------------------------------------------------------

    /**
     * Retrieve a mail account for a given owner.
     *
     * Hook contract for other modules:
     *   ksf_mail_account_get(&$result, $ownerType, $ownerId)
     *     — Set $result to account array if you manage this owner type.
     *
     * This module handles owner_type='user'.  Other modules (HRM, CRM, …)
     * respond with their own owner types.
     */
    /**
     * Report the outbound senders this module offers.
     *
     * Hook contract for other modules:
     *   ksf_mail_sender_selector($data)  where $data = [user_id, selected]
     *     — return an object exposing options() and selected(), or null to decline.
     *
     * Returns DATA, not markup. The consumer renders it, so another module can
     * satisfy this contract with different senders or labels and no consumer
     * needs to change. Returning HTML would pin every consumer to this module's
     * markup and make the extension point decorative.
     *
     * Options are plain value/label arrays. The DTO interface is owned by the
     * consumer (ksf_FA_Calendar\DTO\OutboundSenderSelector); depending on it here
     * would re-create the coupling this hook exists to remove. PHP duck-types the
     * return and the consumer validates the shape via satisfies().
     *
     * Consumers MUST ask by hook rather than calling OutboundAccountService
     * directly. A caller cannot judge this service's usability: the class is
     * autoloadable even when the mail module is not installed, because other
     * extensions contribute the PSR-4 mapping. Deciding "is it safe" by
     * inspecting its tables from outside is a cross-module schema dependency,
     * and when the table is absent FA's db_query() raises E_USER_ERROR, which is
     * fatal and not catchable -- it silently truncated the calendar view.
     * The schema is this module's business, so the guard belongs here.
     *
     * @param array $data [user_id => string, selected => string]
     * @return object|null SenderSelector, or null to decline.
     */
    public function ksf_mail_sender_selector($data)
    {
        if (!is_array($data) || empty($data['user_id'])) {
            return null;
        }

        if (!class_exists('\\Ksfraser\\FA\\Mail\\OutboundAccountService')) {
            return null;
        }

        // Own-table guard: decline when this module's schema is not installed.
        if (!function_exists('db_query')) {
            return null;
        }

        $prefix = defined('TB_PREF') ? TB_PREF : '';
        $probe = db_query(
            "SHOW TABLES LIKE '" . db_escape($prefix . 'ksf_mail_accounts') . "'",
            'ksf_mail_accounts'
        );

        if (!$probe || db_num_rows($probe) === 0) {
            return null;
        }

        $selected = isset($data['selected']) ? (string) $data['selected'] : 'system';

        try {
            $accounts = \Ksfraser\FA\Mail\OutboundAccountService::getAvailableAccounts(
                (string) $data['user_id']
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($accounts) || $accounts === []) {
            return null;
        }

        $options = array();
        foreach ($accounts as $account) {
            if (!is_array($account) || !isset($account['value']) || (string) $account['value'] === '') {
                continue;
            }
            $options[] = array(
                'value' => (string) $account['value'],
                'label' => isset($account['label']) ? (string) $account['label'] : (string) $account['value'],
            );
        }

        if ($options === []) {
            return null;
        }

        return new SenderSelector($options, $selected);
    }

    public function ksf_mail_account_get(&$result, $ownerType, $ownerId)
    {
        if ($ownerType === 'user' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
            $result = \Ksfraser\FA\Mail\OutboundAccountService::getAccount('user', $ownerId);
            return true;
        }
        return null;
    }

    /**
     * Save a mail account for a given owner.
     *
     * Hook contract for other modules:
     *   ksf_mail_account_save(&$saved, $ownerType, $ownerId, $data)
     *     — Set $saved=true if you handled this owner type.
     */
    public function ksf_mail_account_save(&$saved, $ownerType, $ownerId, $data)
    {
        if ($ownerType === 'user' && class_exists('\Ksfraser\FA\Mail\OutboundAccountService')) {
            \Ksfraser\FA\Mail\OutboundAccountService::saveAccount('user', $ownerId, $data);
            $saved = true;
            return true;
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Preferences — shared cross-module store (0_ksf_module_prefs)
    // -----------------------------------------------------------------------

    /**
     * Read a Mail preference from the shared preference store.
     *
     * The store is the single cross-module table `0_ksf_module_prefs`, keyed
     * by (module_name, user_id, pref_key) and owned by the shared package
     * `ksfraser/ksf-fa-common` (AGENTS_ARCH.md section 12).  This module used
     * to read and write a table named `TB_PREF . 'fa_preference_values'`
     * (the legacy fa_-prefixed preference store),
     * which no release of this module ever created — and which, because
     * `0_` is the company prefix, also collided with FA core's own `0_fa_*`
     * namespace.  The old statements were double-quoted as well, so they
     * failed silently and every write went nowhere.
     *
     * @param string $key     Preference key
     * @param mixed  $default Value returned when the key is unset
     * @return mixed Stored value (JSON-decoded) or $default
     */
    public function getMailPref(string $key, $default = null)
    {
        return $this->mailPreferences()->get($key, $default);
    }

    /**
     * Write a Mail preference to the shared preference store.
     *
     * @param string $key   Preference key
     * @param mixed  $value Value to store
     * @return bool True when the write was dispatched or executed
     */
    public function setMailPref(string $key, $value): bool
    {
        return $this->mailPreferences()->set($key, $value);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * The module's preference accessor, created on first use.
     *
     * The class is resolved through the Composer autoloader rather than
     * being used by this file directly, so a deployment whose vendor tree is
     * missing the shared package cannot break the whole application from
     * hooks.php, which FrontAccounting loads for every registered extension
     * on every request.
     *
     * @return \Ksfraser\FA\Mail\Preferences
     */
    private function mailPreferences(): \Ksfraser\FA\Mail\Preferences
    {
        if (!$this->mailPreferenceStore instanceof \Ksfraser\FA\Mail\Preferences) {
            $this->mailPreferenceStore = new \Ksfraser\FA\Mail\Preferences();
        }

        return $this->mailPreferenceStore;
    }
}

// =========================================================================
// E-Mail Application definition
// =========================================================================

class email_app extends application
{
    public function __construct()
    {
        parent::__construct('Email', _($this->help_context = '&E-Mail'));

        $this->add_module(_('E-Mail'));

        $this->add_lapp_function(
            0,
            _('My Mail Account'),
            'modules/ksf_FA_Mail/my_mail_account.php?',
            'SA_OPEN',
            MENU_SETTINGS
        );
    }
}
