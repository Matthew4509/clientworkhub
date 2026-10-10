<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0








declare(strict_types=1);

const CM_DEMO_SIGNIN_PAGE = true;
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/password.php';
require_once CM_LIB . '/tfa.php';

$user = cm_tfa_pending();
if (!$user) {


    cm_redirect('account/login.php');
}

$method = cm_tfa_method($user);





$return = cm_safe_return($_GET['return'] ?? '');
$error  = '';




if ($method === 'email' && !isset($_SESSION['cm_tfa_sent'])) {
    if (cm_tfa_email_send($user)) {
        $_SESSION['cm_tfa_sent'] = time();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();

    if (isset($_POST['resend']) && $method === 'email') {


        if (cm_rate_exceeded('tfa_send', (string) $user['email'], 5, 15)) {
            $error = 'Too many codes requested. Wait a few minutes, or use a backup code.';
        } else {
            cm_rate_record('tfa_send', (string) $user['email']);
            cm_tfa_email_send($user);
            cm_flash('A new code is on its way.');
            cm_redirect('account/two-factor.php' . ($return !== '' ? '?return=' . rawurlencode($return) : ''));
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));






        if (cm_rate_exceeded('tfa_try', (string) $user['email'], 10, 15)) {
            $error = 'Too many attempts. Wait fifteen minutes and try again.';
            cm_audit('tfa.throttled', 'account', (int) $user['id'], 'too many code attempts');
        } elseif ($code === '') {
            $error = 'Type the code to continue.';
        } else {
            cm_rate_record('tfa_try', (string) $user['email']);



            $fresh = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);
            if ($fresh && cm_tfa_verify($fresh, $code)) {
                cm_rate_clear('tfa_try', (string) $user['email']);
                unset($_SESSION['cm_tfa_sent']);
                cm_tfa_complete((int) $user['id']);
                cm_audit('tfa.passed', 'account', (int) $user['id'], 'signed in with ' . $method);







                if (!empty($_SESSION['cm_remember_wanted'])) {
                    unset($_SESSION['cm_remember_wanted']);
                    require_once CM_LIB . '/remember.php';
                    cm_remember_issue((int) $user['id']);
                }

                $left = cm_tfa_backup_left(cm_row('SELECT tfa_backup FROM account WHERE id = ?', [(int) $user['id']]));
                if ($left <= 2) {
                    cm_flash('You have ' . $left . ' backup code' . ($left === 1 ? '' : 's')
                           . ' left. Generate a new set from Security before you run out.', 'warn');
                }
                cm_redirect($return !== '' ? ltrim($return, '/') : 'admin/');
            }
            $error = 'That code was not right. Codes last about 30 seconds — wait for the next one and try again.';
        }
    }
}

cm_head('Two-step sign-in', '', true);
cm_render_flashes();
?>
<h1>One more step</h1>
<p class="sub">
  Signing in as <b><?= e((string) $user['email']) ?></b>.
  <?= $method === 'totp'
      ? 'Open your authenticator app and type the six-digit code it shows.'
      : 'We have emailed you a six-digit code.' ?>
</p>

<div class="card mw-26">
  <?php if ($error !== ''): ?>
    <div class="note note-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <?= cm_csrf_field() ?>
    <div class="row">
      <label class="lbl" for="code">Your code</label>
      <?php 

 ?>
      <input type="text" id="code" name="code" class="mono f-code" inputmode="numeric"
             autocomplete="one-time-code" autofocus required
             maxlength="20" placeholder="000000">
    </div>
    <button class="btn" type="submit">Sign in</button>
  </form>

  <?php if ($method === 'email'): ?>
    <form class="mt-10" method="post">
      <?= cm_csrf_field() ?>
      <button class="btn btn-sm btn-quiet" type="submit" name="resend" value="1">Send another code</button>
    </form>
  <?php endif; ?>

  <p class="hint">
    <b>Lost your phone, or the email is not arriving?</b> Type one of the
    <b>backup codes</b> you saved when you turned this on — they work here
    instead of a code, and each one works once.
  </p>
</div>

<p class="muted small mt-16">
  Not you? <a href="<?= e(cm_url('account/login.php')) ?>">Start again</a>.
  Somebody knowing your password is not enough to get in, which is the point of
  this page — but it is a good moment to change it.
</p>
<?php cm_foot(); ?>
