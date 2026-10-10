<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/emails.php';

$admin = cm_require_admin();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');









    if ($action === 'move_template') {
        $key = (string) ($_POST['ekey'] ?? '');
        $dir = (string) ($_POST['dir'] ?? '');
        $mt = cm_email_template($key);
        if ($mt !== null) {
            $moved = cm_email_move($key, $dir);
            cm_flash($moved
                ? '"' . (string) $mt['label'] . '" moved ' . $dir . '.'
                : 'It is already ' . ($dir === 'up' ? 'first' : 'last') . ' in its group.',
                $moved ? 'ok' : 'warn');
            if ($moved) {
                cm_audit('email_template.moved', '', null,
                         'message "' . $key . '" moved ' . $dir . ' by ' . (string) $admin['email']);
            }
        }
        cm_redirect('admin/email-templates.php?open=m-' . $key);
    }

    if ($action === 'save_message') {
        $key = (string) ($_POST['ekey'] ?? '');
        $why = cm_email_save($key,
                             (string) ($_POST['subject'] ?? ''),
                             (string) ($_POST['body'] ?? ''),
                             (string) $admin['email']);
        if ($why === '') {
            cm_flash('Saved.');
            cm_redirect('admin/email-templates.php');
        }



        cm_flash($why, 'error');
        cm_redirect('admin/email-templates.php?open=' . urlencode($key));
    }


    if ($action === 'add_message') {
        $why = null;
        $key = cm_email_custom_add((string) ($_POST['label'] ?? ''), (string) ($_POST['subject'] ?? ''),
                                   (string) ($_POST['body'] ?? ''), (string) $admin['email'], $why);
        if ($key !== '') {
            cm_flash('Added. It is in the list below, and on every client\'s Communication tab under Send an email.');
            cm_redirect('admin/email-templates.php');
        }
        cm_flash((string) $why, 'error');
        cm_redirect('admin/email-templates.php?open=new');
    }
    if ($action === 'delete_message') {
        $key = (string) ($_POST['ekey'] ?? '');
        cm_flash(cm_email_custom_delete($key, (string) $admin['email'])
            ? 'Deleted.' : 'Only a message you added can be deleted; the shipped ones can be reworded or put back.',
            cm_email_is_custom_key($key) ? 'ok' : 'warn');
        cm_redirect('admin/email-templates.php');
    }
    if ($action === 'reset_message') {
        $key = (string) ($_POST['ekey'] ?? '');
        cm_email_reset($key, (string) $admin['email']);
        cm_flash('Put back to the wording it shipped with.');
        cm_redirect('admin/email-templates.php?open=' . urlencode($key));
    }
}

$templates = [];
try { $templates = cm_email_templates(); } catch (Throwable $ex) {}


$open = (string) ($_GET['open'] ?? '');




$asTiles = $templates;
$signin  = $asTiles['signin_code'] ?? null;
unset($asTiles['signin_code']);

cm_head('Email templates', '', true);
cm_render_flashes();
cm_admin_nav('messages');
?>
<div class="topbar">
  <h1>Email templates</h1>
  <a class="btn btn-quiet" href="<?= e(cm_url('admin/settings.php')) ?>">Email settings</a>
  <button class="btn" type="button" data-modal-open="dlg-tpl-new">+ New message</button>
</div>
<p class="sub">How everything this application sends is worded. Click one to
read it or change it.</p>

<?php if (!cm_mail_enabled()): ?>
  <div class="note note-warn">
    <b>Sending is switched off.</b> Messages are written to
    <span class="mono">storage/mail/</span> instead of going out, so you can
    read exactly what a client would have received.
    <a href="<?= e(cm_url('admin/settings.php')) ?>">Turn it on under Settings → Email</a>
    when you are ready.
  </div>
<?php endif; ?>

<?php 



 ?>
<?php 





















 ?>
<?php



  $tplGroups = ['Sent to a client' => [], 'Security messages' => []];
  foreach ($asTiles as $key => $t) {
      $tplGroups[$t['locked'] ? 'Security messages' : 'Sent to a client'][$key] = $t;
  }
