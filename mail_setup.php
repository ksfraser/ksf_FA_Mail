<?php

declare(strict_types=1);

$page_security = 'SA_SETUPCOMPANY';
$path_to_root = '../..';

include($path_to_root . '/includes/session.inc');
include($path_to_root . '/includes/ui.inc');
include($path_to_root . '/includes/data_checks.inc');
include($path_to_root . '/admin/db/company_db.inc');

require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\FA\Mail\SetupController;

page(_($help_context = 'Mail Setup'));

$controller = new SetupController();
$initCount = $controller->ensureDefaults();
if ($initCount > 0) {
    refresh_sys_prefs();
}

if (isset($_POST['test_email'])) {
    $result = $controller->sendTestEmail($_POST);
    if (str_starts_with($result, 'Test email sent')) {
        display_notification($result);
    } else {
        display_error($result);
    }
} elseif (isset($_POST['submit'])) {
    $error = $controller->validate($_POST);
    if ($error !== null) {
        display_error($error);
    } else {
        $controller->update($_POST);
        display_notification(_('The mail sending settings has been updated.'));
    }
}

$prefs = $controller->getPrefs();

$_POST['mail_type']     = $_POST['mail_type']     ?? $prefs['mail_type'];
$_POST['smtp_host']     = $_POST['smtp_host']     ?? $prefs['smtp_host'];
$_POST['smtp_port']     = $_POST['smtp_port']     ?? $prefs['smtp_port'];
$_POST['smtp_secure']   = $_POST['smtp_secure']   ?? $prefs['smtp_secure'];
$_POST['smtp_username'] = $_POST['smtp_username'] ?? $prefs['smtp_username'];
$_POST['smtp_password'] = $_POST['smtp_password'] ?? $prefs['smtp_password'];
$_POST['bcc_email']     = $_POST['bcc_email']     ?? $prefs['bcc_email'];

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();
$_selector = array_selector(
    'mail_type',
    $_POST['mail_type'],
    ['MAIL' => _("PHP's mail function"), 'SMTP' => _('SMTP server')],
    ['select_submit' => true]
);
label_cells(_('Mail type:'), $_selector);
end_row();
end_table();
echo '<hr>';

div_start('details');

if ($_POST['mail_type'] !== 'MAIL') {
    start_table(TABLESTYLE2);
    text_row(_('SMTP Host:'),      'smtp_host',     $_POST['smtp_host'],     50, 52);
    text_row(_('SMTP Port:'),      'smtp_port',     $_POST['smtp_port'],     10, 12);

    echo '<tr><td class="label">' . _('SMTP Secure:') . '</td><td>';
    echo array_selector('smtp_secure', $_POST['smtp_secure'], [
        'none' => _('None'),
        'tls'  => _('TLS'),
        'ssl'  => _('SSL'),
    ]);
    echo "</td></tr>\n";

    text_row(_('Username:'),       'smtp_username', $_POST['smtp_username'], 60, 62);
    text_row(_('Password:'),       'smtp_password', $_POST['smtp_password'], 60, 62);
    end_table(1);
}

// BCC is available regardless of mail type
start_table(TABLESTYLE2);
text_row_ex(_('BCC email:'), 'bcc_email', $_POST['bcc_email'], 50, 52, null, _('Blind carbon copy for all outgoing mail'));
end_table(1);

div_end();

submit_center('submit', _('Update'), true, '', 'default');

echo '<br>';
submit_center('test_email', _('Send Test Email'), false, '', 'default');

end_form();

end_page();
