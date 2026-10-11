<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/password.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/mail.php';


$canDeliver = cm_mail_can_deliver();
$sent   = false;
$errors = [];
$email  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $ip    = cm_client_ip_bucket();
    $pair  = $ip . '|' . $email;

    if (cm_honeypot_tripped()) {
        $sent = true;
    } elseif (!cm_email_valid($email)) {
        $errors['email'] = 'That does not look like an email address.';
    } elseif (cm_rate_exceeded('reset-pair', $pair, 4, 60) || cm_rate_exceeded('reset-ip', $ip, 15, 60)
              || cm_rate_exceeded('reset', $email, 20, 60)) {





        $sent = true;
    } else {
        cm_rate_record('reset', $email);
        cm_rate_record('reset-pair', $pair);
        cm_rate_record('reset-ip', $ip);

        $account = cm_row('SELECT * FROM account WHERE email = ?', [$email]);



        if ($account && !empty($account['deleted_at'])) $account = null;
        if ($account) {



            $token = cm_token_issue((int) $account['id'], 'reset', 60);
            cm_mail_password_reset($account, cm_url('account/reset.php?token=' . $token));
        }
        $sent = true;
    }
}

cm_head('Reset your password', '', true);
cm_render_flashes();
?>
<h1>Reset your password</h1>

<?php if ($sent && !$canDeliver): ?>
  <?php 
 ?>
  <div class="card">
    <div class="note note-warn m-0">
      <b>This site cannot send email yet, so no link is on its way.</b>
      <p>Sending is switched off here. Your request was recorded and the reset
      link was saved on the server instead of being emailed — which means it
      cannot reach you.</p>
      <p class="small muted">Ask whoever runs this site to turn sending on
      under <b>Admin → Settings → Email</b>. They can also read the link out
      to you from <b>Admin → Email</b> in the meantime. Your current password
      still works, and nothing about your account has changed.</p>
    </div>
    <div class="actions">
      <a class="btn btn-quiet" href="<?= e(cm_url('account/login.php')) ?>">Back to sign in</a>
    </div>
  </div>
<?php elseif ($sent): ?>
  <div class="card">
    <div class="note note-ok m-0">
      <b>Check your email.</b>
      <p>If <span class="mono"><?= e($email) ?></span> has an account here, a
      reset link is on its way. It works once and expires in an hour.</p>
      <p class="small muted">Your current password keeps working until you use
      it. Nothing arrived? Check the spam folder.</p>
    </div>
    <div class="actions">
      <a class="btn btn-quiet" href="<?= e(cm_url('account/login.php')) ?>">Back to sign in</a>
    </div>
  </div>
<?php else: ?>
  <?php if (!$canDeliver): ?>
    <div class="note note-warn">
      <b>This site cannot send email yet.</b>
      <p>Sending is switched off, so a reset link cannot reach you. Ask
      whoever runs this site to turn it on under <b>Admin → Settings →
      Email</b>.</p>
    </div>
  <?php endif; ?>
  <p class="sub">We will email you a link. It works once and expires in an hour.</p>
  <div class="card">
    <form method="post">
      <?= cm_csrf_field() ?>
      <?php cm_honeypot_field(); ?>
      <div class="row">
        <label class="lbl" for="email">Your email address</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               autocomplete="email" required autofocus>
        <?= cm_field_error($errors, 'email') ?>
      </div>
      <div class="actions">
        <button class="btn" type="submit">Email me a link</button>
        <a class="btn btn-quiet" href="<?= e(cm_url('account/login.php')) ?>">Back to sign in</a>
      </div>
    </form>
  </div>
<?php endif; ?>
<?php cm_foot(); ?>
