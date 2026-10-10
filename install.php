<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


































declare(strict_types=1);

if (PHP_SAPI === 'cli') { exit("The installer is a web page. Open /install.php in a browser.\n"); }

const CM_INSTALL_CLAIM_TTL = 1800;
const CM_INSTALL_COOKIE    = 'cm_install';

$CM_ROOT_DIR = __DIR__;
$configFile  = $CM_ROOT_DIR . '/config.php';
$storageDir  = $CM_ROOT_DIR . '/storage';
$claimFile   = $storageDir . '/install-claim.json';



function ins_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}





function ins_head(string $title): void
{
    header("Content-Security-Policy: default-src 'self'; "
         . "style-src 'self'; "
         . "font-src 'self'; "
         . "img-src 'self' data:; script-src 'none'; form-action 'self'; "
         . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= ins_e($title) ?> — Client Work Hub</title>
<link rel="icon" href="favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#1D4ED8">
<link rel="stylesheet" href="css/site.css">
</head>
<body>
<header class="site-head">
  <div class="wrap nav">
    <span class="brand"><img src="favicon.svg" alt="" width="28" height="28"><span>Client Work Hub</span></span>
  </div>
</header>
<main class="wrap page">
<?php
}

function ins_foot(): void
{
    ?>
</main>
<footer class="site-foot"><div class="wrap foot"><span>Client Work Hub installer</span></div></footer>
</body>
</html>
<?php
}



function ins_stop(string $title, string $html, int $status = 200): never
{
    http_response_code($status);
    ins_head($title);
    echo $html;
    ins_foot();
    exit;
}














define('CM_REQ_CALLER', 'install');
if (!is_file(__DIR__ . '/lib/requirements.php')) {
    ins_stop('Incomplete upload', <<<HTML
      <h1>Some of the application is missing</h1>
      <div class="note note-error">
        <b>lib/requirements.php is not on the server</b>, so the upload did not
        finish. Extract the release zip again in cPanel &rarr; File Manager,
        overwriting what is there, and reload this page.
      </div>
    HTML, 500);
}
require_once __DIR__ . '/lib/requirements.php';





if (!is_file(__DIR__ . '/lib/terms.php')) {
    ins_stop('Incomplete upload', <<<HTML
      <h1>Some of the application is missing</h1>
      <div class="note note-error">
        <b>lib/terms.php is not on the server</b>, so the upload did not finish.
        Extract the release zip again in cPanel &rarr; File Manager, overwriting
        what is there, and reload this page.
      </div>
    HTML, 500);
}
require_once __DIR__ . '/lib/terms.php';

const CM_INSTALL_MIN_PHP = CM_REQ_MIN_PHP;









$installed = false;
$dbUnanswered = '';
if (is_file($configFile)) {
    $cfg = @include $configFile;
    if (is_array($cfg)) {
        $db = $cfg['db'] ?? [];
        try {
            $pdo = new PDO(
                'mysql:host=' . ($db['host'] ?? 'localhost')
                . ';port=' . (int) ($db['port'] ?? 3306)
                . ';dbname=' . ($db['name'] ?? '')
                . ';charset=' . ($db['charset'] ?? 'utf8mb4'),
                (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
            );
            $installed = (int) $pdo->query('SELECT COUNT(*) FROM account')->fetchColumn() > 0;
        } catch (Throwable $ex) {
            $state = $ex instanceof PDOException ? (string) ($ex->errorInfo[0] ?? $ex->getCode()) : '';
            if ($state === '42S02') {
                $installed = false;
            } else {
                $dbUnanswered = $ex->getMessage();
            }
        }
    }
}

if ($dbUnanswered !== '') {
    ins_stop('Database unavailable', <<<HTML
      <h1>The database did not answer</h1>
      <div class="note note-warn">
        <b>The installer has stopped and changed nothing.</b>
        <p>config.php is in place, but the database it names could not be
        checked, so this site may already be installed. Try again in a minute.
        If it keeps happening, check the database name, user and password in
        <span class="mono">config.php</span> against cPanel &rarr; MySQL Databases.</p>
      </div>
    HTML, 503);
}

if ($installed) {


    ins_stop('Not found', <<<HTML
      <h1>Client Work Hub is already installed</h1>
      <div class="note note-warn">
        <b>This installer does nothing now, but it should not still be here.</b>
        <p>It normally deletes itself. If you are reading this, that did not
        work — delete <span class="mono">install.php</span> from the site's
        folder in cPanel → File Manager, or from Admin → Settings → Update →
        Cleanup.</p>
      </div>
      <p class="muted small">To sign in, go to
      <a href="account/login.php">/account/login.php</a>. If you have lost the
      admin password, use <b>Forgot password</b> there.</p>
    HTML, 404);
}





if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0750, true);
}
if (is_dir($storageDir) && !is_file($storageDir . '/.htaccess')) {
    @file_put_contents($storageDir . '/.htaccess',
        "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}
if (!is_dir($storageDir) || !is_writable($storageDir)) {
    ins_stop('Cannot continue', <<<HTML
      <h1>The site cannot write to its own folder</h1>
      <div class="note note-error">
        <b>Client Work Hub needs a writable folder called <span class="mono">storage</span>.</b>
        <p>It tried to create one and could not, which usually means the
        permissions on the site's folder do not allow it.</p>
        <p>In cPanel → File Manager, create a folder called
        <span class="mono">storage</span> next to this file, set its
        permissions to <b>0750</b>, and reload this page.</p>
      </div>
    HTML);
}



function ins_claim_read(string $file): ?array
{
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['token']) || empty($data['at'])) return null;
    if (time() - (int) $data['at'] > CM_INSTALL_CLAIM_TTL) return null;
    return $data;
}

