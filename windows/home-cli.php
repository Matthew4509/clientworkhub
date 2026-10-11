<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



















declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/lib/bootstrap.php';
require_once CM_LIB . '/migrate.php';

$cmd = (string) ($argv[1] ?? '');

$out = static function (string $s): void { fwrite(STDOUT, $s . PHP_EOL); };
$err = static function (string $s): void { fwrite(STDERR, $s . PHP_EOL); };

if ($cmd === 'migrate') {
    $pending = cm_migrations_pending();
    if (!$pending) { $out('The database is up to date.'); exit(0); }
    $run = cm_migration_run_pending();
    if (!$run['ok']) { $err('A database update failed: ' . (string) $run['error']); exit(1); }
    $out(count($run['applied'] ?? []) . ' database update(s) applied.');
    exit(0);
}

if ($cmd === 'accounts') {
    foreach (cm_all('SELECT * FROM account ORDER BY id') as $a) {
        $out((string) $a['email'] . (cm_is_admin($a) ? ' admin' : ''));
    }
    exit(0);
}

if ($cmd === 'reset-password') {
    $email = strtolower(trim((string) ($argv[2] ?? '')));
    $accounts = cm_all('SELECT * FROM account ORDER BY id');
    if (!$accounts) { $err('There is no account yet. Open Client Work Hub to create one.'); exit(1); }
    if ($email === '') {
        if (count($accounts) > 1) { $err('More than one account: name the email address.'); exit(2); }
        $account = $accounts[0];
    } else {
        $account = null;
        foreach ($accounts as $a) if (strtolower((string) $a['email']) === $email) $account = $a;
        if ($account === null) { $err('No account has the email address ' . $email . '.'); exit(1); }
    }


    $password = rtrim((string) fgets(STDIN), "\r\n");
    if (str_starts_with($password, "\xEF\xBB\xBF")) $password = substr($password, 3);
    $problem = cm_password_problem($password, (string) $account['email']);
    if ($problem !== '') { $err($problem); exit(3); }

    $id = (int) $account['id'];
    cm_q('UPDATE account SET password_hash = ? WHERE id = ?', [cm_password_hash($password), $id]);
    require_once CM_LIB . '/tfa.php';
    cm_tfa_disable($id, 'reset on this PC');
    cm_q('UPDATE auth_token SET used_at = ? WHERE account_id = ? AND used_at IS NULL', [cm_now(), $id]);
    require_once CM_LIB . '/remember.php';
    cm_remember_revoke_all($id);
    cm_audit('password.reset', 'account', $id, 'password reset on this PC (home edition)');
    $out('The password for ' . (string) $account['email'] . ' has been changed. Two-step sign-in is off; '
       . 'turn it back on under Settings, My account.');
    exit(0);
}

$err('Usage: php windows/home-cli.php migrate | accounts | reset-password [email]');
exit(2);
