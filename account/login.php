<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













declare(strict_types=1);

const CM_DEMO_SIGNIN_PAGE = true;
require __DIR__ . '/../lib/bootstrap.php';


require_once CM_LIB . '/remember.php';
require_once CM_LIB . '/view.php';


if (cm_current_user()) {
    cm_redirect('admin/');
}



$returnTo = cm_safe_return($_GET['return'] ?? ($_POST['return'] ?? ''));
$afterLogin = $returnTo !== '' ? ltrim($returnTo, '/') : 'admin/';

$errors = [];
$email  = '';


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['password_login'])) {
    cm_require_csrf();

    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = cm_client_ip_bucket();
    $pair     = $ip . '|' . $email;








    $tooMany = cm_rate_exceeded('login-pair', $pair, 8, 15)
            || cm_rate_exceeded('login-ip', $ip, 30, 15);
    if (!$tooMany && $email !== '' && cm_rate_exceeded('login', $email, 40, 15)) {
        sleep(2);
    }

    $inside = true;
    if ($tooMany) {
        header('Retry-After: 900');
        $errors['email'] = 'Too many attempts. Wait fifteen minutes and try again, '
                         . 'or use the “forgot your password” link below.';
    } elseif ($email === '' || $password === '') {
        $errors['email'] = 'Enter your email address and password.';
    } else {
        cm_rate_record('login', $email);
        $inside = cm_rate_within('login-pair', $pair, 8, 15);
        $inside = cm_rate_within('login-ip', $ip, 30, 15) && $inside;
    }
    if (!$tooMany && $email !== '' && $password !== '' && !$inside) {


        header('Retry-After: 900');
        $errors['email'] = 'Too many attempts. Wait fifteen minutes and try again, '
                         . 'or use the “forgot your password” link below.';
    } elseif (!$tooMany && $email !== '' && $password !== '') {

        $account = cm_row('SELECT * FROM account WHERE email = ?', [$email]);


        if ($account && !empty($account['deleted_at'])) $account = null;

        if ($account && cm_password_verify_account($account, $password)) {
            cm_rate_clear('login-pair', $pair);
            cm_rate_clear('login-ip', $ip);
            cm_q('UPDATE account SET last_login_at = ? WHERE id = ?', [cm_now(), (int) $account['id']]);
            cm_login((int) $account['id']);








            if (!empty($_POST['remember'])) {
                if (isset($_SESSION['cm_tfa_pending'])) {
                    $_SESSION['cm_remember_wanted'] = true;
                } else {
                    cm_remember_issue((int) $account['id']);
                }
            }

            if (isset($_SESSION['cm_tfa_pending'])) {
                cm_redirect('account/two-factor.php'
                    . ($returnTo !== '' ? '?return=' . rawurlencode($returnTo) : ''));
            }
            cm_redirect($afterLogin);
        }


        $errors['password'] = 'That email address and password do not match an account.';
    }
}


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['dev_login'])) {
    cm_require_csrf();
    if (!cm_dev_login_allowed()) {
        http_response_code(403);
        exit('Dev login is disabled.');
    }
    $devEmail = strtolower(trim((string) ($_POST['dev_email'] ?? '')));
    if (!cm_email_valid($devEmail)) {
        cm_flash('Enter a valid email address for the test account.', 'error');
        cm_redirect('account/login.php');
    }
    $name = trim((string) ($_POST['dev_name'] ?? '')) ?: (string) strstr($devEmail, '@', true);
    $id = cm_account_upsert($devEmail, $name);
    cm_login($id);
    cm_flash('Signed in locally as ' . $devEmail . ' (development sign-in).', 'warn');
    cm_redirect($afterLogin);
}

cm_head('Sign in', '', true);
cm_render_flashes();
?>
<h1>Sign in</h1>

<?php 










 ?>
<?= cm_credential_tools_script_once() ?>

<div class="card mw-28">
  <form method="post">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">

    <div class="row">
      <label class="lbl" for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>"
             autocomplete="username" required autofocus>
      <?= cm_field_error($errors, 'email') ?>
    </div>

    <?php 
















 ?>
    <div class="row">
      <div class="lbl-row">
        <label class="lbl" for="password">Password</label>
        <span class="spacer"></span>
        <button class="pw-eye" type="button" data-toggle-for="password"
                aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
      </div>
      <input type="password" id="password" name="password"
             autocomplete="current-password" required>
      <?= cm_field_error($errors, 'password') ?>
    </div>

    <?php 

 ?>
    <label class="tick mb-6">
      <input type="checkbox" name="remember" value="1">
      <span>Keep me signed in on this device for <?= (int) CM_REMEMBER_DAYS ?> days</span>
    </label>
    <p class="hint mt-0 mb-14"><b>Not recommended</b>
    on a shared or public computer — this application holds your clients'
    credentials, and a remembered device signs straight in, code and all.</p>

    <div class="actions">
      <button class="btn" type="submit" name="password_login" value="1">Sign in</button>
      <a class="btn btn-quiet" href="<?= e(cm_url('account/forgot.php')) ?>">Forgot your password?</a>
    </div>
  </form>
</div>

<?php if (cm_dev_login_allowed()): ?>
  <div class="card card-warn">
    <h2>Development sign-in</h2>
    <p class="small muted">Local machine only — this block does not exist on
    the live site.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <div class="row">
        <label class="lbl" for="dev_email">Email</label>
        <input type="email" id="dev_email" name="dev_email" required
               placeholder="you@example.com" value="<?= e((string) cm_config('admin_emails.0', '')) ?>">
        <p class="hint">Use an address from <span class="mono">admin_emails</span> to test the admin area.</p>
      </div>
      <div class="row">
        <label class="lbl" for="dev_name">Display name</label>
        <input type="text" id="dev_name" name="dev_name" placeholder="Test User">
      </div>
      <button class="btn btn-quiet" type="submit" name="dev_login" value="1">Sign in locally</button>
    </form>
  </div>
<?php endif; ?>
<?php cm_foot(); ?>