?>
<div class="card">
  <?php 

















 ?>
  </ul>

  <?php foreach ($tplGroups as $groupName => $rows): ?>
    <?php if (!$rows) continue; ?>
    <h2 class="svc-group"><?= e($groupName) ?></h2>
    <ul class="svc-list">
      <?php foreach ($rows as $key => $t): ?>
        <li class="svc-row">
          <?php 






 ?>
          <button class="svc-grip" type="button" data-modal-open="dlg-tplm-<?= e($key) ?>"
                  aria-label="Move <?= e($t['label']) ?>" title="Move this message">
            <span></span><span></span><span></span>
          </button>
          <div class="svc-main">
            <button class="svc-name" type="button" data-modal-open="dlg-tpl-<?= e($key) ?>"><?=
              e($t['label']) ?></button>
            <?php 


 ?>
            <div class="svc-sub"><?= e($t['subject']) ?></div>
          </div>
          <div class="svc-tags">
            <?php if ($t['locked']): ?>
              <span class="pill pill-pending">wording fixed</span>
            <?php elseif (!empty($t['custom'])): ?>
              <span class="pill pill-live">yours</span>
            <?php elseif ($t['edited']): ?>
              <span class="pill pill-live">reworded</span>
            <?php else: ?>
              <span class="pill pill-approved">as it shipped</span>
            <?php endif; ?>
          </div>
          <button class="btn btn-sm btn-quiet" type="button"
                  data-modal-open="dlg-tpl-<?= e($key) ?>"><?=
            $t['locked'] ? 'Read' : 'Reword' ?></button>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</div>

<?php 


 ?>
<?php foreach ($asTiles as $key => $t): ?>
  <dialog class="modal" id="dlg-tplm-<?= e($key) ?>"<?= (string) ($_GET['open'] ?? '') === 'm-' . $key ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2>Move <?= e($t['label']) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Changes where it sits on this specific page.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="move_template">
        <input type="hidden" name="ekey" value="<?= e($key) ?>">
        <div class="snip-add">
          <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="up">&uarr; Move up</button>
          <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="down">&darr; Move down</button>
        </div>
        <p class="hint">It moves within <b><?= e($t['locked'] ? 'Security messages' : 'Sent to a client') ?></b>.
        The window stays open, so you can press it again.</p>
      </form>
      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
      </div>
    </div>
  </dialog>
<?php endforeach; ?>

<?php 


 ?>


<?php foreach ($asTiles as $key => $t): ?>
  <dialog class="modal modal-wide" id="dlg-tpl-<?= e($key) ?>"
          <?= $open === $key ? 'data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= e($t['label']) ?></h2>
        <?php if ($t['locked']): ?>
          <span class="pill pill-pending">wording fixed</span>
        <?php elseif ($t['edited']): ?>
          <span class="pill pill-live">reworded</span>
        <?php endif; ?>
        <span class="spacer"></span>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub"><b>Sent:</b> <?= e($t['when']) ?></p>

      <?php if ($t['locked']): ?>
        <?php 
 ?>
        <div class="note note-warn">
          <b>This one cannot be reworded, on purpose.</b> It is a security
          message — the wording is what tells somebody whether a request was
          theirs, and what to do if it was not. A reworded version is exactly
          what a convincing phishing email looks like.
        </div>
        <div class="table-wrap">
          <table>
            <tr><th class="f-base">Subject</th><td class="mono small"><?= e((string) $t['subject']) ?></td></tr>
            <tr><th>Message</th><td class="small prewrap"><?= e((string) $t['body']) ?></td></tr>
          </table>
        </div>
        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      <?php else: ?>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="save_message">
          <input type="hidden" name="ekey" value="<?= e($key) ?>">
          <div class="row">
            <label class="lbl" for="subj-<?= e($key) ?>">Subject</label>
            <input type="text" id="subj-<?= e($key) ?>" name="subject" maxlength="200"
                   class="f-full" value="<?= e((string) $t['subject']) ?>">
          </div>
          <div class="row">
            <label class="lbl" for="body-<?= e($key) ?>">Message</label>
            <textarea id="body-<?= e($key) ?>" name="body" rows="12"
                      class="mono f-full"><?= e((string) $t['body']) ?></textarea>
            <?php 










 ?>
            <div class="hint">An unsubscribe link is included in all emails
            sent by this platform.</div>
          </div>
          <?php if ($t['tokens']): ?>
            <div class="note note-info small">
              <b>Must keep:</b>
              <?php foreach ($t['tokens'] as $tok): ?><span class="mono"><?= e($tok) ?></span> <?php endforeach; ?>
              — without it the message does not work, so saving without it is refused.
            </div>
          <?php endif; ?>
          <div class="modal-foot">
            <button class="btn" type="submit">Save</button>
            <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
            <span class="spacer"></span>
            <?php if ($t['updated_at'] && (string) $t['updated_by'] !== ''): ?>
              <span class="muted small">last changed
                <?= e(date('j M Y', strtotime((string) $t['updated_at']))) ?>
                by <?= e((string) $t['updated_by']) ?></span>
            <?php endif; ?>
          </div>
        </form>

        <?php 

 ?>
        <?php if (!empty($t['custom'])): ?>
          <form method="post" class="mt-8"
                data-confirm="Delete &quot;<?= e($t['label']) ?>&quot;? Anything already sent with it is kept in Messages.">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="delete_message">
            <input type="hidden" name="ekey" value="<?= e($key) ?>">
            <button class="btn btn-sm btn-quiet" type="submit">Delete this message</button>
          </form>
        <?php elseif ($t['edited']): ?>
          <form method="post" class="mt-8"
                data-confirm="Put &quot;<?= e($t['label']) ?>&quot; back to the wording it shipped with? Your version is replaced.">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="reset_message">
            <input type="hidden" name="ekey" value="<?= e($key) ?>">
            <button class="btn btn-sm btn-quiet" type="submit">Put back the original</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </dialog>