$claim = ins_claim_read($claimFile);
$mine  = $claim !== null
      && isset($_COOKIE[CM_INSTALL_COOKIE])
      && hash_equals((string) $claim['token'], (string) $_COOKIE[CM_INSTALL_COOKIE]);

if ($claim === null) {
    $token = bin2hex(random_bytes(32));
    @file_put_contents($claimFile, json_encode(['token' => $token, 'at' => time()]), LOCK_EX);
    @chmod($claimFile, 0600);
    setcookie(CM_INSTALL_COOKIE, $token, [
        'expires'  => time() + CM_INSTALL_CLAIM_TTL,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    $claim = ['token' => $token, 'at' => time()];
    $mine  = true;
} elseif (!$mine) {
    ins_stop('Someone else is installing', <<<HTML
      <h1>An install is already under way</h1>
      <div class="note note-warn">
        <b>Another browser started installing this site in the last half hour.</b>
        <p>If that was you — in another tab, or on your phone — finish it there,
        or wait thirty minutes and reload this page.</p>
        <p><b>If it was not you, act now.</b> Whoever installs this site becomes
        its administrator. In cPanel → File Manager, delete
        <span class="mono">storage/install-claim.json</span> and reload this
        page. Only somebody with File Manager access can do that, so doing it
        always wins.</p>
      </div>
    HTML);
}





$phpOld  = !cm_req_php_ok();
$missing = cm_requirements_missing(true);
$absent  = cm_requirements_missing(false);
$cannot  = $phpOld || $missing;


$needConfig = !is_file($configFile);

$errors = [];




$termsOk = false;
$values = [
    'base_url'   => '',
    'db_host'    => 'localhost',
    'db_port'    => '3306',
    'db_name'    => '',
    'db_user'    => '',
    'db_pass'    => '',
    'admin_name' => '',
    'admin_email'=> '',
];




$guessScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
$guessDir    = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
$values['base_url'] = $guessScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $guessDir;


$done = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$cannot) {




    $posted = (string) ($_POST['cm_claim'] ?? '');
    if (!hash_equals((string) $claim['token'], $posted)) {
        ins_stop('That form expired', <<<HTML
          <h1>That form is out of date</h1>
          <div class="note note-error">
            <p>Reload this page and fill it in again. Nothing has been changed.</p>
          </div>
        HTML, 400);
    }

    foreach (array_keys($values) as $k) {
        if (isset($_POST[$k])) $values[$k] = trim((string) $_POST[$k]);
    }
    $values['db_pass']   = (string) ($_POST['db_pass'] ?? '');
    $values['admin_email'] = strtolower($values['admin_email']);
    $password  = (string) ($_POST['admin_password'] ?? '');
    $password2 = (string) ($_POST['admin_password2'] ?? '');










    $termsOk = ($_POST['terms'] ?? '') === CM_TERMS_VERSION;
    if (!$termsOk) {
        $errors['terms'] = 'Please tick the box to confirm you have read the terms above.';
    }



    if ($values['admin_name'] === '') {
        $errors['admin_name'] = 'Please give a name.';
    } elseif (mb_strlen($values['admin_name']) > 120) {
        $errors['admin_name'] = 'That name is too long (120 characters maximum).';
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['admin_email']) > 190) {
        $errors['admin_email'] = 'That does not look like an email address.';
    }


    if (mb_strlen($password) < 10) {
        $errors['admin_password'] = 'Use at least 10 characters.';
    } elseif ($password !== $password2) {
        $errors['admin_password'] = 'The two passwords are not the same.';
    }

    if ($needConfig) {
        if (!preg_match('#^https?://[^/\s]+#', $values['base_url'])) {
            $errors['base_url'] = 'Start with https:// and your domain, with no trailing slash.';
        }
        $values['base_url'] = rtrim($values['base_url'], '/');
        if ($values['db_name'] === '') $errors['db_name'] = 'Required.';
        if ($values['db_user'] === '') $errors['db_user'] = 'Required.';
        if ($values['db_host'] === '') $errors['db_host'] = 'Required — usually localhost.';
        if (!ctype_digit($values['db_port']) || (int) $values['db_port'] < 1 || (int) $values['db_port'] > 65535) {
            $errors['db_port'] = 'A number, usually 3306.';
        }



        if (!$errors) {
            try {
                $probe = new PDO(
                    "mysql:host={$values['db_host']};port=" . (int) $values['db_port']
                    . ";dbname={$values['db_name']};charset=utf8mb4",
                    $values['db_user'], $values['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
                );


                $probe->exec('CREATE TABLE IF NOT EXISTS cm_install_probe (id INT)');
                $probe->exec('DROP TABLE cm_install_probe');
                $probe = null;
            } catch (Throwable $ex) {
                $msg = $ex->getMessage();
                $errors['db_name'] = str_contains($msg, 'Access denied')
                    ? 'The database refused that username or password. In cPanel → MySQL Databases, check the '
                    . 'user is added to this database with ALL PRIVILEGES — and remember cPanel puts your account '
                    . 'name in front of both, like myaccount_clients.'
                    : (str_contains($msg, 'Unknown database')
                        ? 'There is no database with that name. cPanel usually prefixes it with your account name.'
                        : (str_contains($msg, 'command denied')
                            ? 'That user can read the database but not create tables. In cPanel → MySQL Databases → '
                            . 'Add User To Database, tick ALL PRIVILEGES.'
                            : 'Could not connect: ' . $msg));
            }
        }
    }


    $configWritten = false;
    $configSource  = '';
    if (!$errors && $needConfig) {
        $configSource = ins_config_source($values);
        if (@file_put_contents($configFile, $configSource, LOCK_EX) === false) {
            $errors['config'] = 'could not write';
        } else {
            @chmod($configFile, 0640);


            if (function_exists('opcache_invalidate')) @opcache_invalidate($configFile, true);
            $configWritten = true;
            $needConfig    = false;
        }
    }






    if (!$errors) {
        require __DIR__ . '/lib/bootstrap.php';
        require_once CM_LIB . '/migrate.php';

        $problem = cm_password_problem($password, $values['admin_email']);
        if ($problem !== '') {
            $errors['admin_password'] = $problem;
        }

        if (!$errors) {
            $run = cm_migration_run_pending();
            if (!$run['ok']) {
                $errors['migrate'] = $run['error'];
            }
        }

        if (!$errors) {









            if ((int) cm_val('SELECT COUNT(*) FROM account') > 0) {
                ins_stop('Not found', <<<HTML
                  <h1>Client Work Hub is already installed</h1>
                  <div class="note note-warn">
                    <b>Nothing was changed.</b> An account already exists, so the
                    installer will not create or alter one. Delete
                    <span class="mono">install.php</span> from the site's folder.
                  </div>
                  <p class="muted small">To sign in, go to
                  <a href="account/login.php">/account/login.php</a>. If you have
                  lost the admin password, use <b>Forgot password</b> there.</p>
                HTML, 404);
            }
            $accountId = cm_insert(
                'INSERT INTO account (email, name, password_hash, email_verified, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$values['admin_email'], $values['admin_name'],
                 cm_password_hash($password), cm_now(), cm_now()]
            );

            cm_audit('site.installed', 'account', $accountId,
                     'installed by ' . $values['admin_email'] . ' via install.php');







            cm_audit('terms.accepted', 'account', $accountId,
                     'terms version ' . CM_TERMS_VERSION . ' accepted by '
                     . $values['admin_email'] . ' at install');
            cm_setting_set('terms.accepted_version', CM_TERMS_VERSION, 'install');
            cm_setting_set('terms.accepted_at', (string) time(), 'install');
            cm_setting_set('terms.accepted_by', $values['admin_email'], 'install');



            cm_login($accountId);

            @unlink($claimFile);
            $selfDeleted = @unlink(__FILE__);

            $done = [
                'email'        => $values['admin_email'],
                'selfDeleted'  => $selfDeleted,
                'applied'      => $run['applied'] ?? [],
                'configWritten'=> $configWritten,
            ];
        }
    }
}







