<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/backup.php';
require_once CM_LIB . '/restore.php';
require_once CM_LIB . '/seal.php';

require_once CM_LIB . '/sealrecover.php';
require_once CM_LIB . '/recovery.php';



$admin = cm_require_admin_or_recovery();





$dir = cm_restore_dir();
if (!is_dir($dir)) @mkdir($dir, 0770, true);


$home   = cm_home();
$where  = $home ? str_replace('/', '\\', $dir) : 'storage/restore/';
$byHand = $home ? 'File Explorer' : 'FTP or your host\'s File Manager';
if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
    @file_put_contents($dir . '/.htaccess',
        "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

$error   = '';
$notice  = '';





$hunt    = null;
$report  = null;
$chosen  = (string) ($_POST['file'] ?? $_GET['file'] ?? '');
$keyText = (string) ($_POST['key'] ?? '');
$ran     = null;


function cm_restore_upload_cap(): int
{
    return cm_upload_cap();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $step = (string) ($_POST['step'] ?? '');


    if ($step === 'upload') {
        $up = $_FILES['backup'] ?? null;
        $err = is_array($up) ? (int) ($up['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {

            $cap = cm_restore_upload_cap();
            $error = 'That file is bigger than ' . ($home ? 'this copy' : 'this host') . ' will accept in one upload'
                   . ($cap > 0 ? ' (' . cm_bytes_label($cap) . ')' : '')
                   . ($home ? '. Put the file in ' : ', and that limit is set by your host, not by this site. Put the file in ')
                   . $where . ' with ' . $byHand . ' instead — there is no limit that way — '
                   . 'and it will appear in the list below.';
        } elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $up['tmp_name'])) {
            $error = 'Nothing arrived. Choose a backup file and try again.';
        } else {



            $name = basename((string) $up['name']);
            $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
            $tmp = (string) $up['tmp_name'];






            $kind = cm_restore_identify($tmp);
            $held = count(cm_restore_available());
            if (!preg_match('/(\.sql|\.sql\.gz|\.gz|' . preg_quote(CM_SEAL_EXT, '/') . ')$/i', $name)) {
                $error = 'That is not a backup file. A backup from this site ends in .sql, .sql.gz or '
                       . CM_SEAL_EXT . '.';
            } elseif ($held >= 10) {
                $error = $where . ' already holds ' . $held . ' backups. Delete the ones you do not need '
                       . '(the Delete button beside each), then upload this one again.';
            } elseif ($kind['kind'] === 'unknown' || !empty($kind['error'])) {
                $error = (string) ($kind['error'] ?? 'That file is not a Client Work Hub backup.') . ' Nothing was kept.';
            } elseif ($kind['kind'] !== 'sealed' && !($seen = cm_restore_inspect($tmp))['ok']) {
                $error = (string) $seen['error'] . ' Nothing was kept.';
            } elseif (!@move_uploaded_file($tmp, $dir . '/' . ($name = $kind['kind'] === 'gzip' && !preg_match('/\.sql\.gz$/i', $name)
                        ? preg_replace('/\.gz$/i', '', $name) . '.sql.gz' : $name))) {
                $error = 'The file could not be saved into ' . $where . '. Check the folder is writable, '
                       . 'or put the file there yourself with ' . ($home ? 'File Explorer' : 'File Manager') . '.';
            } else {
                @chmod($dir . '/' . $name, 0600);
                $chosen = $name;
                $notice = 'Uploaded. Check what it says below before you restore it.';
            }
        }
    }


    if ($step === 'delete') {
        $path = cm_restore_path((string) ($_POST['file'] ?? ''));
        if ($path !== null && @unlink($path)) {
            cm_audit('db.restore.file_deleted', '', null, basename($path) . ' removed by ' . (string) $admin['email']);
            cm_flash('That file has been deleted from ' . ($home ? 'this PC' : 'the server') . '.');
        } else {
            cm_flash('That file could not be deleted. Remove it with ' . ($home ? 'File Explorer' : 'File Manager') . '.', 'warn');
        }
        cm_redirect('admin/restore.php');
    }





    if ($step === 'hunt') {
        $path = cm_restore_path($chosen);
        $read = cm_seal_read_key_partial($keyText);

        if ($path === null) {
            $error = 'That file is no longer there. Choose one from the list.';
        } elseif (!$read['ok']) {
            $error = $read['error'];
        } elseif ($read['unknown'] === null) {
            $error = 'Every word is there, so there is nothing to search for. Press Open it.';
        } else {
            $fh   = @fopen($path, 'rb');
            $head = $fh !== false ? (string) fread($fh, 2 * 1024 * 1024) : '';
            if ($fh !== false) fclose($fh);
            $ff = cm_seal_first_frame($head);

            if (!$ff['ok']) {
                $error = $ff['error'];
            } else {
                $from  = max(0, (int) ($_POST['from'] ?? 0));



                $slice = cm_seal_recover_slice($ff['envelope'], $ff['frame'], $ff['final'],
                                               $read['phrase'], (int) $read['unknown'], $from, 8.0);
                $total = count(cm_seal_words());
                $each  = cm_seal_derive_seconds((int) $ff['envelope']['iterations']);

                if ($slice['found'] !== null) {
                    $full = $read['phrase'];
                    $full[(int) $read['unknown']] = $slice['found'];
                    $keyText = implode(' ', $full);
                    $notice  = 'Found it — word ' . ((int) $read['unknown'] + 1) . ' is "'
                             . $slice['found'] . '". The whole key is in the box now.';
                } else {
                    $hunt = [
                        'from'  => $slice['next'],
                        'total' => $total,
                        'done'  => $slice['done'],
                        'left'  => cm_seal_wait_label(max(0, $total - $slice['next']) * $each),
                    ];
                    if ($slice['done']) {
                        $error = 'Every one of the ' . number_format($total) . ' words was tried and none of them '
                               . 'opens this file. That means one of the OTHER three words is wrong too, or this is '
                               . 'not the file those words belong to. Check the other three against the paper.';
                        $hunt = null;
                    }
                }
            }
        }
    }





    if ($step === 'convert') {
        $path = cm_restore_path($chosen);
        $phrase = [];
        $ok = true;

        if ($path === null) {
            $error = 'That file is no longer there. Choose one from the list.';
            $ok = false;
        }
        if ($ok) {
            $kind = cm_restore_identify($path);
            if ($kind['kind'] !== 'sealed') {
                $error = 'That file is not sealed, so there is nothing to decrypt — it is already plain SQL.';
                $ok = false;
            } else {
                $read = cm_seal_read_key($keyText);
                if (!$read['ok']) { $error = $read['error']; $ok = false; }
                else { $phrase = $read['phrase']; }
            }
        }
        if ($ok) {


            $seen = cm_restore_inspect($path, $phrase);
            if (!$seen['ok']) { $error = $seen['error']; $ok = false; }
        }
        if ($ok) {
            cm_audit('db.restore.converted', '', null,
                     basename($path) . ' decrypted to plain SQL by ' . (string) $admin['email']);
            $out = preg_replace('/' . preg_quote(CM_SEAL_EXT, '/') . '$/i', '', basename($path));
            try {
                cm_restore_stream_plain($path, $phrase, $out . '.sql');
                exit;
            } catch (Throwable $ex) {


                $error = $ex->getMessage();
            }
        }
    }


    if ($step === 'run') {
        $path = cm_restore_path($chosen);
        $phrase = [];
        $ok = true;

        if ($path === null) {
            $error = 'That file is no longer there. Choose one from the list.';
            $ok = false;
        }

        if ($ok) {
            $kind = cm_restore_identify($path);
            if ($kind['kind'] === 'sealed') {
                $read = cm_seal_read_key($keyText);
                if (!$read['ok']) { $error = $read['error']; $ok = false; }
                else { $phrase = $read['phrase']; }
            }
        }



        if ($ok && strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'RESTORE') {
            $error = 'Type RESTORE in the box to confirm. Nothing has been changed.';
            $ok = false;
        }




        if ($ok && !cm_migration_is_fresh_install() && cm_backup_taken_at() === null) {
            $error = 'Take a backup of what is here NOW before restoring over it. If this file turns out to be '
                   . 'the wrong one, that backup is the only way back. The button is on Update & backup.';
            $ok = false;
        }

        if ($ok) {
            $seen = cm_restore_inspect($path, $phrase);
            if (!$seen['ok']) { $error = $seen['error']; $ok = false; }
            else { $report = $seen['report']; }
        }

        if ($ok) {
            cm_audit('db.restore.started', '', null,
                     basename($path) . ' by ' . (string) $admin['email']);


            $keepVault = ($report['passwords'] ?? '') === CM_BACKUP_PASSWORDS_OUT;
            $vault = $keepVault ? cm_restore_vault_snapshot() : [];

            $ran = cm_restore_run($path, $phrase);

            if (!$ran['ok']) {
                cm_audit('db.restore.failed', '', null, basename($path) . ' — ' . $ran['error']);
                $error = $ran['error'];
            } else {
                $steps = [];
                $steps[] = ['ok' => true, 'text' => 'Restored ' . number_format($ran['statements'])
                          . ' statements from ' . basename($path) . '.'];




                $newKey = null;
                if (!empty($report['appkey'])) {
                    $k = cm_restore_appkey((string) $report['appkey'], !empty($_POST['replace_key']));
                    $steps[] = ['ok' => $k['ok'], 'text' => $k['note']];
                    if (($k['action'] ?? '') === 'written') $newKey = cm_app_key_from((string) $report['appkey']);
                } else {
                    $steps[] = ['ok' => false, 'text' => 'That backup did not carry a settings key, so any stored '
                              . 'PayPal, Stripe, Google or mail passwords in it will read back as empty unless this '
                              . 'site already has the key they were encrypted with. Re-enter them in Settings if they '
                              . 'stop working. A SEALED backup carries the key; a plain one never can.'];
                }

                if ($keepVault) {
                    try {
                        $v = cm_restore_vault_putback($vault, $newKey);
                        $steps[] = ['ok' => $v['lost'] === 0,
                                    'text' => $vault === []
                                        ? 'That backup had no client passwords in it, and this site had none stored either.'
                                        : 'That backup had no client passwords in it, so the ones this site already had were '
                                          . 'kept: ' . $v['kept'] . ' login' . ($v['kept'] === 1 ? '' : 's') . ' kept their saved '
                                          . 'password, note and recovery code.'
                                          . ($v['lost'] > 0 ? ' ' . $v['lost'] . ' could not be put back, because that login is '
                                              . 'not in the backup, or has another client or username there. Those are empty now.' : '')];
                        cm_audit('db.restore.vault_kept', '', null, $v['kept'] . ' kept, ' . $v['lost'] . ' not matched');
                    } catch (Throwable $ex) {
                        $steps[] = ['ok' => false, 'text' => 'The client passwords this site had could not be put back: '
                                  . $ex->getMessage() . '. They are empty now; restore a backup that includes them.'];
                    }
                    unset($vault);
                }





                try {
                    $pending = cm_migrations_pending();
                    if ($pending) {
                        $m = cm_migration_run_pending();
                        $steps[] = ['ok' => empty($m['error']),
                                    'text' => empty($m['error'])
                                        ? 'Ran ' . count($m['applied'] ?? []) . ' database updates the restored '
                                          . 'database was missing, so the site\'s files and its database match again.'
                                        : 'The restore finished, but a database update failed afterwards: '
                                          . $m['error'] . ' — go to Update & backup and apply the rest.'];
                    } else {
                        $steps[] = ['ok' => true, 'text' => 'No database updates were needed: that backup already '
                                  . 'matches this version\'s files.'];
                    }
                } catch (Throwable $ex) {
                    $steps[] = ['ok' => false, 'text' => 'The restore finished, but the database updates could not be '
                              . 'checked afterwards. Open Update & backup and apply anything waiting.'];
                }

                cm_audit('db.restore.finished', '', null,
                         basename($path) . ' — ' . $ran['statements'] . ' statements by ' . (string) $admin['email']);
                $_SESSION['cm_restore_done'] = $steps;
                cm_redirect('admin/restore.php?done=1');
            }
        }
    }
}


