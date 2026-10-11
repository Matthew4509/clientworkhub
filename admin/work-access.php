<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/workaccess.php';
require_once CM_LIB . '/linkexpiry.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';

$admin = cm_require_admin();
cm_schema_gate('work_access', 'settings');

$error  = '';
$sticky = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $a = (string) ($_POST['action'] ?? '');

    if ($a === 'create') {
        $r = cm_work_access_save($_POST, (string) $admin['email']);
        if (!$r['ok']) {
            $error  = (string) $r['error'];
            $sticky = $_POST;
        } else {
            cm_flash('Work screen created. Copy the link below.');
            cm_redirect('admin/work-access.php');
        }
    } elseif ($a === 'display') {
        cm_work_access_display_save((int) ($_POST['id'] ?? 0), $_POST, (string) $admin['email']);
        cm_flash('Screen display updated.');
        cm_redirect('admin/work-access.php');
    } elseif ($a === 'rotate') {
        cm_work_access_rotate((int) ($_POST['id'] ?? 0), (string) $admin['email']);
        cm_flash('Link rotated. The old URL stopped working.');
        cm_redirect('admin/work-access.php');
    } elseif ($a === 'status') {
        cm_work_access_status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''), (string) $admin['email']);
        cm_flash('Work screen updated.');
        cm_redirect('admin/work-access.php');
    }
}



$screens      = [];
$screensError = '';
try {
    $screens = cm_work_access_all();
} catch (Throwable $ex) {
    error_log('work screens list failed: ' . $ex->getMessage());
    $screensError = 'The list of work screens could not be read. This is a database fault, '
                  . 'not an empty list — check Update & backup, and the server error log.';
}

$stickyChecked = static function (string $key, bool $whenFresh) use ($sticky): bool {


    return $sticky === [] ? $whenFresh : isset($sticky[$key]);
};
$stickyAuth = $stickyChecked('auth_required', true);

cm_head('Work screens', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'work_access', 'Work screens');
?>
<p class="sub">Create a revocable staff or public-display screen, then choose exactly which
safe fields it shows.</p>

<?php if ($error !== ''): ?><div class="note note-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($screensError !== ''): ?><div class="note note-error"><?= e($screensError) ?></div><?php endif; ?>

<div class="page-grid">
  <div>
    <div class="card">
      <h2>Create a work screen</h2>
      <form method="post" data-workscreen-form>
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="form-2col">
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="wa-name">Screen name</label>
              <input id="wa-name" name="name" maxlength="120" required placeholder="Technician screen"
                     value="<?= e((string) ($sticky['name'] ?? '')) ?>">
              <div class="hint">Only admins see this. It never appears on the shared page.</div>
            </div>
          </div>
          <div class="form-col">
            <div class="row">
              <label class="lbl" for="wa-view">Feed</label>
              <select id="wa-view" name="screen_view">
                <?php foreach (cm_work_access_views() as $k => $v): ?>
                  <option value="<?= e($k) ?>"<?= ((string) ($sticky['screen_view'] ?? '')) === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="hint">The screen refreshes itself from this live feed.</div>
            </div>
          </div>
        </div>

        <?php 






 ?>
        <div class="card-subtle mt-12">
          <h3>Privacy &amp; access</h3>
          <label class="check"><input type="checkbox" name="auth_required" value="1"
                 data-auth-toggle<?= $stickyAuth ? ' checked' : '' ?>> Require sign-in</label>
          <p class="hint">Off means the URL is a public, read-only display &mdash; anyone
          with the link sees it, and nobody can start or complete work from it.</p>

          <div data-credential-fields<?= $stickyAuth ? '' : ' hidden' ?>>
            <div class="form-2col">
              <div class="form-col">
                <div class="row">
                  <label class="lbl" for="wa-user">Username</label>
                  <input id="wa-user" name="username" maxlength="80" autocomplete="off"
                         data-needs-signin value="<?= e((string) ($sticky['username'] ?? '')) ?>">
                </div>
              </div>
              <div class="form-col">
                <div class="row">
                  <label class="lbl" for="wa-pass">Passcode</label>
                  <input id="wa-pass" type="password" name="password" autocomplete="new-password"
                         data-needs-signin data-minlength="10">
                  <div class="hint">At least 10 characters; stored as a one-way hash.</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card-subtle mt-12">
          <h3>Display fields</h3>
          <div class="check-grid">
            <label class="check"><input type="checkbox" name="show_business_name" value="1"
                   data-preview="business"<?= $stickyChecked('show_business_name', false) ? ' checked' : '' ?>> Business name</label>
            <label class="check"><input type="checkbox" name="show_client_name" value="1"
                   data-preview="client"<?= $stickyChecked('show_client_name', false) ? ' checked' : '' ?>> Client first name</label>
            <label class="check"><input type="checkbox" name="show_job_name" value="1"
                   data-preview="job"<?= $stickyChecked('show_job_name', false) ? ' checked' : '' ?>> Job name</label>
            <label class="check"><input type="checkbox" name="show_estimate" value="1"
                   data-preview="estimate"<?= $stickyChecked('show_estimate', true) ? ' checked' : '' ?>> Estimated time</label>
            <label class="check"><input type="checkbox" name="highlight_vip" value="1"
                   data-preview="vip"<?= $stickyChecked('highlight_vip', false) ? ' checked' : '' ?>> Highlight VIP
              <span class="hint">Off by default. On a public queue this tells everyone in the
              room who is a priority customer.</span></label>
          </div>

          <div class="form-2col mt-10">
            <div class="row">
              <label class="lbl" for="wa-heading">Public heading <span class="muted">optional</span></label>
              <input id="wa-heading" name="public_heading" maxlength="120" placeholder="Now serving"
                     value="<?= e((string) ($sticky['public_heading'] ?? '')) ?>">
            </div>
            <div class="row">
              <label class="lbl" for="wa-max">Items shown</label>
              <input id="wa-max" type="number" name="max_items" min="1" max="100" required
                     value="<?= (int) ($sticky['max_items'] ?? 10) ?>">
            </div>
          </div>
        </div>

        <?php 









 ?>
        <div class="card-subtle mt-12">
          <h3>Expiry</h3>
          <?= cm_link_expiry_radios('expiry_days', null, true, 'No expiry') ?>
          <div class="row mt-10">
            <label class="lbl" for="wa-exp">Or a date of your own <span class="muted">optional</span></label>
            <input id="wa-exp" type="date" name="expires" min="<?= e(date('Y-m-d')) ?>"
                   value="<?= e((string) ($sticky['expires'] ?? '')) ?>">
            <div class="hint">A date here wins over the choice above. It cannot be in the past.</div>
          </div>
        </div>

        <div class="actions mt-16">
          <button class="btn" type="submit">Create work screen</button>
        </div>
      </form>
    </div>
  </div>

  <?php 



 ?>
  <aside class="side-rail">
    <div class="card">
      <h2>Preview</h2>
      <div class="wa-preview" data-workscreen-preview>
        <div class="wa-preview-head" data-preview-head>Now serving</div>
        <article class="wa-preview-card">
          <p class="eyebrow">Open</p>
          <p class="wa-preview-line" data-preview-for="business"><b>Your business name</b></p>
          <p class="wa-preview-line" data-preview-for="client">Dana</p>
          <p class="wa-preview-line" data-preview-for="job">Kitchen refit</p>
          <p class="wa-preview-line is-always">Second fix &mdash; sockets</p>
          <p class="wa-preview-line mono muted" data-preview-for="estimate">2h estimated</p>
        </article>
      </div>
      <p class="hint mt-10">The task name always shows &mdash; a screen with nothing on it
      would be no screen at all.</p>
    </div>
  </aside>
