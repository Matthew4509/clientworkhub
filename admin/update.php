<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';



require_once CM_LIB . '/migrate.php';
require_once CM_LIB . '/backup.php';
require_once CM_LIB . '/health.php';
require_once CM_LIB . '/recovery.php';
require_once CM_LIB . '/updater.php';



$admin    = cm_require_admin_or_recovery();
$recovery = cm_in_recovery($admin);















if (isset($_GET['ping'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['backup_at' => cm_backup_taken_at()]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();










    if ((string) ($_POST['action'] ?? '') === 'seal_key') {
        require_once CM_LIB . '/seal.php';
        if (!cm_crypt_available()) {
            cm_flash('This host has no AES-256-GCM in its PHP, so a backup cannot be sealed on it. '
                   . 'The plain backup still works.', 'error');
            cm_redirect_recovery('admin/update.php#backup');
        }


        cm_seal_hold(cm_seal_new_key(), !empty($_POST['passwords']));

        cm_seal_confirm();
        cm_redirect_recovery('admin/update.php?sealkey=1#backup');
    }

    if ((string) ($_POST['action'] ?? '') === 'seal_discard') {
        require_once CM_LIB . '/seal.php';
        cm_seal_forget();
        cm_flash('That key was discarded. Nothing was sealed with it.');
        cm_redirect_recovery('admin/update.php#backup');
    }

    if ((string) ($_POST['action'] ?? '') === 'clear_failure') {
        cm_migration_fail_clear();
        cm_audit('db.migrate.cleared', '', null, 'failure warning cleared by ' . (string) $admin['email']);
        cm_flash('Warning cleared. Take a fresh backup before trying the update again.', 'warn');
        cm_redirect_recovery('admin/update.php');
    }






    if ((string) ($_POST['action'] ?? '') === 'cleanup') {
        $target = (string) ($_POST['target'] ?? '');

        if ($target === '*') {
            $keys = [];
            foreach (cm_cleanup_candidates() as $c) {
                if ($c['removable']) $keys[] = $c['key'];
            }
        } else {
            $keys = [$target];
        }

        if (!$keys) {
            cm_flash('There was nothing left to remove.', 'warn');
            cm_redirect_recovery('admin/update.php#cleanup');
        }

        $res = cm_cleanup_run($keys, (string) $admin['email']);

        $said = [];
        if ($res['removed']) {
            $said[] = 'Removed ' . implode(', ', $res['removed']) . '.';
        }
        foreach ($res['failed'] as $k => $why) {
            $said[] = $k . ': ' . $why;
        }




        cm_flash(implode(' ', $said), $res['failed'] ? 'error' : 'ok');
        cm_redirect_recovery('admin/update.php#cleanup');
    }








    if ((string) ($_POST['action'] ?? '') === 'up_upload') {
        $rcv = cm_update_receive($_FILES['release'] ?? []);
        if (!$rcv['ok']) { cm_flash($rcv['error'], 'error'); cm_redirect_recovery('admin/update.php#files'); }
        $ins = cm_update_inspect($rcv['path']);
        if (!$ins['ok']) {
            cm_update_clear();
            cm_flash($ins['error'], 'error');
            cm_redirect_recovery('admin/update.php#files');
        }

        $signed = cm_update_upload_signature($rcv['path']);
        if (!$signed['ok']) {
            cm_update_clear();
            cm_audit('update.refused', '', null, 'uploaded release ' . $ins['version'] . ' refused: ' . $signed['error']);
            cm_flash($signed['error'], 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        cm_audit('update.received', '', null,
                 'release ' . $ins['version'] . ' uploaded and verified by ' . (string) $admin['email']
               . ($signed['note'] !== '' ? ' (signature NOT checked: ' . $signed['note'] . ')' : ' (signature verified)'));
        cm_flash('Release ' . $ins['version'] . ' received and verified — '
               . ($signed['note'] === '' ? 'it is signed with the release key, and ' : '')
               . 'every one of its ' . $ins['count'] . ' files matches its checksum. '
               . ($signed['note'] !== '' ? $signed['note'] . ' ' : '')
               . 'Read the box below, then apply it.', $signed['note'] !== '' ? 'warn' : 'ok');
        cm_redirect_recovery('admin/update.php#files');
    }




    if (cm_home() && (string) ($_POST['action'] ?? '') === 'up_github_check') {
        $chk = cm_update_github_check(CM_VERSION);
        $_SESSION['cm_update_github'] = $chk + ['at' => time()];
        if (!$chk['ok']) cm_flash($chk['error'], 'error');
        cm_redirect_recovery('admin/update.php#files');
    }

    if (cm_home() && (string) ($_POST['action'] ?? '') === 'up_github_download') {

        $chk = cm_update_github_check(CM_VERSION);
        $_SESSION['cm_update_github'] = $chk + ['at' => time()];
        if (!$chk['ok']) { cm_flash($chk['error'], 'error'); cm_redirect_recovery('admin/update.php#files'); }
        if (empty($chk['newer'])) {
            cm_flash('The newest release on GitHub is ' . $chk['version'] . ', and this copy already runs '
                   . CM_VERSION . ' — nothing to fetch.', 'warn');
            cm_redirect_recovery('admin/update.php#files');
        }
        $dl = cm_update_github_download($chk);
        if (!$dl['ok']) {
            cm_audit('update.refused', '', null, 'GitHub release ' . $chk['version'] . ' refused: ' . $dl['error']);
            cm_flash($dl['error'], 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        cm_audit('update.received', '', null, 'release ' . $dl['version'] . ' downloaded from GitHub and verified by '
                 . (string) $admin['email'] . ($dl['note'] !== '' ? ' (signature NOT checked: ' . $dl['note'] . ')' : ' (signature verified)'));
        cm_flash('Release ' . $dl['version'] . ' downloaded — '
               . ($dl['note'] === '' ? 'it is signed with the release key and every file matches its checksum. '
                                     : $dl['note'] . ' ')
               . 'Read the box below, then apply it.', $dl['note'] !== '' ? 'warn' : 'ok');
        cm_redirect_recovery('admin/update.php#files');
    }

    if (cm_home() && (string) ($_POST['action'] ?? '') === 'up_rollback') {
        if (empty($_POST['understood'])) {
            cm_flash('Tick the box to say you have read what this does.', 'error');
            cm_redirect_recovery('admin/update.php#rollback');
        }
        $from = CM_VERSION;
        $rb = cm_update_rollback_apply((string) $admin['email']);
        if (!$rb['ok']) {
            cm_flash($rb['error'], 'error');
            cm_redirect_recovery('admin/update.php#rollback');
        }
        cm_audit('update.rolled_back', '', null, 'files put back from ' . $from . ' to ' . $rb['version']
                 . ' by ' . (string) $admin['email']);
        cm_flash('Client Work Hub is back on version ' . $rb['version'] . ' (' . $rb['written'] . ' files put back). '
               . 'Your data was not touched; database updates the newer version made stay, and this version runs on them.');
        cm_redirect_recovery('admin/update.php');
    }

    if ((string) ($_POST['action'] ?? '') === 'up_check') {
        $chk = cm_update_check(CM_VERSION);


        cm_update_note_feed($chk);
        $_SESSION['cm_update_feed'] = $chk + ['at' => time()];
        if (!$chk['ok']) cm_flash($chk['error'], 'error');
        cm_redirect_recovery('admin/update.php#files');
    }

    if ((string) ($_POST['action'] ?? '') === 'up_download') {



        $chk = cm_update_check(CM_VERSION);
        cm_update_note_feed($chk);
        if (!$chk['ok']) { cm_flash($chk['error'], 'error'); cm_redirect_recovery('admin/update.php#files'); }
        if (empty($chk['newer'])) {
            cm_flash('The newest release online is ' . $chk['version'] . ', and this site already runs '
                   . CM_VERSION . ' — nothing to fetch.', 'warn');
            cm_redirect_recovery('admin/update.php#files');
        }
        $dl = cm_update_download($chk);
        if (!$dl['ok']) { cm_flash($dl['error'], 'error'); cm_redirect_recovery('admin/update.php#files'); }
        $ins = cm_update_inspect($dl['path']);
        if (!$ins['ok']) {
            cm_update_clear();
            cm_flash($ins['error'], 'error');
            cm_redirect_recovery('admin/update.php#files');
        }




        if ($ins['version'] !== (string) $chk['version']) {
            cm_update_clear();
            cm_flash('The update feed said ' . $chk['version'] . ' but the signed release it pointed at is '
                   . $ins['version'] . ', so it was refused and nothing was changed. Try again later.', 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        cm_audit('update.received', '', null, 'release ' . $ins['version']
                 . ' downloaded from the update feed, signature verified, by ' . (string) $admin['email']);
        cm_flash('Release ' . $ins['version'] . ' downloaded — its signature and every checksum '
               . 'verified. Read the box below, then apply it.');
        cm_redirect_recovery('admin/update.php#files');
    }

    if ((string) ($_POST['action'] ?? '') === 'up_clear') {
        cm_update_clear();
        cm_flash('The waiting release was thrown away. Nothing on the site changed.', 'warn');
        cm_redirect_recovery('admin/update.php#files');
    }

    if ((string) ($_POST['action'] ?? '') === 'up_apply') {
        $zipPath = cm_update_pending_zip();
        if ($zipPath === null) {
            cm_flash('There is no release waiting to be applied.', 'warn');
            cm_redirect_recovery('admin/update.php#files');
        }

        if (cm_migration_fail_note() !== '') {
            cm_flash('A database update failed and has not been cleared. Sort that out before '
                   . 'changing the site\'s files — see the red box below.', 'error');
            cm_redirect_recovery('admin/update.php');
        }
        if (!cm_migration_is_fresh_install() && cm_backup_taken_at() === null) {
            cm_flash('Download a backup first. The apply button does not work without one.', 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        if (empty($_POST['understood'])) {
            cm_flash('Tick the box to say you have read what this does.', 'error');
            cm_redirect_recovery('admin/update.php#files');
        }



        $ins = cm_update_inspect($zipPath);
        if ($ins['ok'] && str_starts_with(basename($zipPath), 'upload-')) {
            $signed = cm_update_upload_signature($zipPath);
            if (!$signed['ok']) $ins = ['ok' => false, 'error' => $signed['error']] + $ins;
        }
        if (!$ins['ok']) {
            cm_update_clear();
            cm_flash('The waiting release no longer verifies, so it was thrown away and nothing '
                   . 'was changed: ' . $ins['error'], 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        $stg = cm_update_extract($zipPath, $ins['files']);
        if (!$stg['ok']) { cm_flash($stg['error'], 'error'); cm_redirect_recovery('admin/update.php#files'); }

        $kept = cm_home() ? cm_update_rollback_keep(CM_VERSION) : ['ok' => true, 'error' => ''];
        if (!$kept['ok'] && cm_update_current_manifest()) {
            cm_flash($kept['error'] . ' Nothing was changed.', 'error');
            cm_redirect_recovery('admin/update.php#files');
        }
        $res = cm_update_apply($stg['dir'], $ins['files'], (string) $admin['email']);
        if ($res['ok']) {
            cm_update_clear();
            cm_audit('update.applied', '', null, 'site files updated to ' . $ins['version']
                     . ' (' . $res['written'] . ' written, ' . count($res['removed']) . ' removed) by '
                     . (string) $admin['email']);
            $dbNote = $ins['has_migrations'] ? 'Now check the database section below: if updates are '
                                             . 'waiting, run them while you are here.'
                                             : 'No database changes came with it.';
            if (cm_home()) {

                $run = cm_migrations_pending() ? cm_migration_run_pending() : ['ok' => true, 'applied' => []];
                $dbNote = $run['ok']
                    ? count($run['applied'] ?? []) . ' database update(s) ran with it.'
                    : 'A database update failed: ' . (string) $run['error'] . ' See the database section below.';
                if (!$kept['ok']) $dbNote .= ' (No copy to go back to could be kept: ' . $kept['error'] . ')';
            }
            cm_flash('The ' . (cm_home() ? 'application' : 'site') . '\'s files are now version ' . $ins['version'] . ' — '
                   . $res['written'] . ' written, ' . count($res['removed']) . ' removed. ' . $dbNote);
        } else {


            cm_audit('update.failed', '', null, $res['error']);
            cm_flash($res['error'], 'error');
        }
        cm_redirect_recovery('admin/update.php#files');
    }

    if ((string) ($_POST['action'] ?? '') === 'run') {
        $pending = cm_migrations_pending();

        if (!$pending) {
            cm_flash('The database was already up to date — nothing to run.', 'warn');
            cm_redirect_recovery('admin/update.php');
        }






        $files = cm_manifest_check();
        if ($files['state'] === 'present' && ($files['missing'] || $files['changed'])) {
            cm_flash('Some uploaded files are missing or incomplete. Upload them again before running '
                   . 'any database update — a half-transferred update file applies half of itself.', 'error');
            cm_redirect_recovery('admin/update.php');
        }
        if (cm_migration_fail_note() !== '') {
            cm_flash('The last attempt failed and has not been cleared. Restore your backup first, '
                   . 'then say so on this screen — there is no retry, because it would fail identically.', 'error');
            cm_redirect_recovery('admin/update.php');
        }



        if (!cm_migration_is_fresh_install() && cm_backup_taken_at() === null) {
            cm_flash('Download a backup first. The update button does not work without one, '
                   . 'and that is the whole point of it.', 'error');
            cm_redirect_recovery('admin/update.php');
        }
        if (empty($_POST['understood'])) {
            cm_flash('Tick the box to say you have read what this does.', 'error');
            cm_redirect_recovery('admin/update.php');
        }

        $result = cm_migration_run_pending();

        if ($result['ok']) {
            cm_migration_fail_clear();
            cm_audit('db.migrate', '', null,
                     'applied ' . implode(', ', $result['applied']) . ' by ' . (string) $admin['email']);
            cm_flash(count($result['applied']) === 1
                ? 'Applied 1 database update. The database is now on schema ' . cm_migration_number(cm_schema_version()) . '.'
                : 'Applied ' . count($result['applied']) . ' database updates. The database is now on schema ' . cm_migration_number(cm_schema_version()) . '.');
        } elseif (empty($result['locked'])) {



            cm_flash($result['error'], 'warn');
        } else {
            cm_migration_fail_record($result['error'], (string) $admin['email']);
            cm_audit('db.migrate.failed', '', null, $result['error']);
            cm_flash('The update stopped on a file that failed. Nothing after it was run. '
                   . 'Read the red box below before doing anything else.', 'error');
        }

        $_SESSION['cm_update_result'] = $result;
        cm_redirect_recovery('admin/update.php');
    }

    cm_redirect_recovery('admin/update.php');
}

$state    = cm_migration_state();
$applied  = cm_migrations_applied();
$backupAt = cm_backup_taken_at();
$failure  = cm_migration_fail_note();
$fresh    = cm_migration_is_fresh_install();
$checks   = cm_health_checks(true);
$worst    = cm_health_worst($checks);
$files    = cm_manifest_check();
$filesBad = $files['state'] === 'present' && ($files['missing'] || $files['changed']);






$pendingZip  = cm_update_pending_zip();



try {
    $pendingInfo = $pendingZip !== null ? cm_update_inspect($pendingZip) : null;
} catch (Throwable $ex) {
    error_log('pending update inspect failed: ' . $ex->getMessage());
    cm_update_clear();
    $pendingZip = null;
    $pendingInfo = null;
}
$pendingRemovals = ($pendingInfo !== null && $pendingInfo['ok']) ? cm_update_removals($pendingInfo['files']) : [];
$feedSeen = $_SESSION['cm_update_feed'] ?? null;
unset($_SESSION['cm_update_feed']);





$known = cm_update_known();
$newsAhead = [];
$newsBehind = [];
foreach ($known['history'] as $h) {
    if (version_compare($h['version'], CM_VERSION, '>')) $newsAhead[] = $h;
    else $newsBehind[] = $h;
}


if (!$newsAhead && $known['newer']) {
    $newsAhead[] = ['version' => $known['version'], 'released' => $known['released'],
                    'notes' => $known['notes']];
}


$result = $_SESSION['cm_update_result'] ?? null;
unset($_SESSION['cm_update_result']);

cm_head('Update', '', true);
cm_render_flashes();
if (!$recovery) { cm_admin_nav('settings'); cm_admin_subnav('settings', 'update', 'Versions and updates'); }
?>
<?php 

 ?>
<script defer src="<?= e(cm_url('js/backup-watch.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php 


 ?>
<?php if ($recovery): ?><h1>Versions and updates</h1><?php endif; ?>
<p class="sub">
  What is running, whether it arrived intact, a backup, and the button that
  brings the database into line with the files.
</p>

<?php if ($recovery): ?>
  <div class="note note-warn">
    <b>You are using the recovery key from config.php, not a normal sign-in.</b>
    This page and the backup are the only two things that key opens, and every
    use of it is written to the audit log.
    <b>Remove <span class="mono">update_key</span> from config.php when you are
    finished</b> — it is a way into this screen for anyone who learns it.
  </div>
<?php endif; ?>

<?php if ($fresh): ?>
  <div class="note note-info">
    <b>This database is empty.</b> Nothing has been installed into it yet, so
    there is nothing to back up and the backup step below is skipped. Press
    <b>Apply</b> to create the tables — that is the whole install.
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>Where this <?= cm_home() ? 'copy' : 'site' ?> is up to</h2>
    <span class="spacer"></span>
    <?php if ($state['up_to_date']): ?>
      <span class="pill pill-approved">Database up to date</span>
    <?php else: ?>
      <span class="pill pill-pending"><?= count($state['pending']) ?> database update<?= count($state['pending']) === 1 ? '' : 's' ?> waiting</span>
    <?php endif; ?>
  </div>

  <div class="mini-grid">
    <div class="mini"><span class="n mono"><?= e($state['app'] !== '' ? $state['app'] : 'unknown') ?></span> application</div>
    <div class="mini"><span class="n mono"><?= e($state['schema'] !== '' ? cm_migration_number($state['schema']) : 'none') ?></span> database schema</div>
    <div class="mini"><span class="n"><?= (int) $state['applied'] ?></span> updates applied</div>
    <div class="mini"><span class="n"><?= count($state['pending']) ?></span> waiting</div>
  </div>

</div>

<?php 




 ?>
<?php



$newsRow = static function (array $h): void { ?>
      <div class="mt-10">
        <b class="mono"><?= e($h['version']) ?></b>
        <?php if ($h['released'] !== ''): ?>
          <span class="muted small">&mdash; <?= e(date('j M Y', (int) strtotime($h['released']))) ?></span>
        <?php endif; ?>
        <?php if (trim($h['notes']) !== ''): ?>
          <span class="d-block mt-4 small prewrap"><?= e($h['notes']) ?></span>
        <?php else: ?>
          <span class="d-block mt-4 small muted">No notes came with this release.</span>
        <?php endif; ?>
      </div>
<?php };
?>
<div class="card" id="whatsnew">
  <div class="card-head">
    <h2>What&rsquo;s new</h2>
    <span class="spacer"></span>
    <?php if ($known['version'] !== ''): ?>
      <?php if ($known['newer']): ?>
        <span class="pill pill-pending">Update available</span>
      <?php else: ?>
        <span class="pill pill-approved">Up to date</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!cm_update_online_on()): ?>
    <p class="muted small">
      This <?= cm_home() ? 'copy' : 'site' ?> runs version <b class="mono"><?= e(CM_VERSION) ?></b>. What each
      release changed is listed on
      <a href="<?= e(CM_RELEASES_URL) ?>" target="_blank" rel="noopener noreferrer">the releases page on GitHub</a>,
      where the release zips are downloaded from.
    </p>
  <?php elseif ($known['version'] === ''): ?>
    <p class="muted small">
      This site runs version <b class="mono"><?= e(CM_VERSION) ?></b> and has not
      looked online yet. Press <b>Check for updates</b> in the files section
      below &mdash; after the first look, the site also takes the same look once
      a day on its own and says here (and on every admin page) when a newer
      release is out.
    </p>
  <?php else: ?>
    <p class="muted small">
      This site runs <b class="mono"><?= e(CM_VERSION) ?></b>. The newest release
      is <b class="mono"><?= e($known['version']) ?></b><?php
        if ($known['released'] !== ''): ?>, released
        <?= e(date('j M Y', (int) strtotime($known['released']))) ?><?php endif;
      ?> &mdash; last checked <?= e(date('j M Y, g:ia', $known['checked_at'])) ?>.
    </p>

    <?php if ($known['newer'] && !$newsAhead): ?>
      <p class="muted small">No notes came with it. Fetch it in the files
        section below &mdash; it is verified before anything is touched.</p>
    <?php endif; ?>

    <?php foreach ($newsAhead as $h) $newsRow($h); ?>

    <?php if ($known['newer']): ?>
      <p class="hint">To get it: <a href="#files">the files section below</a> &mdash;
        backup, fetch, apply, in that order.</p>
    <?php endif; ?>

    <?php if ($newsBehind): ?>
      <details class="morelist">
        <summary>What changed in <?= $known['newer'] ? 'earlier versions' : 'this and earlier versions' ?></summary>
        <?php foreach ($newsBehind as $h) $newsRow($h); ?>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <?php 





















 ?>
  <?php if (str_starts_with(CM_VERSION, '0.3.')): ?>
    <p class="muted small mt-8">
      Every <b class="mono">0.3.x</b> release is covered by your free lifetime
      licence &mdash; there is nothing to pay and nothing to renew for any update
      on this screen.
    </p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <h2>Health check</h2>
    <span class="spacer"></span>
    <span class="pill <?= $worst === 'ok' ? 'pill-approved' : 'pill-pending' ?>">
      <?= $worst === 'ok' ? 'All clear' : ($worst === 'fail' ? 'Needs attention' : 'Worth a look') ?>
    </span>
  </div>
  <p class="muted small mb-12">
    The questions somebody with server access would answer from a command line.
    Anything marked <b>Fix this</b> should be sorted before you run a database
    update.
  </p>

  <div class="table-wrap">
    <table>
      <thead><tr><th></th><th>Check</th><th>What it found</th></tr></thead>
      <tbody>
      <?php foreach ($checks as $c): ?>
        <tr>
          <td>
            <span class="pill <?= $c['state'] === 'ok' ? 'pill-approved' : 'pill-pending' ?>">
              <?= $c['state'] === 'ok' ? 'OK' : ($c['state'] === 'fail' ? 'Fix this' : 'Note') ?>
            </span>
          </td>
          <td><b><?= e($c['label']) ?></b></td>
          <td class="small">
            <?= e($c['detail']) ?>
            <?php 

 ?>
            <?php if ($c['state'] !== 'ok' && $c['fix'] !== ''): ?>
              <span class="muted d-block mt-4"><?= e($c['fix']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php














  $hostMsg = cm_requirements_host_message((string) ($_SERVER['HTTP_HOST'] ?? ''));
  ?>
  <?php if ($hostMsg !== '' && !cm_home()): ?>
    <details class="why mt-16">
      <summary>Send this to your hosting company</summary>
      <p class="muted small">
        Everything above that is missing, written as a request. Click in the box,
        select it all, and paste it into a support ticket or an email.
      </p>
      <textarea class="mono f-full" rows="18" readonly><?= e($hostMsg) ?></textarea>
    </details>
  <?php endif; ?>
</div>

<?php




















$cleanup = cm_cleanup_candidates();
$anyRemovable = false;
foreach ($cleanup as $c) { if ($c['removable']) { $anyRemovable = true; break; } }
?>
<?php  ?>
<?php if (!cm_home()): ?>
  <div class="card" id="cleanup">
    <h2>Cleanup</h2>
    <p class="muted small">
      Files left behind by an upload that are not part of the release running
      here &mdash; the installer, and pages replaced by a newer version.
      Nothing that belongs to the installed version can be listed &mdash;
      that is checked against <code>MANIFEST.txt</code>, not against a list in
      the code.
    </p>

    <?php if (!$cleanup): ?>
      <?php 


 ?>
      <p class="note note-ok">
        <b>Nothing to remove.</b> Checked
        <span class="mono">install.php</span> &mdash; it is not on the server,
        or it is part of the release running here.
      </p>
    <?php else: ?>

    <div class="table-wrap">
      <table>
        <thead><tr><th>File</th><th>Why it should go</th><th class="num"></th></tr></thead>
        <tbody>
        <?php foreach ($cleanup as $c): ?>
          <tr>
            <td>
              <b><?= e($c['label']) ?></b>
              <div class="muted small mono"><?= e($c['key']) ?></div>
              <?php if ($c['danger']): ?>
                <span class="pill pill-spam">Security</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= e($c['why']) ?></td>
            <td class="num">
              <?php if ($c['removable']): ?>
                <form method="post" class="inline"
                      data-confirm="Delete <?= e($c['key']) ?> from the server? This cannot be undone from here &mdash; it comes back on the next upload."
                      data-saving>
                  <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
                  <input type="hidden" name="action" value="cleanup">
                  <input type="hidden" name="target" value="<?= e($c['key']) ?>">
                  <button class="btn btn-sm<?= $c['danger'] ? '' : ' btn-quiet' ?>">Remove</button>
                </form>
              <?php else: ?>
                <span class="muted small"><?= e($c['blocked']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($anyRemovable && count($cleanup) > 1): ?>
      <form class="mt-12" method="post"
            data-confirm="Delete every file listed above? This cannot be undone from here."
            data-saving>
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="cleanup">
        <input type="hidden" name="target" value="*">
        <button class="btn">Remove all of them</button>
      </form>
    <?php endif; ?>
    <?php endif;  ?>
  </div>
<?php endif;  ?>

<?php if ($failure !== ''): ?>
  <div class="note note-error">
    <b>The last update attempt failed and did not finish.</b>
    <span class="mono small d-block my-8"><?= e($failure) ?></span>
    A file that fails part-way leaves the database in a state no update file
    describes, and MySQL cannot undo the parts that already ran.
    <b>Restore the backup you took before that attempt</b>, then try again on
    the restored database. Running it again as it stands will fail on the same
    statement and look like a different fault.
  </div>
<?php endif; ?>

<?php if ($result !== null): ?>
  <div class="card">
    <h2>What just ran</h2>
    <?php if ($result['applied']): ?>
      <ul class="tile-list">
        <?php foreach ($result['applied'] as $f): ?>
          <li><span class="mono small"><?= e($f) ?></span> — applied</li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted small">Nothing was applied.</p>
    <?php endif; ?>
    <?php if (!$result['ok']): ?>
      <p class="small mt-10"><b>Stopped at:</b>
        <span class="mono"><?= e($result['error']) ?></span></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <h2>1. Take a backup</h2>
    <span class="spacer"></span>
    <?php if ($backupAt !== null): ?>
      <span class="pill pill-approved">Downloaded <?= e(date('g:ia', $backupAt)) ?></span>
    <?php endif; ?>
  </div>

  <details class="why">
  <summary>What is in the file, and why it is compressed</summary>
  <p class="muted small">
    A complete copy of every table, as a file your browser downloads. Nothing is
    written to the server, so no copy is left behind for anyone to find.
    <?php if (cm_backup_can_gzip() && !cm_home()): ?>
      It is <b>compressed</b>, because phpMyAdmin — the tool you would restore
      with — has an upload limit you cannot raise, and it reads
      <span class="mono">.sql.gz</span> directly.
    <?php endif; ?>
  </p>
</details>

  <?php 












 ?>
  <form method="post" action="<?= e(cm_url('admin/backup.php')) ?>">
    <?= cm_csrf_field() ?><?= cm_recovery_field() ?>

    <?php 













 ?>
    <label class="tick d-if mb-10">
      <input type="checkbox" name="passwords" value="1">
      <span>Include my clients&rsquo; stored passwords in the download</span>
    </label>
    <p class="hint mt-0">
      Off, and the file carries <b>no client passwords at all</b> &mdash; the saved
      password, the secret note and the recovery code on every login come out
      empty. Restored onto this site, it keeps the passwords the site already
      has; restored anywhere else, those fields stay empty. Everything else is there.
      On, and the whole vault travels with the file, which is the copy to keep
      if this backup is the one you would rebuild from.
    </p>

    <button class="btn" type="submit">Download a backup now</button>

    <?php 





 ?>
    <?php if (cm_backup_can_gzip()): ?>
      <div class="mt-8"><button class="btn btn-sm btn-quiet" type="submit" name="plain" value="1">Download it uncompressed instead</button></div>
    <?php endif; ?>

  <?php 








 ?>
  <?php 








 ?>
  <?php if (cm_crypt_available()): ?>
    <?php 

 ?>
      <div class="mt-8"><button class="btn btn-sm" type="submit" name="action" value="seal_key"
              formaction="<?= e(cm_url('admin/update.php')) ?>">Download an encrypted backup instead</button></div>
    <?php endif; ?>
  </form>

  <?php if (cm_crypt_available()): ?>
    <p class="hint">
      Encrypted with a four-word key this site shows you once and never stores.
      Unreadable to anyone without it &mdash; <b>including you</b>, so write it
      down. It also carries your stored PayPal, Stripe, Google and mail
      passwords, which a plain backup cannot.
    </p>
  <?php endif; ?>

  <?php if ($backupAt !== null): ?>
    <p class="hint">
      Taken at <?= e(date('g:ia', $backupAt)) ?><?php
        if (!empty($_SESSION['cm_backup_bytes'])) {
            echo ', ' . e(cm_backup_size_label((int) $_SESSION['cm_backup_bytes']));
        } ?>.
      <b>Open the file before you go on.</b> This site knows it sent the whole
      thing; it cannot know that your browser finished writing it to disk.
    </p>
  <?php endif; ?>

  <?php 

 ?>
  <div class="note note-warn mt-14">
    <b>Keep that file under lock and key.</b> It contains every client&rsquo;s
    details and every password hash. Save it on an airgapped PC, or hide it on
    a USB stick and shove it behind your undies.
  </div>

  <?php 




 ?>
  <?php if (cm_crypt_available()):
          require_once CM_LIB . '/seal.php';
          $held = cm_seal_pending();
          $open = $held !== null && isset($_GET['sealkey']); ?>
    <dialog class="modal" id="dlg-sealkey"<?= $open ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2>Your backup key</h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>

        <?php if ($held !== null): $words = (array) $held['phrase']; ?>
          <p class="modal-sub">Write these four words down, then download the file.</p>

          <?php 


 ?>
          <div class="seal-key-row">
            <ol class="seal-key">
              <?php foreach ($words as $w): ?><li><?= e((string) $w) ?></li><?php endforeach; ?>
            </ol>
            <button class="pw-eye cred-copy" type="button" data-copy-for="seal-key-value"
                    aria-label="Copy the key" title="Copy the key"><?= cm_icon_copy() ?></button>
          </div>
          <?php 

 ?>
          <input type="text" id="seal-key-value" class="mono seal-key-plain" readonly tabindex="-1"
                 aria-label="Your backup key as one line, to copy"
                 value="<?= e(cm_seal_key_text($words)) ?>">

          <div class="note note-warn mt-14">
            <b>There is no way to recover this.</b> Nobody can open the file
            without those four words &mdash; not you, not this site, not anyone
            you ring for help. Keep them somewhere other than the backup itself.
          </div>

          <?php 







                  $sealPw = !empty($held['passwords']); ?>
          <p class="set-state <?= $sealPw ? 'set-state-warn' : 'set-state-ok' ?>">
            <?php if ($sealPw): ?>
              <b>Your clients&rsquo; stored passwords will be in this file.</b>
              The box on the backup card is ticked. Close this and untick it if
              that is not what you meant.
            <?php else: ?>
              <b>No client passwords will be in this file.</b> Tick the box on
              the backup card first if you want the vault in it.
            <?php endif; ?>
          </p>

          <div class="modal-foot">
            <form method="post" action="<?= e(cm_url('admin/backup.php')) ?>">
              <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
              <input type="hidden" name="sealed" value="1">
              <?php if ($sealPw): ?><input type="hidden" name="passwords" value="1"><?php endif; ?>
              <button class="btn" type="submit">Download the sealed backup</button>
            </form>
            <form method="post">
              <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
              <input type="hidden" name="action" value="seal_key">
              <?php 

 ?>
              <?php if ($sealPw): ?><input type="hidden" name="passwords" value="1"><?php endif; ?>
              <button class="btn btn-quiet" type="submit">Give me a different key</button>
            </form>
            <form method="post">
              <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
              <input type="hidden" name="action" value="seal_discard">
              <button class="btn btn-quiet" type="submit">Cancel</button>
            </form>
          </div>
          <?php 

 ?>
          <p class="hint">Once it has saved, close this window. The key is used
          once &mdash; a second encrypted backup gets a second key, because two
          files under one key means one guess opens both.</p>
        <?php else: ?>
          <p class="modal-sub">That key has expired. Close this and press
          <b>Download an encrypted backup instead</b> again for a new one.</p>
          <div class="modal-foot">
            <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
          </div>
        <?php endif; ?>
      </div>
    </dialog>
  <?php endif; ?>

  <?php 



 ?>
  <p class="hint mt-10">
    Putting one back: <a href="<?= e(cm_url('admin/restore.php')) ?>">Restore a backup</a>.
    <?php if (!cm_home()): ?>A plain backup can also be imported with phpMyAdmin; an encrypted one can
    only be opened here, with its key.<?php else: ?>Keep the file somewhere other than this PC &mdash; a USB
    stick, or a cloud folder.<?php endif; ?>
  </p>
</div>

<?php 




 ?>
<div class="card" id="files">
  <h2>2. Update the <?= cm_home() ? 'application' : 'site&rsquo;s files' ?></h2>
  <p class="muted small">
    <?php if (cm_home()): ?>
    This copy is version <b class="mono"><?= e(CM_VERSION) ?></b>. A newer release comes from GitHub when you
    ask for it, is checked file by file before anything is touched, and your data is never written or removed.
    The application is replaced; a copy of this version is kept so you can go back.
    <?php else: ?>
    This site&rsquo;s files are version <b class="mono"><?= e(CM_VERSION) ?></b>.
    <?php if (cm_update_online_on()): ?>
    A newer release can be fetched online or uploaded from your
    computer &mdash; either way it is checked file-by-file before anything is
    touched,
    <?php else: ?>
    A newer release is downloaded from
    <a href="<?= e(CM_RELEASES_URL) ?>" target="_blank" rel="noopener noreferrer">the releases page on GitHub</a>
    and uploaded here &mdash; it is checked file-by-file before anything is
    touched,
    <?php endif; ?> and your own files
    (<span class="mono">config.php</span>, <span class="mono">storage/</span>,
    anything you added) are never written or removed.
    <?php endif; ?>
  </p>

  <?php if (!cm_update_ready()): ?>
    <div class="note note-warn">
      <b>This server&rsquo;s PHP cannot open zip files</b>, so releases cannot be
      applied from this screen. Ask your host to enable the
      <span class="mono">zip</span> extension, or upload the files through
      cPanel &rarr; File Manager as before.
    </div>

  <?php elseif ($pendingInfo !== null && !$pendingInfo['ok']): ?>
    <?php 
 ?>
    <div class="note note-error">
      <b>A release is waiting but does not verify:</b> <?= e($pendingInfo['error']) ?>
    </div>
    <form method="post" data-saving>
      <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
      <input type="hidden" name="action" value="up_clear">
      <button class="btn btn-quiet" type="submit">Throw it away</button>
    </form>

  <?php elseif ($pendingInfo !== null): ?>
    <?php $newer = version_compare($pendingInfo['version'], CM_VERSION, '>'); ?>
    <div class="note note-info">
      <b>Release <span class="mono"><?= e($pendingInfo['version']) ?></span> is here and verified</b>
      &mdash; all <?= (int) $pendingInfo['count'] ?> files match their checksums.
      Applying it will write <?= (int) ($pendingInfo['writes'] ?? $pendingInfo['count']) ?> files<?php
        if ($pendingRemovals): ?> and remove <?= count($pendingRemovals) ?> the old
        release shipped and this one does not<?php endif; ?>.
      <?= !$pendingInfo['has_migrations'] ? 'It brings no database changes.'
          : (cm_home() ? 'Any database updates it brings run as part of applying it.'
                       : 'It carries database update files — after the files land, run them in the database section below.') ?>
    </div>

    <?php if (!$newer): ?>
      <div class="note note-warn">
        <b>This is not a newer version.</b> It would take the site from
        <span class="mono"><?= e(CM_VERSION) ?></span> to
        <span class="mono"><?= e($pendingInfo['version']) ?></span>. That is how a
        deliberate roll-back is done &mdash; but if you expected an upgrade,
        throw this away and fetch the right zip.
      </div>
    <?php endif; ?>

    <?php if ($failure !== ''): ?>
      <div class="note note-error">
        <b>The apply button is off until the failed database update above is dealt
        with.</b> Changing the site&rsquo;s files while the database is in a state
        no version describes only adds a second problem to the first.
      </div>
    <?php elseif ($backupAt === null && !$fresh): ?>
      <p class="note note-info">
        <b>Take a backup first.</b> The button appears once you have downloaded
        one in this sitting &mdash; a file update that goes wrong is recovered
        the same way as a database one.
      </p>
    <?php else: ?>
      <form method="post"
            data-confirm="<?= cm_home()
                ? 'Replace the application with release ' . e($pendingInfo['version']) . ' now? Your data is not touched.'
                : 'Replace this site\'s files with release ' . e($pendingInfo['version']) . ' now? Your config, storage and own files are not touched.' ?>"
            data-progress="Verifying the release|Copying files|Finishing"
            data-progress-title="Updating the <?= cm_home() ? 'application' : 'site&rsquo;s files' ?>"
            data-progress-done="Files updated">
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_apply">
        <div class="caps-pick">
          <label class="caps-row">
            <input type="checkbox" name="understood" value="1" required>
            <span>
              <b>I have the backup, and I understand this replaces the <?= cm_home() ? 'application' : 'site&rsquo;s files' ?>.</b>
              <span class="muted small">
                The release was verified twice already and is verified once more
                when you press the button. If a file cannot be written, the
                update stops before deleting anything and says which file.
              </span>
            </span>
          </label>
        </div>
        <button class="btn mt-12" type="submit">
          Apply version <?= e($pendingInfo['version']) ?>
        </button>
      </form>
      <form class="mt-8" method="post" data-saving>
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_clear">
        <button class="btn btn-sm btn-quiet" type="submit">Throw this release away instead</button>
      </form>
    <?php endif; ?>

  <?php elseif (cm_home()): ?>
    <?php 
          $ghSeen = $_SESSION['cm_update_github'] ?? null; ?>
    <?php if (is_array($ghSeen) && !empty($ghSeen['ok']) && !empty($ghSeen['newer'])): ?>
      <div class="note note-info">
        <b>Version <span class="mono"><?= e((string) $ghSeen['version']) ?></span> is available</b>
        (this copy runs <span class="mono"><?= e(CM_VERSION) ?></span>).
        <?php if (trim((string) $ghSeen['title']) !== ''): ?><span class="d-block mt-6"><b><?= e((string) $ghSeen['title']) ?></b></span><?php endif; ?>
        <?php if (trim((string) $ghSeen['notes']) !== ''): ?>
          <span class="d-block mt-6 small prewrap"><?= e((string) $ghSeen['notes']) ?></span>
        <?php endif; ?>
        <a class="d-block mt-6 small" href="<?= e((string) $ghSeen['page']) ?>" target="_blank" rel="noopener noreferrer">The release on GitHub</a>
      </div>
      <form method="post" data-saving
            data-progress="Fetching the release from GitHub|Checking its signature|Verifying every file"
            data-progress-title="Downloading the update"
            data-progress-done="Release downloaded and verified">
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_github_download">
        <button class="btn" type="submit">Download version <?= e((string) $ghSeen['version']) ?></button>
      </form>
      <p class="hint">Refused unless it carries Client Work Hub&rsquo;s release signature, so a tampered
      file cannot get past it.</p>
    <?php else: ?>
      <?php if (is_array($ghSeen) && !empty($ghSeen['ok'])): ?>
        <p class="note note-ok mb-12">
          <b>Up to date.</b> The newest release on GitHub is
          <span class="mono"><?= e((string) $ghSeen['version']) ?></span> and this copy runs
          <span class="mono"><?= e(CM_VERSION) ?></span>.
        </p>
      <?php endif; ?>
      <h3>Check for updates</h3>
      <p class="muted small">
        Asks <a href="<?= e(CM_RELEASES_URL) ?>" target="_blank" rel="noopener noreferrer">the releases page on GitHub</a>
        whether a newer version exists. Only when you press the button &mdash; Client Work Hub never goes
        online on its own &mdash; and nothing about you or your data is sent.
      </p>
      <form method="post" data-saving>
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_github_check">
        <button class="btn" type="submit">Check for updates</button>
      </form>
    <?php endif; ?>
    <details class="why mt-16">
      <summary>Or install a release zip you already have</summary>
      <p class="muted small">
        The <span class="mono">clientworkhub-x.y.z.zip</span> from the releases page, exactly as it
        downloaded &mdash; not unpacked, not renamed. It is verified the same way.
      </p>
      <form method="post" enctype="multipart/form-data" data-saving>
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_upload">
        <input type="file" name="release" accept=".zip,application/zip" required<?= cm_upload_limit_attrs() ?>>
        <button class="btn mt-8" type="submit">Install it</button>
      </form>
    </details>

  <?php else: ?>
    <?php  ?>
    <?php if (is_array($feedSeen)): ?>
      <?php if (!empty($feedSeen['ok']) && !empty($feedSeen['newer'])): ?>
        <div class="note note-info">
          <b>Version <span class="mono"><?= e((string) $feedSeen['version']) ?></span> is available</b>
          (this site runs <span class="mono"><?= e(CM_VERSION) ?></span>).
          <?php if (trim((string) ($feedSeen['notes'] ?? '')) !== ''): ?>
            <span class="d-block mt-6 small prewrap"><?= e((string) $feedSeen['notes']) ?></span>
          <?php endif; ?>
        </div>
        <form method="post" data-saving
              data-progress="Fetching the release|Checking its signature|Verifying every file"
              data-progress-title="Downloading the update"
              data-progress-done="Release downloaded and verified">
          <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
          <input type="hidden" name="action" value="up_download">
          <button class="btn" type="submit">Download version <?= e((string) $feedSeen['version']) ?></button>
        </form>
        <p class="hint">
          The download is refused unless it carries Client Work Hub&rsquo;s release
          signature &mdash; a tampered file cannot get past that, whatever server it
          came from.
        </p>
      <?php elseif (!empty($feedSeen['ok'])): ?>
        <p class="note note-ok mb-12">
          <b>Up to date.</b> The newest release online is
          <span class="mono"><?= e((string) $feedSeen['version']) ?></span> and this
          site already runs <span class="mono"><?= e(CM_VERSION) ?></span>.
        </p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!cm_update_online_on()): ?>
    <div>
      <h3>Upload a release</h3>
      <p class="muted small">
        Download the newest <span class="mono">clientworkhub-x.y.z.zip</span> from
        <a href="<?= e(CM_RELEASES_URL) ?>" target="_blank" rel="noopener noreferrer">the releases page on GitHub</a>
        and upload it here exactly as it downloaded &mdash; not unpacked, not
        renamed. It is verified before anything is touched, and refused unless
        it carries Client Work Hub&rsquo;s release signature.
        <?php $cap = (string) ini_get('upload_max_filesize');
              if ($cap !== '' && $cap !== '-1'): ?>
          Your host accepts uploads up to <span class="mono"><?= e($cap) ?></span>.
        <?php endif; ?>
      </p>
      <form method="post" enctype="multipart/form-data" data-saving>
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="up_upload">
        <input type="file" name="release" accept=".zip,application/zip" required<?= cm_upload_limit_attrs() ?>>
        <button class="btn mt-8" type="submit">Upload it</button>
      </form>
      <p class="hint">
        This site does not check for updates on its own: nothing is fetched from
        the internet unless you download it yourself.
      </p>
    </div>
    <?php elseif (!is_array($feedSeen) || empty($feedSeen['ok']) || empty($feedSeen['newer'])): ?>
    <div class="split-2">
      <div>
        <h3>Check online</h3>
        <p class="muted small">
          Asks the update feed whether a newer release exists. Checking sends
          nothing about this site &mdash; it is a plain read of one small file.
          The site also takes the same look once a day on its own, which is how
          the <b>update available</b> notice knows to appear without anyone
          pressing anything.
        </p>
        <form method="post" data-saving>
          <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
          <input type="hidden" name="action" value="up_check">
          <button class="btn" type="submit">Check for updates</button>
        </form>
      </div>
      <div>
        <h3>Or upload a release</h3>
        <p class="muted small">
          The <span class="mono">clientworkhub-x.y.z.zip</span> exactly as it was
          given to you &mdash; not unpacked, not renamed. It is verified before
          anything is touched.
          <?php $cap = (string) ini_get('upload_max_filesize');
                if ($cap !== '' && $cap !== '-1'): ?>
            Your host accepts uploads up to <span class="mono"><?= e($cap) ?></span>.
          <?php endif; ?>
        </p>
        <form method="post" enctype="multipart/form-data" data-saving>
          <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
          <input type="hidden" name="action" value="up_upload">
          <input type="file" name="release" accept=".zip,application/zip" required<?= cm_upload_limit_attrs() ?>>
          <button class="btn mt-8" type="submit">Upload it</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php $rollbackTo = cm_home() ? cm_update_rollback_version() : ''; ?>
<?php if ($rollbackTo !== ''): ?>
<div class="card" id="rollback">
  <h2>Go back to version <?= e($rollbackTo) ?></h2>
  <p class="muted small">
    The last update kept a copy of version <span class="mono"><?= e($rollbackTo) ?></span>. If something is
    wrong with <span class="mono"><?= e(CM_VERSION) ?></span>, this puts that copy back. Your clients, jobs and
    settings are not touched; database updates the newer version made stay, and the older version runs on them.
  </p>
  <form method="post"
        data-confirm="Go back to version <?= e($rollbackTo) ?> now?"
        data-progress="Checking the kept copy|Copying files|Finishing"
        data-progress-title="Going back"
        data-progress-done="Back on the earlier version">
    <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
    <input type="hidden" name="action" value="up_rollback">
    <label class="tick">
      <input type="checkbox" name="understood" value="1" required>
      <span>I understand this replaces the application&rsquo;s files with version <?= e($rollbackTo) ?>.</span>
    </label>
    <button class="btn btn-quiet mt-12" type="submit">Go back to <?= e($rollbackTo) ?></button>
  </form>
</div>
<?php endif; ?>

<?php 

 ?>
<div class="card" id="update">
  <h2>3. Update the database</h2>

  <?php if ($state['up_to_date']): ?>
    <p class="muted small">
      <?php if (cm_home()): ?>
      Nothing is waiting. Database updates run by themselves when Client Work Hub
      starts and straight after an update is applied.
      <?php else: ?>
      Nothing is waiting. Every update file on this server has been applied to
      this database — after you upload a newer version of the site, come back
      here and this section will have something in it.
      <?php endif; ?>
    </p>

  <?php else: ?>
    <p class="muted small">
      <b><?= count($state['pending']) ?></b>
      update<?= count($state['pending']) === 1 ? '' : 's' ?> came with the files
      you uploaded and <?= count($state['pending']) === 1 ? 'has' : 'have' ?>
      not run against this database yet.
      <?= count($state['pending']) === 1 ? 'It runs on its own:' : 'They run in this order:' ?>
    </p>
    <ul class="tile-list">
      <?php foreach ($state['pending'] as $f): ?>
        <li><span class="mono small"><?= e($f) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <div class="note note-warn">
      <b>Read this before you press it.</b>
      <ul class="tile-list">
        <li>This changes the shape of your live database — it adds and alters
            tables. It is not reversible from inside this site.</li>
        <li>If a file fails part-way, MySQL <b>cannot undo it</b>. The way back
            is the backup you just downloaded, and there is no other way back.</li>
        <li>Do it when the site is quiet. Anyone using it while a table is
            being altered may see an error.</li>
        <li><b>Do not close this tab while it runs.</b> It will finish either
            way — the work carries on even if the browser goes away — but you
            would not see whether it worked.</li>
        <li>It updates the <b>database only</b>. The site's files are whatever
            you uploaded; this button does not fetch or install anything.</li>
      </ul>
    </div>

    <?php if ($filesBad): ?>
      <?php 



 ?>
      <div class="note note-error">
        <b>Some files did not arrive completely, so the update button is off.</b>
        <?php foreach (['missing' => 'Missing', 'changed' => 'Incomplete or altered'] as $k => $lbl): ?>
          <?php if ($files[$k]): ?>
            <span class="d-block mt-6"><?= e($lbl) ?>:
              <span class="mono small"><?= e(implode(', ', $files[$k])) ?></span></span>
          <?php endif; ?>
        <?php endforeach; ?>
        <span class="d-block mt-8">
          <?= cm_home() ? 'Install the release again from the download on GitHub (Install Client Work Hub.cmd), then reload this page.'
                        : 'Upload them again through cPanel → File Manager, then reload this page.' ?>
        </span>
      </div>

    <?php elseif ($failure !== ''): ?>
      <?php 




 ?>
      <div class="note note-error">
        <b>The update button is off until the failure above is dealt with.</b>
        <?php if (cm_home()): ?>
        Restore the backup you took before that attempt with <b>Restore a backup</b> above. Then come back
        here and say so.
        <?php else: ?>
        Restore the backup you took before that attempt — <b>phpMyAdmin →
        Import</b> in cPanel, or <span class="mono small">mysql -u USER -p
        DATABASE &lt; your-backup.sql</span> from a shell. Then come back here
        and say so.
        <?php endif; ?>
      </div>
      <form method="post" data-confirm="Only do this if you have actually restored the backup. Clearing this warning turns the update button back on.">
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="clear_failure">
        <button class="btn btn-quiet" type="submit">I have restored the backup — clear this warning</button>
      </form>

    <?php elseif ($backupAt === null && !$fresh): ?>
      <p class="note note-info">
        <b>Take a backup first.</b> The button appears once you have downloaded
        one in this sitting — not because a backup makes the update safe, but
        because it is the only thing that makes a failed one survivable.
      </p>

    <?php else: ?>
      <?php 


 ?>
      <?php 










 ?>
      <form method="post" data-confirm="Apply <?= count($state['pending']) ?> database update<?= count($state['pending']) === 1 ? '' : 's' ?> to the live database now? There is no undo from inside the site."
            data-progress="Checking database|Performing update|Post-update check"
            data-progress-title="Updating your site"
            data-progress-done="Site update completed successfully">
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="action" value="run">
        <div class="caps-pick">
          <label class="caps-row">
            <input type="checkbox" name="understood" value="1" required>
            <span>
              <b>I have the backup, and I understand this cannot be undone from here.</b>
              <span class="muted small">
                <?= $fresh
                    ? 'This database is empty, so there is nothing to lose — this is the install.'
                    : 'Backup downloaded at ' . e(date('g:ia', (int) $backupAt))
                      . '. If the update fails, restoring that file is the recovery.' ?>
              </span>
            </span>
          </label>
        </div>
        <button class="btn mt-12" type="submit">
          Apply <?= count($state['pending']) ?> update<?= count($state['pending']) === 1 ? '' : 's' ?>
        </button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php













$rows   = $applied ? array_reverse($applied, true) : [];
$recent = array_slice($rows, 0, 3, true);
$older  = array_slice($rows, 3, null, true);



$updateRows = static function (array $list): void { ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Update</th><th>Applied</th></tr></thead>
        <tbody>
        <?php foreach ($list as $file => $when): ?>
          <tr>
            <td class="mono small"><?= e((string) $file) ?></td>
            <td class="small"><?= e(date('j M Y, g:ia', strtotime((string) $when))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php };
?>
<div class="card">
  <h2>Recent updates applied</h2>
  <?php if (!$applied): ?>
    <p class="muted small">
      Nothing at all — this database has never been set up. Every file above is
      waiting, and running them is how the site gets its tables.
    </p>
  <?php else: ?>
    <?php $updateRows($recent); ?>
    <?php if ($older): ?>
      <details class="morelist">
        <summary>Show the other <?= count($older) ?> update<?= count($older) === 1 ? '' : 's' ?></summary>
        <?php $updateRows($older); ?>
      </details>
    <?php endif; ?>
  <?php endif; ?>
  <p class="hint">
    Newest first<?= $older ? ', three at a time' : '' ?>. This list <b>is</b> the
    schema version — there is no number stored beside it that could disagree
    with it.
  </p>
</div>

<?php if (!cm_home()): ?>
<div class="card">
  <h2>Restoring a backup</h2>
  <?php 




 ?>
  <p class="muted small">
    To put a backup back, use <b>Restore a backup</b> above. The file is also
    an ordinary compressed <span class="mono">.sql</span>, so phpMyAdmin
    (cPanel &rarr; Import) and any other MySQL tool can read it as it is.
  </p>
  <p class="muted small mt-10">
    From a shell, if you have one, the same file goes back with:
    <span class="mono small">gunzip &lt; your-backup.sql.gz | mysql -u USER -p DATABASE</span>
  </p>
</div>

<div class="card">
  <h2>If you can never reach this page</h2>
  <details class="why">
  <summary>The one case where this screen cannot help you</summary>
  <p class="muted small">
    There is one way this screen can be unreachable: a new version whose
    sign-in code needs a database change that has not run yet. Signing in
    breaks, and the button that would fix it is behind the sign-in.
  </p>
</details>
  <p class="muted small mt-10">
    The way out needs no server access. In <span class="mono">config.php</span>,
    through cPanel → File Manager, add a long random line:
  </p>
  <p class="mono small mt-8">
    'update_key' =&gt; 'paste-at-least-32-random-characters-here',
  </p>
  <p class="muted small mt-10">
    Then open <span class="mono">/admin/update.php?key=that-same-string</span>.
    It reaches this page and the backup with no sign-in at all, and nothing
    else. <b>Delete the line once the site is working</b> — anyone who learns
    it has the same access. Every use is recorded in the audit log.
  </p>
</div>
<?php endif; ?>
<?php  ?>
<?= cm_credential_tools_script_once() ?>
<?php cm_foot(); ?>