$done = null;
if (isset($_GET['done']) && !empty($_SESSION['cm_restore_done'])) {
    $done = (array) $_SESSION['cm_restore_done'];
    unset($_SESSION['cm_restore_done']);
}


$kind = null;
if ($report === null && $chosen !== '') {
    $path = cm_restore_path($chosen);
    if ($path === null) {
        if ($error === '') $error = 'That file is no longer in ' . $where . '.';
        $chosen = '';
    } else {
        $kind = cm_restore_identify($path);
        if (isset($kind['error'])) {
            if ($error === '') $error = $kind['error'];
        } else {


            $needKey = $kind['kind'] === 'sealed';
            $phrase  = [];
            if ($needKey && $keyText !== '') {
                $read = cm_seal_read_key($keyText);
                if ($read['ok']) $phrase = $read['phrase'];
                elseif ($error === '') $error = $read['error'];
            }
            if (!$needKey || $phrase !== []) {
                $seen = cm_restore_inspect($path, $phrase);
                if ($seen['ok']) $report = $seen['report'];
                elseif ($error === '') $error = $seen['error'];
            }
        }
    }
}

$files    = cm_restore_available();
$cap      = cm_restore_upload_cap();
$backupAt = cm_backup_taken_at();

cm_head('Restore a backup', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'update', 'Restore a backup');
?>
<p class="sub">
  Replace everything in this site&rsquo;s database with the contents of a backup
  file. There is no undo.
