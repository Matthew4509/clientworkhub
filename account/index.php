<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/tfa.php';

$user = cm_require_login('/account/');
$user = cm_row('SELECT * FROM account WHERE id = ?', [(int) $user['id']]);


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    if (isset($_POST['forget_device'])) {
        $id = (int) $_POST['forget_device'];

        cm_q('DELETE FROM remember_token WHERE id = ? AND account_id = ?', [$id, (int) $user['id']]);
        cm_audit('remember.revoked', 'account', (int) $user['id'], 'a remembered device was signed out');
        cm_flash('That device has been signed out. Next time it will need the password'
               . (cm_tfa_on($user) ? ' and a code.' : '.'));
        cm_redirect('account/');
    }
    if (isset($_POST['forget_all'])) {
        cm_remember_revoke_all((int) $user['id']);
        cm_audit('remember.revoked_all', 'account', (int) $user['id'], 'all remembered devices signed out');
        cm_flash('Every remembered device has been signed out.');
        cm_redirect('account/');
    }
}

$devices = [];
try {
    $devices = cm_all('SELECT * FROM remember_token WHERE account_id = ? AND expires_at > ? ORDER BY created_at DESC',
                      [(int) $user['id'], cm_now()]);
} catch (Throwable $ex) {

}

cm_head('My account', '', true);
cm_account_nav('home', 'My account');
cm_render_flashes();
?>

<div class="card">
  <div class="mini-grid">
    <div class="mini"><span class="n"><?= e((string) $user['name']) ?></span> name</div>
    <div class="mini"><span class="n mono t-ui"><?= e((string) $user['email']) ?></span> email</div>
    <div class="mini"><span class="n"><?= $user['last_login_at'] ? e(date('j M Y, g:ia', strtotime((string) $user['last_login_at']))) : 'now' ?></span> last signed in</div>
    <div class="mini"><span class="n"><?= cm_tfa_on($user) ? 'On' : 'Off' ?></span> two-step sign-in</div>
  </div>
  <?php if (!cm_tfa_on($user)): ?>
    <div class="note note-warn mt-14">
      <b>Two-step sign-in is off.</b> This application holds the passwords to
      every account you manage for every client — a password on its own is one
      reused password away from all of it.
      <a href="<?= e(cm_url('account/security.php')) ?>">Turn it on</a>.
    </div>
  <?php endif; ?>
</div>

<?php 




























 ?>
<div class="set-tiles mb-16">
  <?php $tfaOn = cm_tfa_on($user); ?>
  <button class="set-tile<?= $tfaOn ? ' is-ready' : '' ?>" type="button" data-modal-open="dlg-twostep">
    <span class="set-tile-icon" aria-hidden="true"><?= $tfaOn ? '&#10003;' : '2' ?></span>
    <span class="set-tile-name">Two-step sign-in</span>
    <?php 
 ?>
    <span class="set-tile-blurb">
      <?php if ($tfaOn): ?>
        <b>On.</b> A code is asked for as well as your password.
        Manage it, or print new backup codes.
      <?php else: ?>
        <b>Off.</b> Your password is the only thing between a stranger and
        every client password you hold. Turn it on.
      <?php endif; ?>
    </span>
  </button>

  <button class="set-tile" type="button" data-modal-open="dlg-password">
    <span class="set-tile-icon" aria-hidden="true">&#8226;&#8226;&#8226;</span>
    <span class="set-tile-name">Password</span>
    <span class="set-tile-blurb">
      Change the password you sign in with. Changing it signs out every
      remembered device below.
    </span>
  </button>
</div>

<?php 









 ?>
<script defer src="<?= e(cm_url('js/credential-tools.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<dialog class="modal" id="dlg-password"<?= (string) ($_GET['open'] ?? '') === 'password' ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Change my password</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Every remembered device is signed out when it
    changes, including this one.</p>
    <form method="post" action="<?= e(cm_url('account/password.php')) ?>">
      <?= cm_csrf_field() ?>
      <?php 












 ?>
      <div class="row">
        <div class="lbl-row">
          <label class="lbl" for="current">Current password</label>
          <span class="spacer"></span>
          <button class="pw-eye" type="button" data-toggle-for="current"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
        </div>
        <input type="password" id="current" name="current" class="f-full"
               autocomplete="current-password" required>
      </div>
      <div class="row">
        <div class="lbl-row">
          <label class="lbl" for="password">New password</label>
          <span class="spacer"></span>
          <button class="pw-eye" type="button" data-toggle-for="password"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
        </div>
        <input type="password" id="password" name="password" class="f-full"
               autocomplete="new-password" required>
        <div class="actions mt-8">
          <button class="btn btn-sm btn-quiet" type="button" data-generate-for="password">Generate a strong one</button>
        </div>
      </div>
      <div class="row">
        <div class="lbl-row">
          <label class="lbl" for="password2">New password again</label>
          <span class="spacer"></span>
          <button class="pw-eye" type="button" data-toggle-for="password2"
                  aria-label="Show" title="Show" aria-pressed="false"><?= cm_icon_eye() ?></button>
        </div>
        <input type="password" id="password2" name="password2" class="f-full"
               autocomplete="new-password" required>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Change my password</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 























 ?>
