<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/terms.php';
require_once CM_LIB . '/migrate.php';

if (!cm_home() || (int) cm_val('SELECT COUNT(*) FROM account') > 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    cm_send_security_headers();
    exit("Not found\n");
}

$errors = [];
$values = ['name' => '', 'email' => ''];
$termsOk = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $values['name']  = trim((string) ($_POST['name'] ?? ''));
    $values['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');
    $termsOk   = ($_POST['terms'] ?? '') === cm_terms_version(true);
    $tz        = (string) ($_POST['tz'] ?? '');

    if ($values['name'] === '') {
        $errors['name'] = 'Please give a name.';
    } elseif (mb_strlen($values['name']) > 120) {
        $errors['name'] = 'That name is too long (120 characters at most).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190) {
        $errors['email'] = 'That does not look like an email address. It is only your sign-in name here; nothing is sent to it.';
    }
    if ($password !== $password2) {
        $errors['password'] = 'The two passwords are not the same.';
    } else {
        $problem = cm_password_problem($password, $values['email']);
        if ($problem !== '') $errors['password'] = $problem;
    }
    if (!in_array($tz, timezone_identifiers_list(), true)) {
        $errors['tz'] = 'Choose your time zone from the list.';
    }
    if (!$termsOk) {
        $errors['terms'] = 'Please tick the box to confirm you have read the terms above.';
    }

    if (!$errors && cm_migrations_pending()) {
        $run = cm_migration_run_pending();
        if (!$run['ok']) $errors['name'] = 'The database could not be prepared: ' . (string) $run['error'];
    }





    if (!$errors && @file_put_contents(CM_DATA . '/timezone.txt', $tz) === false) {
        $errors['tz'] = 'The time zone could not be saved in ' . CM_DATA . '.';
    }

    if (!$errors && (int) cm_val('SELECT COUNT(*) FROM account') === 0) {
        $id = cm_insert(
            "INSERT INTO account (email, name, password_hash, role, email_verified, created_at)
             VALUES (?, ?, ?, 'admin', ?, ?)",
            [$values['email'], $values['name'], cm_password_hash($password), cm_now(), cm_now()]);
        cm_audit('site.installed', 'account', $id, 'home edition set up by ' . $values['email']);
        cm_audit('terms.accepted', 'account', $id, 'terms version ' . cm_terms_version(true) . ' accepted by '
                 . $values['email'] . ' at first run');
        cm_setting_set('terms.accepted_version', cm_terms_version(true), 'first run');
        cm_setting_set('terms.accepted_at', (string) time(), 'first run');
        cm_setting_set('terms.accepted_by', $values['email'], 'first run');
        cm_login($id);
        cm_q('UPDATE account SET last_login_at = ? WHERE id = ?', [cm_now(), $id]);
        cm_flash('Welcome. Everything you add stays on this PC.');
        cm_redirect('admin/');
    }
    if (!$errors) cm_redirect('account/login.php');
}

cm_head('Welcome', '', true);
cm_render_flashes();
?>
<h1>Welcome to Client Work Hub</h1>
<p class="sub">It runs on this PC only. Make your account to begin: you will sign in with it each time you open
the application.</p>
<?= cm_credential_tools_script_once() ?>

<div class="card mw-28">
  <form method="post">
    <?= cm_csrf_field() ?>
    <div class="row">
      <label class="lbl" for="name">Your name</label>
      <input id="name" name="name" value="<?= e($values['name']) ?>" maxlength="120" required autofocus
             autocomplete="name">
      <?= cm_field_error($errors, 'name') ?>
    </div>
    <div class="row">
      <label class="lbl" for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" maxlength="190" required
             autocomplete="username">
      <div class="hint">Your sign-in name. Nothing is ever sent to it.</div>
      <?= cm_field_error($errors, 'email') ?>
    </div>
    <div class="row">
      <div class="lbl-row">
        <label class="lbl" for="password">Password</label>
        <span class="spacer"></span>
        <button class="pw-eye" type="button" data-toggle-for="password"
                aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
      </div>
      <input type="password" id="password" name="password" required autocomplete="new-password">
      <div class="hint">At least 10 characters. Three or four unrelated words are strong and easy to remember.
      Forgotten later? The Start menu has <b>Reset my password</b>.</div>
      <?= cm_field_error($errors, 'password') ?>
    </div>
    <div class="row">
      <label class="lbl" for="password2">Password again</label>
      <input type="password" id="password2" name="password2" required autocomplete="new-password">
    </div>

    <div class="row">
      <label class="lbl" for="tz">Your time zone</label>
      <select id="tz" name="tz" data-first-run-tz required>
        <?php $pickedTz = (string) ($_POST['tz'] ?? 'UTC'); ?>
        <?php foreach (timezone_identifiers_list() as $z): ?>
          <option value="<?= e($z) ?>"<?= $z === $pickedTz ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $z)) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">"Today", due dates and reminders all run on this clock. Taken from this PC; change it if it is wrong.</div>
      <?= cm_field_error($errors, 'tz') ?>
    </div>

    <h2 class="mt-16"><?= e(cm_terms_title(true)) ?></h2>
    <?php foreach (cm_terms_sections(true) as $s): ?>
      <p class="small mb-8"><b><?= e($s['heading']) ?>.</b> <?= e($s['body']) ?></p>
    <?php endforeach; ?>
    <div class="row">
      <label class="tick">
        <input type="checkbox" name="terms" value="<?= e(cm_terms_version(true)) ?>" <?= $termsOk ? 'checked' : '' ?> required>
        <span><?= e(cm_terms_confirm_label()) ?></span>
      </label>
      <?= cm_field_error($errors, 'terms') ?>
    </div>

    <button class="btn" type="submit">Create my account</button>
  </form>
</div>
<script defer src="<?= e(cm_url('js/first-run.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php cm_foot(); ?>