</p>

<?php if ($done !== null): ?>
  <div class="card">
    <h2>Restore finished</h2>
    <?php foreach ($done as $s): ?>
      <p class="<?= $s['ok'] ? 'small' : 'small' ?>">
        <b><?= $s['ok'] ? '&check;' : '!' ?></b> <?= e((string) $s['text']) ?>
      </p>
    <?php endforeach; ?>
    <div class="note note-warn mt-14">
      <b>Sign in again.</b> The backup brought its own users and passwords with
      it, so the account you are signed in as may no longer be the account it was
      a minute ago. Sign out and back in with the password that was in use when
      that backup was taken.
    </div>
    <p class="hint">
      If you uploaded the file, <b>delete it from <?= $home ? 'the list' : 'storage/restore/' ?></b> below.
      A plain backup left <?= $home ? 'there' : 'on the server' ?> is the whole database sitting in the open.
    </p>
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?><div class="note note-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice !== ''): ?><div class="note"><?= e($notice) ?></div><?php endif; ?>

<div class="page-grid">
<div>

<div class="card">
  <h2>1. Choose the backup</h2>

  <form method="post" enctype="multipart/form-data">
    <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
    <input type="hidden" name="step" value="upload">
    <div class="row">
      <label class="lbl" for="backup-file">Upload a backup file</label>
      <input type="file" id="backup-file" name="backup"
             accept=".sql,.gz,<?= e(CM_SEAL_EXT) ?>" required<?= cm_upload_limit_attrs() ?>>
      <div class="hint">
        <?php if ($cap > 0): ?>
          <?= $home ? 'This copy' : 'This host' ?> accepts uploads up to <b><?= e(cm_bytes_label($cap)) ?></b>.
          A bigger file goes in <span class="mono"><?= e($where) ?></span> by
          <?= $home ? 'File Explorer' : 'FTP or File Manager' ?> instead &mdash; there is no limit that way, and it
          appears in the list below.
        <?php else: ?>
          A file too big to upload goes in <span class="mono"><?= e($where) ?></span>
          by <?= $home ? 'File Explorer' : 'FTP or File Manager' ?> instead, and appears in the list below.
        <?php endif; ?>
      </div>
    </div>
    <button class="btn" type="submit">Upload</button>
  </form>

  <?php if ($files): ?>
    <h3 class="mt-16">Files <?= $home ? 'waiting here' : 'on the server' ?></h3>
    <?php foreach ($files as $f): ?>
      <div class="list-row">
        <div>
          <b class="mono"><?= e($f['name']) ?></b>
          <div class="muted small">
            <?= e(cm_bytes_label($f['size'])) ?> &middot;
            <?= e(date('j M Y, g:ia', $f['at'])) ?>
          </div>
        </div>
        <span class="spacer"></span>
        <?php 





 ?>
        <div class="row-actions">
          <form method="post">
            <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
            <input type="hidden" name="file" value="<?= e($f['name']) ?>">
            <button class="btn btn-sm" type="submit">Open</button>
          </form>
          <form method="post">
            <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
            <input type="hidden" name="step" value="delete">
            <input type="hidden" name="file" value="<?= e($f['name']) ?>">
            <button class="btn btn-sm btn-quiet" type="submit">Delete</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <p class="hint mt-10">
      <b>Delete them when you are done.</b> A sealed backup here is unreadable
      without its key; a plain one is every client&rsquo;s details and every
      password hash, sitting <?= $home ? 'in a folder on this PC' : 'on the web server' ?>.
    </p>
  <?php else: ?>
    <div class="empty-state mt-16">
      <h3>No files waiting</h3>
      <p>Upload one above, or put it in <span class="mono"><?= e($where) ?></span>.</p>
    </div>
  <?php endif; ?>