function ins_config_source(array $v): string
{
    $x = static fn(string $s): string => var_export($s, true);


    $secret = bin2hex(random_bytes(32));

    $host = (string) parse_url($v['base_url'], PHP_URL_HOST);
    $from = 'no-reply@' . ($host !== '' ? $host : 'example.com');

    $stamp = gmdate('Y-m-d H:i') . ' UTC';

    return <<<PHP
<?php
/**
 * Client Work Hub — configuration.
 *
 * Written by install.php on {$stamp}. Safe to edit by hand.
 * Every option is listed and explained in config.sample.php.
 */
return [

    'env'      => 'prod',

    /* THE WORKING TIMEZONE. Every date and time in the application — the
       queue, the schedules, "today" — lives in this one. */
    'timezone' => 'Australia/Melbourne',

    /* No trailing slash. Every link in every email is built from this. */
    'base_url' => {$x($v['base_url'])},

    /* Generated at install. It encrypts everything stored as a secret —
       client credentials above all. CHANGING IT MAKES THOSE UNREADABLE.
       Keep this file in your backups. */
    'secret_key' => {$x($secret)},

    'db' => [
        'host'    => {$x($v['db_host'])},
        'port'    => {$v['db_port']},
        'name'    => {$x($v['db_name'])},
        'user'    => {$x($v['db_user'])},
        'pass'    => {$x($v['db_pass'])},
        'charset' => 'utf8mb4',
    ],

    /* THE ROOT OF ADMIN ACCESS. An address here is an administrator and no
       screen on the site can remove it — that is what stops anyone locking
       you out. Use an address you will keep. */
    'admin_emails' => [
        {$x($v['admin_email'])},
    ],

    /* 'from' MUST be an address on this domain or the mail fails SPF and
       lands in spam. Also editable in Admin -> Settings, which wins. */
    'mail' => [
        'from'      => {$x($from)},
        'from_name' => 'ClientWorkHub',
        'reply_to'  => {$x($v['admin_email'])},
    ],

    /* Only true behind a proxy you control that sets X-Forwarded-For. On
       plain cPanel this must stay false, or a visitor can forge their own IP
       and walk through every rate limit. */
    'trust_proxy' => false,

    'dev_login' => false,
];

PHP;
}