</div>

<div class="card mt-16">
  <div class="card-head">
    <h2>Work screens</h2>
    <span class="spacer"></span>
    <span class="muted small"><?= count($screens) ?></span>
  </div>

  <?php if (!$screens && $screensError === ''): ?>
    <div class="empty-state">
      <h3>No work screens yet</h3>
      <p>Create one above and you get a link to open on a tablet, a wall display or a
      technician&rsquo;s phone.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($screens as $s):
        $url = cm_work_access_url($s);
        $exp = $s['expires_at'] !== null ? (string) $s['expires_at'] : null;
        $dead = cm_link_expired($exp); ?>
    <div class="list-row">
      <div class="grow">
        <b><?= e((string) $s['name']) ?></b>
        <span class="pill <?= (string) $s['status'] === 'active' && !$dead ? 'pill-approved' : 'pill-pending' ?>"><?=
          e($dead ? 'expired' : (string) $s['status']) ?></span>
        <div class="muted small">
          <?= e(cm_work_access_views()[(string) $s['screen_view']] ?? 'Today') ?>
          &middot; <?= ((int) ($s['auth_required'] ?? 1)) ? 'Sign-in required' : 'Public display' ?>
          &middot; <?= e(cm_link_expiry_label($exp)) ?>
        </div>
        <div class="linkcopy mt-8">
          <input class="mono f-full" id="wa-<?= (int) $s['id'] ?>" readonly value="<?= e($url) ?>">
          <button class="btn btn-sm btn-quiet" type="button" data-copy-for="wa-<?= (int) $s['id'] ?>">Copy</button>
        </div>
      </div>
      <div class="actions">
        <a class="btn btn-sm btn-quiet"
           href="<?= e(cm_url('admin/work-access-screen.php?id=' . (int) $s['id'])) ?>">Customise</a>
        <a class="btn btn-sm btn-quiet" target="_blank" rel="noopener" href="<?= e($url) ?>">Open</a>
        <form method="post"><?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <input type="hidden" name="status" value="<?= (string) $s['status'] === 'active' ? 'disabled' : 'active' ?>">
          <button class="btn btn-sm btn-quiet" type="submit"><?= (string) $s['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="post" data-confirm="Rotate this link? The old URL will stop working, and anyone using it will need the new one.">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="rotate">
          <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn btn-sm btn-quiet" type="submit">Rotate</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?= cm_credential_tools_script_once() ?>
<script defer src="<?= e(cm_url('js/workscreen.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php cm_foot(); ?>
