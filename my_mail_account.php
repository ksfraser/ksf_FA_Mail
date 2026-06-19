<?php

declare(strict_types=1);

$page_security = 'SA_MAIL_PERSONAL';
$path_to_root = '../..';

include($path_to_root . '/includes/session.inc');
include($path_to_root . '/includes/ui.inc');

require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\FA\Mail\OutboundAccountService;

page(_($help_context = 'My Mail Account'));

$userId = (string) (isset($_SESSION['wa_current_user']->user)
    ? $_SESSION['wa_current_user']->user
    : '');

if ($userId === '') {
    display_error(_('User not identified.'));
    end_page();
    exit;
}

if (isset($_POST['submit'])) {
    $data = [
        'local_part'     => $_POST['local_part'] ?? '',
        'domain'         => $_POST['domain'] ?? '',
        'from_name'      => $_POST['from_name'] ?? '',
        'smtp_host'      => $_POST['smtp_host'] ?? '',
        'smtp_port'      => (int) ($_POST['smtp_port'] ?? 587),
        'smtp_secure'    => $_POST['smtp_secure'] ?? 'tls',
        'smtp_username'  => $_POST['smtp_username'] ?? '',
        'smtp_password'  => $_POST['smtp_password'] ?? '',
        'imap_host'      => $_POST['imap_host'] ?? '',
        'imap_port'      => (int) ($_POST['imap_port'] ?? 993),
        'imap_secure'    => $_POST['imap_secure'] ?? 'ssl',
        'imap_username'  => $_POST['imap_username'] ?? '',
        'imap_password'  => $_POST['imap_password'] ?? '',
        'bcc_email'      => $_POST['bcc_email'] ?? '',
    ];

    if (empty($data['local_part']) && empty($data['domain'])) {
        display_error(_('At least the local part of your email address must be entered.'));
    } else {
        OutboundAccountService::saveAccount('user', $userId, $data);
        display_notification(_('Your mail account settings have been updated.'));
    }
}

$account = OutboundAccountService::getAccount('user', $userId);
if (empty($account)) {
    $account = [
        'local_part'     => '',
        'domain'         => '',
        'from_name'      => '',
        'smtp_host'      => '',
        'smtp_port'      => 587,
        'smtp_secure'    => 'tls',
        'smtp_username'  => '',
        'smtp_password'  => '',
        'imap_host'      => '',
        'imap_port'      => 993,
        'imap_secure'    => 'ssl',
        'imap_username'  => '',
        'imap_password'  => '',
        'bcc_email'      => '',
    ];
}

foreach ($account as $k => $v) {
    if (!isset($_POST[$k])) {
        $_POST[$k] = $v;
    }
}

start_form();

start_table(TABLESTYLE2);

label_cells(_('Email address:'), '
    <table style="border:0;display:inline"><tr>
    <td><input type="text" name="local_part" size="30" maxlength="255"
        value="' . htmlspecialchars((string) $_POST['local_part'], ENT_QUOTES) . '"></td>
    <td style="font-size:1.2em;padding:0 4px">@</td>
    <td><input type="text" name="domain" size="30" maxlength="255"
        value="' . htmlspecialchars((string) $_POST['domain'], ENT_QUOTES) . '"></td>
    </tr></table>');

text_row(_('From name:'), 'from_name', $_POST['from_name'], 50, 255);

end_table(1);

echo '<hr><h3>' . _('SMTP (Outgoing)') . '</h3>';

start_table(TABLESTYLE2);
text_row(_('SMTP Host:'),      'smtp_host',     $_POST['smtp_host'],     50, 255);
text_row(_('SMTP Port:'),      'smtp_port',     $_POST['smtp_port'],     10, 11);

echo '<tr><td class="label">' . _('SMTP Secure:') . '</td><td>';
echo array_selector('smtp_secure', $_POST['smtp_secure'], [
    'none' => _('None'),
    'tls'  => _('TLS'),
    'ssl'  => _('SSL'),
]);
echo "</td></tr>\n";

text_row(_('Username:'),       'smtp_username', $_POST['smtp_username'], 50, 255);
text_row(_('Password:'),       'smtp_password', $_POST['smtp_password'], 50, 255);
end_table(1);

echo '<h3>' . _('IMAP (Incoming)') . '</h3>';

start_table(TABLESTYLE2);
text_row(_('IMAP Host:'),      'imap_host',     $_POST['imap_host'],     50, 255);
text_row(_('IMAP Port:'),      'imap_port',     $_POST['imap_port'],     10, 11);

echo '<tr><td class="label">' . _('IMAP Secure:') . '</td><td>';
echo array_selector('imap_secure', $_POST['imap_secure'], [
    'none' => _('None'),
    'tls'  => _('TLS'),
    'ssl'  => _('SSL'),
]);
echo "</td></tr>\n";

text_row(_('Username:'),       'imap_username', $_POST['imap_username'], 50, 255);
text_row(_('Password:'),       'imap_password', $_POST['imap_password'], 50, 255);
end_table(1);

echo '<hr>';

start_table(TABLESTYLE2);
text_row_ex(_('BCC email:'), 'bcc_email', $_POST['bcc_email'], 50, 255, null,
    _('Blind carbon copy for all outgoing mail'));
end_table(1);

submit_center('submit', _('Update'), true, '', 'default');

end_form();

end_page();