if ($done !== null) {
    ins_head('Installed');
    ?>
    <h1>Client Work Hub is installed</h1>
    <p class="sub">You are signed in as
      <span class="mono"><?= ins_e($done['email']) ?></span>, and that address
      is a permanent administrator.</p>

    <?php if (!$done['selfDeleted']): ?>
      <div class="note note-error">
        <b>Delete <span class="mono">install.php</span> now.</b>
        <p>It tried to remove itself and could not. Until it is gone it is the
        one file on this site that could hand it to somebody else. cPanel →
        File Manager → right-click <span class="mono">install.php</span> →
        Delete. Admin → Settings → Update → Cleanup can also do it.</p>
      </div>
    <?php else: ?>
      <div class="note note-ok">
        <b>The installer has deleted itself</b>, which is what should happen —
        it is not a page that should outlive the install.
      </div>
    <?php endif; ?>

    <div class="card">
      <h2>What just happened</h2>
      <ul class="tile-list">
        <?php if ($done['configWritten']): ?>
          <li><span class="mono">config.php</span> written, with a freshly generated encryption key</li>
        <?php endif; ?>
        <li><span class="mono">storage/</span> created and closed to the web</li>
        <?php if ($done['applied']): ?>
          <li><?= count($done['applied']) ?> database update<?= count($done['applied']) === 1 ? '' : 's' ?> applied — every table the application needs</li>
        <?php else: ?>
          <li>the database was already built — nothing needed running</li>
        <?php endif; ?>
        <li>your administrator account created and signed in</li>
      </ul>
    </div>

    <div class="card">
      <h2>Do these two things next</h2>
      <ol class="tile-list">
        <li><b>Turn on two-step sign-in</b> — <a href="account/security.php">Settings → My account → Two-step sign-in</a>.
            This application will hold the passwords to every account you
            manage for every client. A password on its own is one reused
            password away from all of it. <b>Save the ten backup codes
            somewhere that is not the phone generating them.</b></li>
        <li><b>Check the health screen</b> — <a href="admin/update.php">Admin → Settings → Update</a>
            answers every question somebody with server access would answer
            from a command line.</li>
      </ol>
      <p class="hint">Email settings are in <a href="admin/settings.php">Admin → Settings</a>.
      Nothing there is required to use the application — until email is on,
      anything it would send is spooled to a folder instead.</p>
    </div>

    <p><a class="btn" href="admin/">Open the dashboard</a></p>
    <?php
    ins_foot();
    exit;
}


