<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/password.php';
require_once CM_LIB . '/tfa.php';

$user = cm_require_login();
$user = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);



if (isset($_GET['cancel'])) {
    unset($_SESSION['cm_tfa_setup']);
    cm_flash('Setup cancelled. Nothing was changed on your account.');
    cm_redirect('account/');
}

$method  = cm_tfa_method($user);
$isStaff = cm_is_staff($user);
$error   = '';
$showCodes = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');







    $manageBucket = 'tfa_manage';
    $manageKey    = 'account:' . (int) $user['id'];
    if (in_array($action, ['newcodes', 'off'], true)) {
        if (cm_rate_exceeded($manageBucket, $manageKey, 10, 15)) {
            cm_flash('Too many codes tried. Wait fifteen minutes, then try again.', 'error');
            cm_redirect('account/?open=twostep');
        }
    }
    if (in_array($action, ['start', 'confirm'], true) && $method !== '') {
        unset($_SESSION['cm_tfa_setup']);
        cm_flash('Two-step sign-in is already on. To change the method, turn it off first (that asks for a code), then set it up again.', 'error');
        cm_redirect('account/?open=twostep');
    }

    if ($action === 'start') {
        $want = (string) ($_POST['method'] ?? '');
        if (!isset(cm_tfa_methods()[$want])) {
            cm_flash('Pick one of the two methods.', 'error');
            cm_redirect('account/?open=twostep');
        }
        if ($want === 'email' && !cm_mail_enabled()) {
            cm_flash('Email is not switched on for this site yet, so a code could never reach you. '
                   . 'Use an authenticator app instead.', 'error');
            cm_redirect('account/?open=twostep');
        }
        $_SESSION['cm_tfa_setup'] = ['method' => $want, 'secret' => cm_totp_secret_new()];
        if ($want === 'email') {
            cm_tfa_email_send($user);
            cm_flash('We have emailed you a code to check the address works.');
        }
        cm_redirect('account/?open=twostep');
    }

    if ($action === 'confirm') {
        $setup = $_SESSION['cm_tfa_setup'] ?? null;
        if (!$setup) {
            cm_flash('That setup expired. Start again.', 'warn');
            cm_redirect('account/?open=twostep');
        }
        $code = trim((string) ($_POST['code'] ?? ''));
        $good = false;

        if ($setup['method'] === 'totp') {
            $good = cm_totp_check($setup['secret'], $code) !== null;
        } else {
            $good = cm_tfa_email_check($user, $code);
        }

        if (!$good) {




            cm_flash('That code did not match. '
                   . ($setup['method'] === 'totp'
                      ? 'Check you typed the whole key into the app, and that your phone\'s clock is set automatically.'
                      : 'Check the newest email — codes expire after ' . CM_TFA_EMAIL_TTL . ' minutes.'), 'error');
            cm_redirect('account/?open=twostep');
        } else {



            $codes = cm_tfa_backup_new();
            cm_q('UPDATE account SET tfa_method = ?, tfa_secret = ?, tfa_enrolled_at = ?, tfa_last_step = 0
                  WHERE id = ?', [
                $setup['method'],
                $setup['method'] === 'totp' ? cm_encrypt($setup['secret']) : '',
                cm_now(),
                (int) $user['id'],
            ]);
            cm_tfa_backup_store((int) $user['id'], $codes);
            cm_credential_mark_refresh((int) $user['id']);
            unset($_SESSION['cm_tfa_setup']);
            cm_audit('tfa.enabled', 'account', (int) $user['id'], 'two-step sign-in on, method ' . $setup['method']);

            $_SESSION['cm_tfa_show_codes'] = $codes;
            cm_flash('Two-step sign-in is on. Save the backup codes below — this is the only time they are shown.');
            cm_redirect('account/?open=twostep');
        }
    }

    if ($action === 'newcodes') {
        $fresh = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);
        $hasPassword = (string) ($fresh['password_hash'] ?? '') !== '';
        $passOk = !$hasPassword || cm_password_verify_account($fresh, (string) ($_POST['password'] ?? ''));
        if (!$passOk || !cm_tfa_verify($fresh, trim((string) ($_POST['code'] ?? '')))) {
            cm_rate_record($manageBucket, $manageKey);
            cm_flash(($hasPassword ? 'Enter your password and a current code' : 'Enter a current code')
                   . ' to make new backup codes. Nothing was changed.', 'error');
            cm_redirect('account/?open=twostep');
        }
        cm_rate_clear($manageBucket, $manageKey);
        cm_credential_mark_refresh((int) $user['id']);
        $codes = cm_tfa_backup_new();
        cm_tfa_backup_store((int) $user['id'], $codes);
        cm_audit('tfa.backup_regenerated', 'account', (int) $user['id'], 'a new set of backup codes was generated');
        $_SESSION['cm_tfa_show_codes'] = $codes;
        cm_flash('New backup codes. The old ones no longer work.');
        cm_redirect('account/?open=twostep');
    }

    if ($action === 'off') {




        $fresh = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);
        if (!cm_tfa_verify($fresh, trim((string) ($_POST['code'] ?? '')))) {
            cm_rate_record($manageBucket, $manageKey);
            cm_flash('Enter a current code (or a backup code) to turn two-step sign-in off.', 'error');
            cm_redirect('account/?open=twostep');
        } else {
            cm_rate_clear($manageBucket, $manageKey);
            cm_tfa_disable((int) $user['id'], (string) $user['email']);
            cm_credential_mark_refresh((int) $user['id']);
            cm_flash('Two-step sign-in is off. Your password is now the only thing protecting this account.', 'warn');
            cm_redirect('account/?open=twostep');
        }
    }
}





























cm_redirect('account/?open=twostep');
