<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/password.php';
require_once CM_LIB . '/view.php';

$token   = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$account = cm_token_check($token, 'reset');
$errors  = [];

if ($account !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password2'] ?? '');

    $problem = cm_password_problem($password, (string) $account['email']);
    if ($problem !== '') {
        $errors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $errors['password2'] = 'Those two do not match.';
    } elseif (!cm_token_spend((int) $account['_token_id'])) {

        $errors['password'] = 'That link has just been used. Please request a new one.';
    } else {
        cm_q('UPDATE account SET password_hash = ?, email_verified = COALESCE(email_verified, ?) WHERE id = ?',
             [cm_password_hash($password), cm_now(), (int) $account['id']]);



        cm_q('UPDATE auth_token SET used_at = ? WHERE account_id = ? AND used_at IS NULL',
             [cm_now(), (int) $account['id']]);







        require_once CM_LIB . '/remember.php';
        cm_remember_revoke_all((int) $account['id']);

        cm_audit('password.reset', 'account', (int) $account['id'], 'password changed via emailed link');

        cm_login((int) $account['id']);
        cm_q('UPDATE account SET last_login_at = ? WHERE id = ?', [cm_now(), (int) $account['id']]);

        cm_flash('Your password has been changed, and you are signed in.');
        cm_redirect('admin/');
    }
}

cm_head('Choose a new password', '', true);
cm_render_flashes();
?>
<script defer src="<?= e(cm_url('js/credential-tools.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php if ($account === null): ?>
  <h1>That link did not work</h1>
  <div class="card">
    <div class="note note-warn m-0">
      <b>Reset links work once and expire after an hour.</b>
      <p>This one has been used, has expired, or was not copied in full. Your
      old password still works — nothing has changed.</p>
    </div>
    <div class="actions">
      <a class="btn" href="<?= e(cm_url('account/forgot.php')) ?>">Send me a new link</a>
      <a class="btn btn-quiet" href="<?= e(cm_url('account/login.php')) ?>">Back to sign in</a>
    </div>
  </div>
<?php else: ?>
  <h1>Choose a new password</h1>
  <p class="sub">For <span class="mono"><?= e((string) $account['email']) ?></span></p>
  <div class="card">
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <?php 







 ?>
      <div class="row">
        <div class="lbl-row">
          <label class="lbl" for="password">New password</label>
          <span class="small" data-strength-for="password"></span>
          <span class="spacer"></span>
          <button class="pw-eye" type="button" data-toggle-for="password"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
        </div>
        <input type="password" id="password" name="password" data-strength-meter
               autocomplete="new-password" minlength="<?= CM_PASSWORD_MIN ?>" required autofocus>
        <button class="btn btn-sm btn-quiet" type="button" data-generate-for="password">Generate a strong one</button>
        <?= cm_field_error($errors, 'password') ?>
        <div class="hint">At least <?= CM_PASSWORD_MIN ?> characters.</div>
      </div>
      <div class="row">
        <div class="lbl-row">
          <label class="lbl" for="password2">And again</label>
          <span class="spacer"></span>
          <button class="pw-eye" type="button" data-toggle-for="password2"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
        </div>
        <input type="password" id="password2" name="password2"
               autocomplete="new-password" minlength="<?= CM_PASSWORD_MIN ?>" required>
        <?= cm_field_error($errors, 'password2') ?>
      </div>
      <div class="actions">
        <button class="btn" type="submit">Save my new password</button>
      </div>
    </form>
  </div>
<?php endif; ?>
<?php cm_foot(); ?>
