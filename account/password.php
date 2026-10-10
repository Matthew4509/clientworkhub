<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0






















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';

$user = cm_require_login('/account/password.php');
$user = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();

    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['password'] ?? '');
    $again   = (string) ($_POST['password2'] ?? '');

    if (!cm_password_verify_account($user, $current)) {
        $errors['current'] = 'That is not the current password.';
    }
    $problem = cm_password_problem($new, (string) $user['email']);
    if ($problem !== '') {
        $errors['password'] = $problem;
    } elseif ($new !== $again) {
        $errors['password2'] = 'Those two do not match.';
    }





    if ($errors) {
        cm_flash(implode(' ', $errors), 'error');
        cm_redirect('account/?open=password');
    }

    if (!$errors) {
        cm_q('UPDATE account SET password_hash = ? WHERE id = ?',
             [cm_password_hash($new), (int) $user['id']]);
        cm_remember_revoke_all((int) $user['id']);
        cm_credential_mark_refresh((int) $user['id']);
        cm_audit('password.changed', 'account', (int) $user['id'], 'password changed from the account screen');
        cm_flash('Password changed. Every other browser and remembered device has been signed out.');
        cm_redirect('account/');
    }
}








cm_redirect('account/?open=password');