</div>

<?php if ($chosen !== '' && $kind !== null && ($kind['kind'] ?? '') === 'sealed' && $report === null): ?>
  <div class="card">
    <h2>2. The key for <span class="mono"><?= e($chosen) ?></span></h2>
    <p class="muted small">
      That file is sealed. It was shown a key when it was made, and nothing can
      open it without those <?= e((string) ($kind['header']['words'] ?? CM_SEAL_WORDS)) ?> words.
    </p>
    <form method="post">
      <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
      <input type="hidden" name="file" value="<?= e($chosen) ?>">
      <div class="row">
        <label class="lbl" for="restore-key">The backup key</label>
        <input type="text" id="restore-key" name="key" class="mono f-full" autocomplete="off"
               spellcheck="false" autocapitalize="none" autocorrect="off" required
               value="<?= e($keyText) ?>"
               placeholder="<?= e((string) ($kind['header']['words'] ?? CM_SEAL_WORDS)) ?> words, separated by spaces">
        <div class="hint">
          Capitals and punctuation do not matter, and the first four letters of
          each word are enough. <b>If one word cannot be read, put a <span class="mono">?</span>
          in its place</b> and press Find the missing word.
        </div>
      </div>
      <button class="btn" type="submit">Open it</button>
      <button class="btn btn-quiet" type="submit" name="step" value="hunt">Find the missing word</button>
    </form>

    <?php 







 ?>
    <?php if ($hunt !== null): ?>
      <?php $pct = (int) round($hunt['from'] / max(1, $hunt['total']) * 100); ?>
      <div class="note mt-14" id="hunt">
        <b>Looking for the missing word.</b>
        Tried <?= e(number_format($hunt['from'])) ?> of
        <?= e(number_format($hunt['total'])) ?> (<?= e((string) $pct) ?>%).
        Roughly <?= e($hunt['left']) ?> left if it is near the end of the list.
        <p class="hint">
          Leave this page open. It carries on by itself &mdash; each step is a
          few seconds long so that nothing <?= $home ? 'on this PC' : 'on your host' ?> can time it out. You can
          stop and come back to it.
        </p>
        <form method="post" id="hunt-form">
          <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
          <input type="hidden" name="step" value="hunt">
          <input type="hidden" name="file" value="<?= e($chosen) ?>">
          <input type="hidden" name="key" value="<?= e($keyText) ?>">
          <input type="hidden" name="from" value="<?= e((string) $hunt['from']) ?>">
          <button class="btn btn-sm" type="submit">Keep looking</button>
        </form>
      </div>
      <script defer src="<?= e(cm_url('js/hunt.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($report !== null && $done === null): ?>
  <div class="card">
    <div class="card-head">
      <h2>2. What is in it</h2>
      <span class="spacer"></span>
      <?php if (!empty($report['sealed'])): ?><span class="pill pill-approved">Sealed</span><?php endif; ?>
    </div>

    <?php 
 ?>
    <div class="summary-strip">
      <div><span class="summary-k">Taken</span> <?= e($report['taken'] !== '' ? $report['taken'] : 'not recorded') ?></div>
      <div><span class="summary-k">From</span> <?= e($report['site'] !== '' ? $report['site'] : 'not recorded') ?></div>
      <div><span class="summary-k">Version</span> <?= e($report['app'] !== '' ? $report['app'] : 'not recorded') ?></div>
      <div><span class="summary-k">Database</span> <?= e($report['schema'] !== '' ? $report['schema'] : 'not recorded') ?></div>
      <div><span class="summary-k">Tables</span> <?= e((string) $report['tables']) ?></div>
      <div><span class="summary-k">Statements</span> <?= e(number_format((int) $report['statements'])) ?></div>
      <?php 

 ?>
      <div><span class="summary-k">Client passwords</span> <?= e(
          $report['passwords'] === CM_BACKUP_PASSWORDS_IN  ? 'included'
        : ($report['passwords'] === CM_BACKUP_PASSWORDS_OUT ? 'NOT included'
        : 'not recorded')) ?></div>
    </div>

    <?php 





 ?>
    <?php if ($report['passwords'] === CM_BACKUP_PASSWORDS_OUT): ?>
      <div class="note mt-14">
        <b>This backup has no client passwords in it, so the ones you have now
        are kept.</b> It was taken with the
        <span class="mono">Include my clients&rsquo; stored passwords</span> box
        left unticked. Each saved password, secret note and recovery code this
        site holds goes back onto the same login after the restore. A login the
        backup does not have, or that has another client or username in it,
        comes back empty, and the report afterwards says how many.
      </div>
    <?php endif; ?>

    <?php if ($report['site'] !== '' && $report['site'] !== cm_base_url()): ?>
      <div class="note note-warn mt-14">
        <b>That backup was taken from a different site.</b> It says
        <span class="mono"><?= e((string) $report['site']) ?></span>, and this is
        <span class="mono"><?= e(cm_base_url()) ?></span>. That is normal when you
        are moving a site or rebuilding one, and it is a mistake worth noticing
        if you are not.
      </div>
    <?php endif; ?>

    <?php if (!empty($report['forward'])): ?>
      <div class="note mt-14">
        That backup is from an older database than this site&rsquo;s files. The
        updates it is missing will be run for you straight afterwards, so the
        files and the database match again &mdash; that gap is what makes screens
        go blank.
      </div>
    <?php endif; ?>

    <?php if (!empty($report['appkey'])): ?>
      <div class="note mt-14">
        It carries its <b>settings key</b>, so stored PayPal, Stripe, Google and
        mail passwords will read back properly.
        <?php $have = cm_app_key_source(); ?>
        <?php if ($have['where'] === 'file' && !hash_equals($have['value'], (string) $report['appkey'])): ?>
          This site already has a <b>different</b> key in
          <span class="mono">storage/appkey.php</span> &mdash; tick below to
          replace it, or the restored credentials will read back as empty.
        <?php elseif ($have['where'] === 'config' && !hash_equals($have['value'], (string) $report['appkey'])): ?>
          This site takes its key from <span class="mono">secret_key</span> in
          <span class="mono">config.php</span>, which this screen will not edit.
          The credentials will read back as empty until that value matches.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="note note-warn mt-14">
        It does <b>not</b> carry a settings key &mdash; a plain backup never can.
        Stored PayPal, Stripe, Google and mail passwords will read back as empty
        unless this site still has the key they were encrypted with. Re-enter
        them in Settings afterwards if they stop working.
      </div>
    <?php endif; ?>
  </div>

  <?php 








 ?>
  <?php if (!empty($report['sealed'])): ?>
    <div class="card">
      <h2>3. Or just decrypt it</h2>
      <p class="muted small">
        Turn this sealed file back into a plain <span class="mono">.sql</span>
        and download it. <b>Nothing here is changed</b> &mdash; this reads a file
        and writes a file, so it is safe to do on any install, including one you
        put up purely to open an old backup.
      </p>
      <form method="post">
        <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
        <input type="hidden" name="step" value="convert">
        <input type="hidden" name="file" value="<?= e($chosen) ?>">
        <input type="hidden" name="key" value="<?= e($keyText) ?>">
        <button class="btn btn-quiet" type="submit">Download it as plain SQL</button>
      </form>
      <p class="hint">
        The plain file is every client&rsquo;s details and every password hash
        with nothing in front of it. Put it somewhere you would put the database
        itself, and delete it when you are done.
      </p>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= !empty($report['sealed']) ? '4' : '3' ?>. Restore it</h2>

    <div class="note note-error">
      <b>This replaces everything.</b> Every client, job, invoice, message and
      user in this database is dropped and rebuilt from that file. Anything
      entered since it was taken is gone, and there is no undo.
      <b>You will be signed out</b>, because the backup brings its own users and
      passwords with it.
    </div>

    <?php if (!cm_migration_is_fresh_install() && $backupAt === null): ?>
      <?php 

 ?>
      <div class="note note-warn mt-14">
        <b>Take a backup of what is here now, first.</b> If this file turns out to
        be the wrong one, that backup is the only way back.
        <a href="<?= e(cm_url('admin/update.php')) ?>">Take one on Update &amp; backup</a>,
        then come back to this page and reload it.
      </div>
    <?php endif; ?>

    <form method="post">
      <?= cm_csrf_field() ?><?= cm_recovery_field() ?>
      <input type="hidden" name="step" value="run">
      <input type="hidden" name="file" value="<?= e($chosen) ?>">
      <input type="hidden" name="key" value="<?= e($keyText) ?>">

      <?php $have = cm_app_key_source(); ?>
      <?php if (!empty($report['appkey']) && $have['where'] === 'file'
                && !hash_equals($have['value'], (string) $report['appkey'])): ?>
        <div class="row">
          <label class="check">
            <input type="checkbox" name="replace_key" value="1">
            Replace this site&rsquo;s settings key with the one in the backup
          </label>
          <div class="hint">
            Without this, the restored credentials cannot be decrypted. With it,
            anything still encrypted under the current key cannot be.
          </div>
        </div>
      <?php endif; ?>

      <div class="row">
        <label class="lbl" for="confirm-word">Type RESTORE to confirm</label>
        <input type="text" id="confirm-word" name="confirm" class="mono" autocomplete="off"
               spellcheck="false" autocapitalize="characters" required placeholder="RESTORE">
      </div>

      <button class="btn btn-danger" type="submit"
              <?= (!cm_migration_is_fresh_install() && $backupAt === null) ? 'disabled' : '' ?>>
        Restore this backup
      </button>
      <?php if (!cm_migration_is_fresh_install() && $backupAt === null): ?>
        <p class="hint">The button turns on once a backup has been taken in this sitting.</p>
      <?php endif; ?>
    </form>
  </div>
