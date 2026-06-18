<?php

declare(strict_types=1);

$page_security = 'SA_SETUPCOMPANY';
$path_to_root = '../..';

include($path_to_root . '/includes/session.inc');
include($path_to_root . '/includes/ui.inc');

require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\FA\Mail\SetupController;

page(_($help_context = 'Mail Setup'));

$controller = new SetupController();
$initCount = $controller->ensureDefaults();
if ($initCount > 0) {
    global $SysPrefs;
    $SysPrefs->refresh();
}

if (isset($_POST['submit'])) {
    $error = $controller->validate($_POST);
    if ($error !== null) {
        display_error($error);
    } else {
        $controller->update($_POST);
        display_notification(_('The mail sending settings has been updated.'));
    }
}

$test_result = '';
if (isset($_POST['test_email'])) {
    $test_result = $controller->sendTestEmail($_POST);
}

$prefs = $controller->getPrefs();

$_POST['mail_type']     = $_POST['mail_type']     ?? $prefs['mail_type'];
$_POST['smtp_host']     = $_POST['smtp_host']     ?? $prefs['smtp_host'];
$_POST['smtp_port']     = $_POST['smtp_port']     ?? $prefs['smtp_port'];
$_POST['smtp_secure']   = $_POST['smtp_secure']   ?? $prefs['smtp_secure'];
$_POST['smtp_username'] = $_POST['smtp_username'] ?? $prefs['smtp_username'];
$_POST['smtp_password'] = $_POST['smtp_password'] ?? $prefs['smtp_password'];
$_POST['bcc_email']     = $_POST['bcc_email']     ?? $prefs['bcc_email'];

$show_smtp = $_POST['mail_type'] !== 'MAIL';

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();
$_selector = array_selector(
    'mail_type',
    $_POST['mail_type'],
    ['MAIL' => _("PHP's mail function"), 'SMTP' => _('SMTP server')]
);
label_cells(_('Mail type:'), $_selector);
end_row();
end_table();
echo '<hr>';

div_start('details');

echo '<div id="smtp_fields"' . ($show_smtp ? '' : ' style="display:none"') . '>';
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
echo '</div>';

start_table(TABLESTYLE2);
text_row_ex(_('BCC email:'), 'bcc_email', 50, 52, _('Blind carbon copy for all outgoing mail'), $_POST['bcc_email']);
end_table(1);

div_end();

submit_center('submit', _('Update'), true, '', 'default');

echo '<br>';
submit_center('test_email', _('Test Settings'), true, '', 'default');

if ($test_result !== '') {
    if (str_starts_with($test_result, 'Test email sent')) {
        display_notification($test_result);
    } else {
        display_error($test_result);
    }
}

end_form();

echo <<<JS
<script type="text/javascript">
(function() {
    var sel = document.getElementsByName('mail_type')[0];
    var div = document.getElementById('smtp_fields');
    if (!sel || !div) return;

    function toggle() {
        div.style.display = sel.value === 'SMTP' ? '' : 'none';
    }

    toggle();

    var old = window.onload || function(){};
    window.onload = function() {
        old();
        var orig = sel.onchange;
        sel.onchange = function() {
            if (typeof orig === 'function') orig.call(this);
            toggle();
            return true;
        };
    };
})();
</script>
JS;

end_page();