<?php endforeach; ?>

<?php 



 ?>
<?php if ($signin !== null): ?>
  <div class="card" id="msg-signin_code">
    <div class="card-head">
      <h2><?= e($signin['label']) ?></h2>
      <span class="pill pill-pending">wording fixed</span>
    </div>

    <p class="muted small mb-12"><b>Sent:</b> <?= e($signin['when']) ?></p>

    <div class="note note-warn mb-12">
      <b>This one cannot be reworded, on purpose.</b> It is a security
      message — the wording is what tells somebody whether a request was
      theirs, and what to do if it was not. A reworded version is exactly
      what a convincing phishing email looks like.
    </div>
    <div class="table-wrap">
      <table>
        <tr><th class="f-base">Subject</th><td class="mono small"><?= e((string) $signin['subject']) ?></td></tr>
        <tr><th>Message</th><td class="small prewrap"><?= e((string) $signin['body']) ?></td></tr>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php  ?>
<div class="card">
  <h2>Merge codes</h2>
  <p class="muted small mb-12">Type one into any message and it fills itself
  in when the message is sent. A code with nothing behind it becomes empty —
  never braces in a client's inbox.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th class="f-lg">Code</th><th>Becomes</th></tr></thead>
      <tbody>
        <?php foreach (cm_email_codes() as $code => $what): ?>
          <tr><td class="mono small"><?= e($code) ?></td><td class="small"><?= e($what) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php  ?>
<dialog class="modal modal-wide" id="dlg-tpl-new"<?= $open === 'new' ? ' data-modal-autoopen' : '' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2>New message</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">A message of your own. Nothing sends it by itself &mdash; it is
    offered on every client&rsquo;s Communication tab under <b>Send an email</b>,
    filled in for that client, and you read it before it goes.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="add_message">
      <div class="row">
        <label class="lbl" for="new-label">Name <span class="muted">(for your list, the client never sees it)</span></label>
        <input type="text" id="new-label" name="label" maxlength="120" class="f-full" value="<?= e((string) ($_POST['label'] ?? '')) ?>" placeholder="Quote follow-up, Holiday closure, Welcome…">
      </div>
      <div class="row">
        <label class="lbl" for="new-subject">Subject</label>
        <input type="text" id="new-subject" name="subject" maxlength="200" class="f-full" value="<?= e((string) ($_POST['subject'] ?? '')) ?>">
      </div>
      <div class="row">
        <label class="lbl" for="new-body">Message</label>
        <textarea id="new-body" name="body" rows="12" class="mono f-full"><?= e((string) ($_POST['body'] ?? "Hello {{client.first_name}},\n\n\n\n{{signature}}")) ?></textarea>
        <div class="hint">Merge codes work here as in every message: <span class="mono">{{client.first_name}}</span>,
        <span class="mono">{{client.name}}</span>, <span class="mono">{{business.name}}</span>,
        <span class="mono">{{my_link}}</span>, <span class="mono">{{signature}}</span>. An unsubscribe link is
        included in all emails sent by this platform.</div>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Add it</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>
<?php cm_foot(); ?>