ins_head('Install');
?>
<h1>Install Client Work Hub</h1>
<p class="sub">One form. It writes the configuration, builds the database and
makes your administrator account.</p>

<?php if ($phpOld): ?>
  <div class="note note-error">
    <b>This server is running PHP <?= ins_e(PHP_VERSION) ?>, and the application
    needs <?= ins_e(CM_INSTALL_MIN_PHP) ?> or newer.</b>
    <p>cPanel → MultiPHP Manager, set this domain to a newer PHP, then reload.</p>
  </div>
<?php endif; ?>

<?php if ($missing): ?>
  <div class="note note-error">
    <b>PHP is missing: <span class="mono"><?= ins_e(implode(', ', $missing)) ?></span></b>
    <p>cPanel → Select PHP Version → Extensions, tick those, then reload this
    page. Nothing works without them.</p>
  </div>
<?php endif; ?>

<?php if ($absent && !$cannot): ?>
  <div class="note note-warn">
    <b>You can install now. Some optional extensions are missing:
      <span class="mono"><?= ins_e(implode(', ', $absent)) ?></span></b>
    <p>Nothing here stops the install — the features that use them are simply
    unavailable until your host turns them on. The table below says which.</p>
  </div>
<?php endif; ?>

<?php

















$reqRows = cm_requirements_state();
$hostMsg = cm_requirements_host_message((string) ($_SERVER['HTTP_HOST'] ?? ''));
?>
<details class="why" <?= ($missing || $absent || $phpOld) ? 'open' : '' ?>>
  <summary>What this server can do<?= ($missing || $absent || $phpOld) ? '' : ' — everything, as it happens' ?></summary>

  <div class="table-wrap mt-8">
    <table>
      <thead><tr><th>Extension</th><th></th><th>What it is for</th><th>Without it</th></tr></thead>
      <tbody>
      <tr>
        <td class="mono">PHP <?= ins_e(PHP_VERSION) ?></td>
        <td><?= $phpOld ? '<b>too old</b>' : 'OK' ?></td>
        <td class="small">Running the application at all.</td>
        <td class="small"><?= $phpOld
              ? 'Needs ' . ins_e(CM_INSTALL_MIN_PHP) . ' or newer. cPanel → MultiPHP Manager,'
                . ' set THIS domain — PHP Selector only changes the account default.'
              : '<span class="muted">&mdash;</span>' ?></td>
      </tr>
      <?php foreach ($reqRows as $r): ?>
        <tr>
          <td class="mono"><?= ins_e($r['ext']) ?><br>
            <span class="muted small"><?= $r['required'] ? 'required' : 'optional' ?></span></td>
          <td><?= $r['have'] ? 'yes' : '<b>NO</b>' ?></td>
          <td class="small"><?= ins_e($r['for']) ?></td>
          <td class="small"><?= $r['have']
                ? '<span class="muted">&mdash;</span>' : ins_e($r['lost']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p class="muted small mt-8">
    Also needed, and not testable from this page: a MySQL or MariaDB database
    with a user holding <b>ALL PRIVILEGES</b> on it, and HTTPS on the domain.
    Nothing else — no Composer, no <span class="mono">mod_rewrite</span>, no
    shell access, no cron.
  </p>

  <?php if ($hostMsg !== ''): ?>
    <p class="mt-16"><b>Send this to your hosting company.</b> Everything missing
    above, written as a request — click in the box, select it all, and paste it
    into a support ticket or an email.</p>
    <textarea class="mono f-full" rows="18" readonly><?= ins_e($hostMsg) ?></textarea>
  <?php endif; ?>
</details>

<?php if (isset($errors['migrate'])): ?>
  <div class="note note-error">
    <b>The database updates stopped part-way.</b>
    <span class="mono small d-block mt-8 mb-8"><?= ins_e($errors['migrate']) ?></span>
    <p>Nothing after the failed file was run. The usual cause is a database
    user without full privileges — cPanel → MySQL Databases → Add User To
    Database, tick <b>ALL PRIVILEGES</b>, then reload this page. It will carry
    on from where it stopped.</p>
  </div>
<?php endif; ?>

<?php if (isset($errors['config'])): ?>
  <div class="note note-error">
    <b>The configuration file could not be written.</b>
    <p>The site's folder is not writable by PHP. Create
    <span class="mono">config.php</span> next to this file yourself — cPanel →
    File Manager → <b>+ File</b>, then right-click it and choose <b>Edit</b> —
    and paste in exactly this:</p>
    <textarea class="mono f-full" rows="16" readonly><?= ins_e($configSource) ?></textarea>
    <p>Save it, then reload this page: the installer will pick up from there.
    <b>Keep this text</b> — the encryption key in it is generated fresh on
    every attempt.</p>
  </div>
<?php endif; ?>

<?php if (!$needConfig): ?>
  <div class="note note-info">
    <b>There is already a <span class="mono">config.php</span> here</b>, so the
    installer will use it and only needs your administrator account below.
  </div>
<?php endif; ?>

<?php 


$fieldErrors = array_diff_key($errors, ['migrate' => 1, 'config' => 1]);
if ($fieldErrors):
?>
  <div class="note note-error">
    <b>Nothing has been installed — <?= count($fieldErrors) === 1
        ? 'one box needs fixing' : count($fieldErrors) . ' boxes need fixing' ?>.</b>
    <ul class="tile-list">
      <?php foreach ($fieldErrors as $msg): ?>
        <li><?= ins_e($msg) ?></li>
      <?php endforeach; ?>
    </ul>
    <span class="muted small">Both password boxes have been cleared, so type
    the password again — that is normal, not a fault.</span>
  </div>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="cm_claim" value="<?= ins_e((string) $claim['token']) ?>">

  <?php if ($needConfig): ?>
    <div class="card">
      <h2>1. Your site</h2>
      <div class="row">
        <label class="lbl" for="base_url">Web address</label>
        <input type="url" id="base_url" name="base_url" value="<?= ins_e($values['base_url']) ?>" required>
        <?php if (isset($errors['base_url'])): ?><div class="field-err"><?= ins_e($errors['base_url']) ?></div><?php endif; ?>
        <div class="hint">With <span class="mono">https://</span> and no
        trailing slash. Every link in every email is built from this.</div>
      </div>
    </div>

    <div class="card">
      <h2>2. Your database</h2>
      <p class="muted small">From cPanel → <b>MySQL® Databases</b>. cPanel puts
      your account name in front of both the database and the user, so they
      usually look like <span class="mono">myaccount_clients</span>. The
      user needs <b>ALL PRIVILEGES</b> — the application creates and alters its
      own tables.</p>

      <div class="row">
        <label class="lbl" for="db_name">Database name</label>
        <input type="text" id="db_name" name="db_name" value="<?= ins_e($values['db_name']) ?>" required autocomplete="off">
        <?php if (isset($errors['db_name'])): ?><div class="field-err"><?= ins_e($errors['db_name']) ?></div><?php endif; ?>
      </div>
      <div class="row">
        <label class="lbl" for="db_user">Database user</label>
        <input type="text" id="db_user" name="db_user" value="<?= ins_e($values['db_user']) ?>" required autocomplete="off">
        <?php if (isset($errors['db_user'])): ?><div class="field-err"><?= ins_e($errors['db_user']) ?></div><?php endif; ?>
      </div>
      <div class="row">
        <label class="lbl" for="db_pass">Database password</label>
        <input type="password" id="db_pass" name="db_pass" value="<?= ins_e($values['db_pass']) ?>" autocomplete="off">
      </div>
      <div class="row">
        <label class="lbl" for="db_host">Database host</label>
        <input type="text" id="db_host" name="db_host" value="<?= ins_e($values['db_host']) ?>" required autocomplete="off">
        <?php if (isset($errors['db_host'])): ?><div class="field-err"><?= ins_e($errors['db_host']) ?></div><?php endif; ?>
        <div class="hint">Nearly always <span class="mono">localhost</span> on cPanel.</div>
      </div>
      <div class="row">
        <label class="lbl" for="db_port">Port</label>
        <input type="text" id="db_port" name="db_port" value="<?= ins_e($values['db_port']) ?>" required autocomplete="off" inputmode="numeric">
        <?php if (isset($errors['db_port'])): ?><div class="field-err"><?= ins_e($errors['db_port']) ?></div><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= $needConfig ? '3. ' : '' ?>Your administrator account</h2>
    <p class="muted small">This account can read every client's stored
    credentials. There is no email confirmation step — installing the site is
    proof enough — so the password you choose here works immediately.</p>

    <div class="row">
      <label class="lbl" for="admin_name">Your name</label>
      <input type="text" id="admin_name" name="admin_name" value="<?= ins_e($values['admin_name']) ?>" maxlength="120" required autocomplete="name">
      <?php if (isset($errors['admin_name'])): ?><div class="field-err"><?= ins_e($errors['admin_name']) ?></div><?php endif; ?>
    </div>
    <div class="row">
      <label class="lbl" for="admin_email">Your email</label>
      <input type="email" id="admin_email" name="admin_email" value="<?= ins_e($values['admin_email']) ?>" required autocomplete="email">
      <?php if (isset($errors['admin_email'])): ?><div class="field-err"><?= ins_e($errors['admin_email']) ?></div><?php endif; ?>
      <div class="hint">This goes into <span class="mono">config.php</span> as
      a permanent administrator — <b>no screen on the site can remove it</b>,
      which is what stops anyone locking you out. Use an address you will keep.</div>
    </div>
    <div class="row">
      <label class="lbl" for="admin_password">Password</label>
      <input type="password" id="admin_password" name="admin_password" required autocomplete="new-password">
      <?php if (isset($errors['admin_password'])): ?><div class="field-err"><?= ins_e($errors['admin_password']) ?></div><?php endif; ?>
      <div class="hint">At least 10 characters. Three or four unrelated words
      beat one clever substitution.</div>
    </div>
    <div class="row">
      <label class="lbl" for="admin_password2">Password again</label>
      <input type="password" id="admin_password2" name="admin_password2" required autocomplete="new-password">
    </div>
  </div>

  <?php 








 ?>
  <div class="card mt-16">
    <h2><?= ins_e(cm_terms_title()) ?></h2>
    <?php foreach (cm_terms_sections() as $s): ?>
      <p class="mb-8"><b><?= ins_e($s['heading']) ?>.</b>
         <?= ins_e($s['body']) ?></p>
    <?php endforeach; ?>

    <div class="row mt-16">
      <label class="lbl" for="terms">
        <input type="checkbox" id="terms" name="terms" value="<?= ins_e(CM_TERMS_VERSION) ?>"
               <?= $termsOk ? 'checked' : '' ?> required>
        <?= ins_e(cm_terms_confirm_label()) ?>
      </label>
      <?php if (isset($errors['terms'])): ?><div class="field-err"><?= ins_e($errors['terms']) ?></div><?php endif; ?>
      <div class="hint">Kept on record with the date and your email address, so
      the site can always show which wording was agreed to — these words stay
      in <span class="mono">lib/terms.php</span> after the installer removes
      itself.</div>
    </div>
  </div>

  <button class="btn" type="submit" <?= $cannot ? 'disabled' : '' ?>>Install Client Work Hub</button>
  <p class="hint">This takes a few seconds. Nothing is sent anywhere — the
  installer talks only to your own database.</p>
</form>
<?php
ins_foot();