<?php endif; ?>

</div>

<aside class="side-rail">
  <div class="card">
    <h2>What a restore does</h2>
    <p class="small">Drops every table and rebuilds it from the file. Then it runs
      any database updates the backup was missing, so the site&rsquo;s files and
      its database agree &mdash; a mismatch there is what makes screens go blank
      rather than error.</p>
    <p class="small">It does <b>not</b> touch the site&rsquo;s files, its version,
      <span class="mono">config.php</span>, or anything in
      <span class="mono">storage/</span> other than the settings key you
      explicitly agree to replace.</p>
  </div>
  <div class="card">
    <h2>If it stops half way</h2>
    <p class="small">It says so, and says after how many statements. There is no
      rolling that back &mdash; MySQL cannot undo a dropped table &mdash; so the
      move is to restore another copy, or the backup you took before starting.
      That is why the button waits for one.</p>
  </div>
  <?php if (!$home): ?>
  <div class="card">
    <h2>The other way in</h2>
    <p class="small">A <b>plain</b> .sql or .sql.gz backup can also be imported
      with phpMyAdmin on any host, with no need for this screen and no need for
      this site to be running at all. A <b>sealed</b> one cannot: only this site
      can open it, and only with its key.</p>
  </div>
  <?php endif; ?>
</aside>
</div>

<?php cm_foot(); ?>
