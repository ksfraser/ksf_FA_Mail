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
if (isset($_POST['test_settings'])) {
    $test_result = $controller->testSettings($_POST);
}

$pending_confirm = false;
$pending_recipient = '';
if (isset($_POST['send_test_email']) && !isset($_POST['confirm_send'])) {
    $recipient = $_POST['test_recipient'] ?? '';
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $test_result = _('Please enter a valid email address.');
    } else {
        $pending_confirm = true;
        $pending_recipient = $recipient;
    }
}

if (isset($_POST['confirm_send'])) {
    $recipient = $_POST['test_recipient'] ?? '';
    $test_result = $controller->sendTestEmailTo($_POST, $recipient);
}

$prefs = $controller->getPrefs();

$_POST['mail_type']     = $_POST['mail_type']     ?? $prefs['mail_type'];
$_POST['smtp_host']     = $_POST['smtp_host']     ?? $prefs['smtp_host'];
$_POST['smtp_port']     = $_POST['smtp_port']     ?? $prefs['smtp_port'];
$_POST['smtp_secure']   = $_POST['smtp_secure']   ?? $prefs['smtp_secure'];
$_POST['smtp_username'] = $_POST['smtp_username'] ?? $prefs['smtp_username'];
$_POST['smtp_password'] = $_POST['smtp_password'] ?? $prefs['smtp_password'];
$_POST['bcc_email']     = $_POST['bcc_email']     ?? $prefs['bcc_email'];

$default_test_recipient = '';
if (isset($_SESSION['wa_current_user']->email)) {
    $default_test_recipient = (string) $_SESSION['wa_current_user']->email;
}
$_POST['test_recipient'] = $_POST['test_recipient'] ?? $default_test_recipient;

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
submit_center('test_settings', _('Test Settings'), true, '', 'default');

echo '<br>';
echo '<div id="test_email_section">';
start_table(TABLESTYLE2);
text_row_ex(_('Test recipient:'), 'test_recipient', 50, 60, _('Email address to send the test message to'), $_POST['test_recipient']);
end_table(1);
echo '<br>';
submit_center('send_test_email', _('Send Test Email'), true, '', 'nonajax');
echo '</div>';

if ($pending_confirm) {
    echo '<br>';
    display_warning(
        sprintf(
            _('Send a test email to %s? You must have consent to email this address (CASL compliance).'),
            $pending_recipient
        )
    );
    echo '<center>';
    submit_center('confirm_send', _('Confirm Send'), true, '', 'nonajax');
    echo '&nbsp;';
    submit_center('cancel_send', _('Cancel'), true, '', 'nonajax');
    echo '</center>';
}

if ($test_result !== '') {
    if (str_contains($test_result, 'failed') || str_starts_with($test_result, 'SMTP test failed') || str_contains($test_result, 'Could not') || str_contains($test_result, 'not selected')) {
        display_error($test_result);
    } elseif (str_contains($test_result, 'Please enter') || str_contains($test_result, 'please enter') || str_contains($test_result, 'Valid email') || str_contains($test_result, 'valid email')) {
        display_warning($test_result);
    } else {
        display_notification($test_result);
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