<?php
$tfaSetup  = $_SESSION['cm_tfa_setup'] ?? null;
$showCodes = $_SESSION['cm_tfa_show_codes'] ?? null;
unset($_SESSION['cm_tfa_show_codes']);
$tfaMethod = cm_tfa_method($user);
$openTwo   = $showCodes || (string) ($_GET['open'] ?? '') === 'twostep';
?>
<dialog class="modal modal-wide" id="dlg-twostep"<?= $openTwo ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>Two-step sign-in</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">For <b><?= e((string) $user['email']) ?></b>.</p>

    <?php if ($showCodes): ?>
      <div class="note note-warn">
        <b>Save these now — they are not shown again.</b> Each one signs you in
        once if you lose your phone or the email does not arrive. Print them, or
        put them in a password manager. Do not keep them only on the phone that
        generates your codes.
      </div>
      <div class="mini-grid mt-12">
        <?php foreach ($showCodes as $c): ?>
          <div class="mini"><span class="mono t-body"><?= e($c) ?></span></div>
        <?php endforeach; ?>
      </div>
      <div class="modal-foot">
        <button class="btn" type="button" data-modal-close>I have saved them</button>
      </div>

    <?php elseif ($tfaMethod !== ''): ?>
      <p class="muted small">
        <b>On</b> since <?= e(date('j M Y', strtotime((string) $user['tfa_enrolled_at']))) ?>,
        by <?= e(cm_tfa_methods()[$tfaMethod]['label']) ?>. You have
        <b><?= cm_tfa_backup_left($user) ?></b>
        backup code<?= cm_tfa_backup_left($user) === 1 ? '' : 's' ?> left.
      </p>

      <form class="mt-12" method="post" action="<?= e(cm_url('account/security.php')) ?>">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="newcodes">
        <h3 class="work-h">New backup codes</h3>
        <p class="muted small">The old codes stop working. This asks for the
        same proof as signing in.</p>
        <?php if ((string) ($user['password_hash'] ?? '') !== ''): ?>
          <div class="row">
            <label class="lbl" for="nc-pass">Your password</label>
            <input type="password" id="nc-pass" name="password" autocomplete="current-password" required>
          </div>
        <?php endif; ?>
        <div class="row">
          <label class="lbl" for="nc-code">Current code, or a backup code</label>
          <input type="text" id="nc-code" name="code" class="mono" inputmode="numeric"
                 autocomplete="one-time-code" required maxlength="20">
        </div>
        <button class="btn btn-sm btn-quiet" type="submit">Generate a new set of backup codes</button>
      </form>

      <h3 class="work-h mt-22">Turn it off</h3>
      <p class="muted small">You need a current code to do this — otherwise
      anyone who found this window already signed in could simply switch the
      protection off.</p>
      <form method="post" action="<?= e(cm_url('account/security.php')) ?>"
            data-confirm="Turn two-step sign-in off? Your password becomes the only thing protecting this account.">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="off">
        <div class="row">
          <label class="lbl" for="offcode">Current code, or a backup code</label>
          <input type="text" id="offcode" name="code" class="mono" inputmode="numeric" required maxlength="20">
        </div>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="submit">Turn off two-step sign-in</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </form>

    <?php elseif ($tfaSetup): ?>
      <h3 class="work-h">Finish setting it up</h3>
      <?php if ($tfaSetup['method'] === 'totp'): ?>
        <p class="muted small">
          In <b>Microsoft Authenticator</b>, <b>Google Authenticator</b>,
          <b>Authy</b> or <b>1Password</b>: add an account, choose
          <b>work or school</b> / <b>scan a QR code</b>, and point the camera at this.
        </p>
        <div class="my-14">
          <?php 


 ?>
          <?= cm_qr_svg(cm_totp_uri((string) $user['email'], $tfaSetup['secret']), 5) ?>
        </div>
        <p class="muted small"><b>Cannot scan it?</b> Choose “enter a key
        manually” in the app and type:</p>
        <p class="mono key-out"><?= e(cm_totp_pretty($tfaSetup['secret'])) ?></p>
        <p class="hint">The account name will show as
        <span class="mono">Client Work Hub</span>. If codes are rejected, check
        the phone's clock is set automatically — that is nearly always the cause.</p>
      <?php else: ?>
        <p class="muted small">
          We have sent a six-digit code to <b><?= e((string) $user['email']) ?></b>.
          Type it below to confirm the address works. Codes expire after
          <?= CM_TFA_EMAIL_TTL ?> minutes.
        </p>
      <?php endif; ?>

      <form class="mt-14" method="post" action="<?= e(cm_url('account/security.php')) ?>">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="confirm">
        <div class="row">
          <label class="lbl" for="code">
            <?= $tfaSetup['method'] === 'totp' ? 'The code your app is showing now' : 'The code from the email' ?>
          </label>
          <input type="text" id="code" name="code" class="mono f-code" inputmode="numeric"
                 autocomplete="one-time-code" required maxlength="10" placeholder="000000">
        </div>
        <p class="hint">Nothing has been changed on your account yet. It is
        switched on only when a code you typed actually matches.</p>
        <div class="modal-foot">
          <button class="btn" type="submit">Confirm and turn it on</button>
          <?php 

 ?>
          <a class="btn btn-quiet" href="<?= e(cm_url('account/security.php?cancel=1')) ?>">Cancel setup</a>
        </div>
      </form>

    <?php else: ?>
      <?php if (cm_is_staff($user)): ?>
        <div class="note note-warn">
          <b>This account holds the keys.</b> Every client's stored sign-in
          details — their websites, their social accounts, their hosting — and
          the button that downloads the whole database. A password on its own is
          one leaked reused password away from all of it. <b>Turning this on is
          strongly recommended</b> — it is not compulsory, and nothing here will
          nag you again.
        </div>
      <?php endif; ?>
      <p class="muted small mb-14">With this on, signing in needs your password
      <b>and</b> a code. Someone who learns your password still cannot get in.</p>

      <form method="post" action="<?= e(cm_url('account/security.php')) ?>">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="start">
        <div class="caps-pick">
          <?php foreach (cm_tfa_methods() as $key => $m):
              if ($key === 'email' && cm_home()) continue;
              $off = $key === 'email' && !cm_mail_enabled(); ?>
            <label class="caps-row">
              <input type="radio" name="method" value="<?= e($key) ?>"
                     <?= $key === 'totp' ? 'checked' : '' ?> <?= $off ? 'disabled' : '' ?>>
              <span>
                <b><?= e($m['label']) ?><?= $key === 'totp' ? ' — recommended' : '' ?></b>
                <span class="muted small d-block"><?= e($m['blurb']) ?><?=
                  $off ? ' (email is not switched on for this site yet)' : '' ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="hint mt-12">You will be shown <b>ten backup codes</b> at the
        end. They are what gets you back in if you lose your phone — there is no
        other way, and nobody here can bypass it for you.</p>
        <div class="modal-foot">
          <button class="btn" type="submit">Set it up</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</dialog>

<div class="card">
  <h2>Remembered devices</h2>
  <p class="muted small">Browsers where you ticked “keep me signed in”. Each
  signs straight in — password and code included — until it expires or you
  sign it out here.</p>

  <?php if (!$devices): ?>
    <p class="muted small mt-10">None. Every sign-in on every
    device asks for the password<?= cm_tfa_on($user) ? ' and a code' : '' ?>.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Browser</th><th>Remembered</th><th>Expires</th><th class="num"></th></tr></thead>
        <tbody>
        <?php foreach ($devices as $d): ?>
          <tr>
            <td class="small"><?= e((string) $d['user_agent'] ?: 'unknown browser') ?></td>
            <td class="small"><?= e(date('j M Y', strtotime((string) $d['created_at']))) ?></td>
            <td class="small"><?= e(date('j M Y', strtotime((string) $d['expires_at']))) ?></td>
            <td class="num">
              <form method="post" class="inline">
                <?= cm_csrf_field() ?>
                <button class="btn btn-sm btn-quiet" name="forget_device" value="<?= (int) $d['id'] ?>">Sign it out</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form class="mt-10" method="post"
          data-confirm="Sign out every remembered device, including this one if it is remembered?">
      <?= cm_csrf_field() ?>
      <button class="btn btn-sm btn-quiet" name="forget_all" value="1">Sign out everywhere</button>
    </form>
  <?php endif; ?>
</div>
<?php cm_foot(); ?>
