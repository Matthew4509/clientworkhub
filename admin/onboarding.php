<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/onboarding.php';



require_once CM_LIB . '/publicsite.php';

$admin = cm_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');














    if ($action === 'form_create') {
        $problem = '';
        $catRaw = (string) ($_POST['category_id'] ?? '');
        $newId = cm_onboard_form_create(
            (string) ($_POST['f_name'] ?? ''), (string) ($_POST['f_description'] ?? ''),
            $catRaw === '' ? null : (int) $catRaw, $problem);
        if ($newId > 0) {
            cm_audit('onboarding.form_created', '', null,
                     'form #' . $newId . ' created by ' . (string) $admin['email']);
            cm_flash('Form created — add its questions below.');
            cm_redirect('admin/onboarding.php?form=' . $newId);
        }
        cm_flash($problem !== '' ? $problem : 'The form could not be created.', 'error');
        cm_redirect('admin/onboarding.php');
    }

    if ($action === 'form_save') {
        $fid = (int) ($_POST['form_id'] ?? 0);
        $catRaw = (string) ($_POST['category_id'] ?? '');
        $problem = cm_onboard_form_save($fid, (string) ($_POST['f_name'] ?? ''),
                                        (string) ($_POST['f_description'] ?? ''),
                                        $catRaw === '' ? null : (int) $catRaw);
        cm_flash($problem !== '' ? $problem : 'Saved.', $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/onboarding.php?form=' . $fid);
    }

    if ($action === 'form_delete') {
        $fid = (int) ($_POST['form_id'] ?? 0);
        $problem = cm_onboard_form_delete($fid);
        if ($problem === '') {
            cm_audit('onboarding.form_deleted', '', null,
                     'form #' . $fid . ' deleted by ' . (string) $admin['email']);
        }
        cm_flash($problem !== '' ? $problem : 'Form deleted.', $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/onboarding.php');
    }

    if ($action === 'form_move') {
        $fid = (int) ($_POST['form_id'] ?? 0);
        $dir = (string) ($_POST['dir'] ?? '');
        if ($dir === 'up' || $dir === 'down') {
            $moved = cm_onboard_form_move($fid, $dir);
            cm_flash($moved ? 'Moved ' . $dir . '.'
                            : 'It is already ' . ($dir === 'up' ? 'first' : 'last') . ' in its group.',
                     $moved ? 'ok' : 'warn');
        } else {
            $catRaw = (string) ($_POST['category_id'] ?? '');
            $moved = cm_onboard_form_set_category($fid, $catRaw === '' ? null : (int) $catRaw);
            cm_flash($moved ? 'Moved.' : 'It was already there.', $moved ? 'ok' : 'warn');
        }
        cm_redirect('admin/onboarding.php?move=' . $fid);
    }

    if ($action === 'form_category_add') {
        $name = trim((string) ($_POST['category_new'] ?? ''));
        if ($name === '') {
            cm_flash('Give the category a name.', 'error');
        } else {
            $existed = cm_val('SELECT id FROM onboarding_form_category WHERE name = ?', [$name]) !== null;
            cm_onboard_form_category_ensure($name);
            cm_flash($existed ? '"' . $name . '" already exists — pick it on any form.'
                              : '"' . $name . '" added. Pick it on any form from now on.');
        }
        cm_redirect('admin/onboarding.php');
    }


    if ($action === 'form_questions') {
        $fid = (int) ($_POST['form_id'] ?? 0);
        $form = cm_onboard_form_row($fid);
        if ($form === null) {
            cm_flash('That form no longer exists.', 'error');
            cm_redirect('admin/onboarding.php');
        }
        $made = cm_onboard_questions_save(['form' => $fid], $_POST);
        cm_flash($made > 0
            ? $made . ' question' . ($made === 1 ? '' : 's') . ' added to ' . (string) $form['name'] . '.'
            : 'Nothing to add — write at least one question.', $made > 0 ? 'ok' : 'warn');
        cm_redirect('admin/onboarding.php?form=' . $fid);
    }

    if (cm_onboard_handle_field_post($action, (string) $admin['email'])) {

    }



    if ($action === 'send_form') {
        $blocked = cm_onboard_links_blocked();
        if ($blocked !== '') {
            cm_flash($blocked, 'error');
            cm_redirect('admin/onboarding.php');
        }
        $cid = (int) ($_POST['client_id'] ?? 0);
        $tid = (int) ($_POST['template_id'] ?? 0);



        $days = cm_link_expiry_days($_POST['expiry_days'] ?? null);
        $client = cm_client($cid);
        if ($client) {
            $token = cm_onboard_start($cid, null, $tid > 0 ? $tid : null, null, null, null, null, false, $days);
            cm_flash('Form created — the link is in the list below, ready to send. '
                   . 'It stops working after ' . $days . ' days.');
            cm_audit('onboarding.started', 'client', $cid,
                     'onboarding form created by ' . (string) $admin['email']
                     . ' (expires in ' . $days . ' days)');
        } else {
            cm_flash('Pick the client.', 'error');
        }
        cm_redirect('admin/onboarding.php');
    }
}

$generic = [];
$pending = [];
$done    = [];
$clients = [];
try {
    $generic = cm_onboard_generic_fields();
    $pending = cm_onboard_pending();
    $done = cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, t.name AS service_name
           FROM onboarding_response r
      LEFT JOIN client c ON c.id = r.client_id
      LEFT JOIN job_template t ON t.id = r.template_id
          WHERE r.status = 'complete' ORDER BY r.completed_at DESC LIMIT 30");
    $clients = cm_all("SELECT * FROM client WHERE status <> 'inactive' ORDER BY company, first_name");
} catch (Throwable $ex) {

}
$tpls = [];
try { $tpls = cm_templates(); } catch (Throwable $ex) {}

$viewing = null;
$viewAnswers = [];
if (isset($_GET['view'])) {
    $viewing = cm_row('SELECT r.*, c.first_name, c.last_name, c.company, t.name AS service_name
                         FROM onboarding_response r
                    LEFT JOIN client c ON c.id = r.client_id
                    LEFT JOIN job_template t ON t.id = r.template_id
                        WHERE r.id = ?', [(int) $_GET['view']]);
    if ($viewing) {
        $viewAnswers = cm_all('SELECT * FROM onboarding_answer WHERE response_id = ? ORDER BY id',
                              [(int) $viewing['id']]);
    }
}

cm_head('Onboarding', '', true);
cm_render_flashes();
cm_admin_nav('onboarding');
?>
<div class="topbar">
  <h1>Onboarding</h1>
</div>
<p class="sub">What you ask a client for before you can start, and what they
have sent back.</p>

<?php 

 ?>
<?php 



 ?>
<?= cm_credential_tools_script_once() ?>

<?php 











 ?>
<div class="howto">
  <h2>How onboarding works</h2>
  <ol class="howto-steps">
    <li><b>Write the questions.</b> Below. Everybody is asked these; a service
      can add its own on the <a href="<?= e(cm_url('admin/services.php')) ?>">Services</a> page.</li>
    <li><b>Create a form for a client.</b> Pick who it is for and which
      service. You get a private link.</li>
    <li><b>Send them the link.</b> Copy it, or let the application email it.
      One link, one client &mdash; it opens their form and nobody else's.</li>
    <li><b>Read what comes back.</b> Answers arrive under <b>Answered</b>. Any
      logins they give go straight to that client&rsquo;s Properties tab,
      encrypted, and are never shown on this page.</li>
  </ol>
</div>

<?php 













 ?>
<?php  ?>
<?php
$obForms   = cm_onboard_forms();
$obCats    = cm_onboard_form_category_map();
$openForm  = (int) ($_GET['form'] ?? 0);
$openMove  = (int) ($_GET['move'] ?? 0);


$openPreview = (int) ($_GET['preview'] ?? 0);
?>
<div class="card" id="builder">
  <div class="card-head">
    <h2>Your forms</h2>
    <span class="spacer"></span>
    <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-form-cat">Create a category</button>
    <button class="btn btn-sm" type="button" data-modal-open="dlg-form-new">Create a form</button>
  </div>
  <p class="muted small mb-12">A form is a set of questions you can send a
  client. <b>General questions</b> is the one everybody is asked; the rest are
  yours to build. A service adds its own questions on the
  <a href="<?= e(cm_url('admin/services.php')) ?>">Services</a> page.</p>

  <?php if (!$obForms): ?>
    <p class="note note-warn m-0"><b>No forms yet.</b> The database update for
    forms has not been run &mdash; <a href="<?= e(cm_url('admin/update.php')) ?>">run it</a>
    and the general questions appear here as the first form.</p>
  <?php else: ?>
    <?php foreach (cm_onboard_forms_grouped($obForms) as $grp): ?>
      <?php if ($grp['name'] !== ''): ?>
        <h2 class="svc-group"><?= e($grp['name']) ?></h2>
      <?php endif; ?>
      <ul class="svc-list">
        <?php foreach ($grp['forms'] as $f): $fid = (int) $f['id']; ?>
          <li class="svc-row">
            <button class="svc-grip" type="button" data-modal-open="dlg-formmove-<?= $fid ?>"
                    aria-label="Move <?= e((string) $f['name']) ?>" title="Move this form">
              <span></span><span></span><span></span>
            </button>
            <div class="svc-main">
              <button class="svc-name" type="button" data-modal-open="dlg-form-<?= $fid ?>"><?=
                e((string) $f['name']) ?></button>
              <?php $d = trim((string) $f['description']); ?>
              <?php if ($d !== ''): ?>
                <div class="svc-desc" title="<?= e(preg_replace('/\s+/', ' ', $d)) ?>"><?=
                  e(mb_strimwidth(preg_replace('/\s+/', ' ', $d), 0, 90, '…')) ?></div>
              <?php endif; ?>
              <div class="svc-sub"><?= (int) $f['questions'] ?> question<?= (int) $f['questions'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="svc-tags">
              <?php if ((int) $f['is_general'] === 1): ?>
                <span class="pill pill-approved">everybody</span>
              <?php endif; ?>
            </div>
            <?php 


 ?>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-form-<?= $fid ?>">Edit</button>
            <?php 


 ?>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-preview-<?= $fid ?>">Preview</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php 


 ?>
<?php foreach ($obForms as $f): $fid = (int) $f['id']; $fFields = cm_onboard_form_fields($fid); ?>
  <dialog class="modal modal-wider" id="dlg-form-<?= $fid ?>"<?= $openForm === $fid ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2><?= e((string) $f['name']) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub"><?= (int) $f['is_general'] === 1
        ? 'Asked of every client, whatever they have booked. A service can add more on top.'
        : 'A form of your own. Send it from a client&rsquo;s Communication tab.' ?></p>

      <form method="post" class="mb-16">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="form_save">
        <input type="hidden" name="form_id" value="<?= $fid ?>">
        <div class="form-row">
          <div class="field">
            <label class="lbl" for="fn<?= $fid ?>">Name</label>
            <input type="text" id="fn<?= $fid ?>" name="f_name" class="f-full" maxlength="160"
                   value="<?= e((string) $f['name']) ?>">
          </div>
          <div class="field field-tight">
            <label class="lbl" for="fcat<?= $fid ?>">Category</label>
            <div class="snip-add">
              <select id="fcat<?= $fid ?>" name="category_id" class="f-lg">
                <option value="">&mdash; none &mdash;</option>
                <?php foreach ($obCats as $cid => $cname): ?>
                  <option value="<?= (int) $cid ?>" <?= (int) ($f['category_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?= e($cname) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <label class="lbl" for="fd<?= $fid ?>">Description</label>
          <textarea id="fd<?= $fid ?>" name="f_description" rows="2" class="f-full"><?= e((string) $f['description']) ?></textarea>
          <div class="hint">For you, not the client — it is how you tell two forms apart.</div>
        </div>
        <button class="btn btn-sm btn-quiet" type="submit">Save the name and description</button>
      </form>

      <?php if ($fFields): ?>
        <h3 class="work-h">The form, in order</h3>
        <?= cm_onboard_builder_html($fFields, 'frm' . $fid, e((string) $f['name'])) ?>
      <?php endif; ?>
      <div class="actions mb-16">
        <button class="btn btn-sm" type="button" data-modal-open="dlg-addblk-frm<?= $fid ?>">+ Add a question or a message</button>
      </div>

      <form method="post" id="formqs<?= $fid ?>">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="form_questions">
        <h3 class="work-h">Or add several at once</h3>
        <p class="muted small">The greyed-out wording is a suggestion, not an
        answer — nothing is added until you type it.</p>
        <?= cm_onboard_question_grid_html('frm' . $fid, ['form' => $fid]) ?>
      </form>

      <?= cm_onboard_add_block_window_html('dlg-addblk-frm' . $fid, ['form' => $fid]) ?>
      <?= cm_onboard_field_windows_html($fFields, 'frm' . $fid) ?>

      <div class="modal-foot">
        <button class="btn" type="submit" form="formqs<?= $fid ?>">Save the questions</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        <span class="spacer"></span>
        <?php if ((int) $f['is_general'] !== 1): ?>
          <button class="btn btn-sm btn-danger" type="submit" form="formdel<?= $fid ?>">Delete this form</button>
        <?php endif; ?>
      </div>
    </div>
  </dialog>

  <?php  ?>
  <form id="formdel<?= $fid ?>" method="post"
        data-confirm="Delete &quot;<?= e((string) $f['name']) ?>&quot;? Its questions go with it. Answers already given are kept.">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="form_delete">
    <input type="hidden" name="form_id" value="<?= $fid ?>">
  </form>

  <dialog class="modal modal-wider" id="dlg-preview-<?= $fid ?>"<?= $openPreview === $fid ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2>Preview &mdash; <?= e((string) $f['name']) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">What a client sees. Nothing here can be typed in or
      sent. A service that adds its own questions shows them after these.</p>
      <?= cm_onboard_preview_html($fid) ?>
      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        <span class="spacer"></span>
        <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-form-<?= $fid ?>">Edit it</button>
      </div>
    </div>
  </dialog>

  <dialog class="modal" id="dlg-formmove-<?= $fid ?>"<?= $openMove === $fid ? ' data-modal-autoopen' : '' ?>>
    <div class="modal-box">
      <div class="modal-head">
        <h2>Move <?= e((string) $f['name']) ?></h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Changes where it displays internally, impacts nothing else.</p>
      <h3 class="work-h">Move it up or down</h3>
      <form method="post" class="mb-16">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="form_move">
        <input type="hidden" name="form_id" value="<?= $fid ?>">
        <div class="snip-add">
          <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="up">&uarr; Move up</button>
          <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="down">&darr; Move down</button>
        </div>
      </form>
      <h3 class="work-h">Put it in another group</h3>
      <form method="post" class="mb-16">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="form_move">
        <input type="hidden" name="form_id" value="<?= $fid ?>">
        <div class="snip-add">
          <select name="category_id" class="f-lg" aria-label="Category">
            <option value=""<?= $f['category_id'] === null ? ' selected' : '' ?>>Everything else</option>
            <?php foreach ($obCats as $cid => $cname): ?>
              <option value="<?= (int) $cid ?>"<?= (int) ($f['category_id'] ?? 0) === (int) $cid ? ' selected' : '' ?>><?= e($cname) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-sm btn-quiet" type="submit">Move it</button>
        </div>
        <p class="hint">It lands at the bottom of the group it moves to.</p>
      </form>
      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
      </div>
    </div>
  </dialog>
<?php endforeach; ?>

<dialog class="modal" id="dlg-form-new">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Create a form</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Name it now; the questions come next, in the window
    that opens.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="form_create">
      <div class="row">
        <label class="lbl" for="nf_name">Name</label>
        <input type="text" id="nf_name" name="f_name" class="f-full" maxlength="160"
               placeholder="New photography client, Website handover…">
      </div>
      <div class="row">
        <label class="lbl" for="nf_desc">Description</label>
        <textarea id="nf_desc" name="f_description" rows="2" class="f-full"
                  placeholder="What this form is for, so you know which to send."></textarea>
      </div>
      <div class="row">
        <label class="lbl" for="nf_cat">Category</label>
        <select id="nf_cat" name="category_id" class="f-full">
          <option value="">&mdash; none &mdash;</option>
          <?php foreach ($obCats as $cid => $cname): ?>
            <option value="<?= (int) $cid ?>"><?= e($cname) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Create it</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<dialog class="modal" id="dlg-form-cat">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Create a category</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">Your own words. Pick it on any form.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="form_category_add">
      <div class="row">
        <label class="lbl" for="fcat_new">Category name</label>
        <input type="text" id="fcat_new" name="category_new" class="f-full" maxlength="80"
               placeholder="Photography, Websites, Handover…">
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Create the category</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>

<?php 

 ?>
<?php 



 ?>


<?php 



 ?>
<?php 




 ?>
<div class="card">
  <div class="card-head">
    <h2>Create a form for a client</h2>
  </div>
  <p class="muted small">Pick who it is for and which service. You get a private
  link to send them &mdash; it opens their form and nobody else&rsquo;s.</p>
  <?php 



 ?>
  <form method="post">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="send_form">
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="ob_client">Create a form for</label>
        <?= cm_client_picker('client_id', 0, true, 'ob_client') ?>
      </div>
      <div class="field">
        <label class="lbl" for="ob_tpl">About which service</label>
        <select id="ob_tpl" name="template_id" class="f-full">
          <option value="0">&mdash; just the generic questions &mdash;</option>
          <?php foreach ($tpls as $t): ?>
            <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php 







 ?>
    <div class="row">
      <span class="lbl">How long the link works</span>
      <?= cm_link_expiry_radios('expiry_days') ?>
      <div class="hint">After that the link stops opening and the client is asked to get in touch.
      Links made before this version have no expiry and keep working.</div>
    </div>

    <div class="actions">
      <button class="btn" type="submit">Create the link</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-head">
    <h2>Waiting on a client<?= $pending ? ' — ' . count($pending) : '' ?></h2>
  </div>
  <?php if (!$pending): ?>
    <p class="muted small">Nobody has an open form.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Who</th><th>For</th><th>Sent</th><th>Expires</th><th>The link to send them</th></tr></thead>
        <tbody>
        <?php foreach ($pending as $r): ?>
          <tr>
            <td class="small"><?= $r['client_id']
                  ? e(cm_client_display_name(['id' => $r['client_id'],
                      'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
                      'company' => $r['company'], 'email' => $r['client_email']]))
                  : '<span class="muted">not matched yet</span>' ?></td>
            <td class="small"><?= $r['service_name'] ? e((string) $r['service_name'])
                  : '<span class="muted">the generic form</span>' ?></td>
            <td class="small mono nowrap"><?= e(date('j M', strtotime((string) $r['created_at']))) ?></td>
            <?php 


                  $obExp = array_key_exists('expires_at', $r) && $r['expires_at'] !== null
                         ? (string) $r['expires_at'] : null; ?>
            <td class="small nowrap<?= cm_link_expired($obExp) ? ' is-expired' : '' ?>"><?=
                e(cm_link_expiry_label($obExp)) ?></td>
            <?php 




 ?>
            <td>
              <div class="linkcopy">
                <input type="text" class="mono small" readonly
                       id="ob<?= (int) $r['id'] ?>"
                       value="<?= e(($tk = cm_token_clear($r)) !== '' ? cm_onboard_url($tk) : '') ?>"<?= ($tk === '') ? ' placeholder="Made before this version — send a new form"' : '' ?>>
                <button class="btn btn-sm btn-quiet" type="button"
                        data-copy-for="ob<?= (int) $r['id'] ?>">Copy the link</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>

<?php  ?>
<div class="card">
  <div class="card-head"><h2>Answered</h2></div>
  <?php if (!$done): ?>
    <p class="muted small">Nothing back yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Who</th><th>For</th><th>When</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($done as $r): ?>
          <tr>
            <td class="small"><b><?= $r['client_id']
                  ? e(cm_client_display_name(['id' => $r['client_id'],
                      'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
                      'company' => $r['company'], 'email' => '']))
                  : 'Unmatched' ?></b></td>
            <td class="small"><?= $r['service_name'] ? e((string) $r['service_name'])
                  : '<span class="muted">generic</span>' ?></td>
            <td class="small mono nowrap"><?= e(date('j M', strtotime((string) $r['completed_at']))) ?></td>
            <td class="num">
              <?php 


 ?>
              <button class="btn btn-sm btn-quiet" type="button"
                      data-modal-open="dlg-reply-<?= (int) $r['id'] ?>">Read it</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php foreach ($done as $r): ?>
      <dialog class="modal modal-wider" id="dlg-reply-<?= (int) $r['id'] ?>"<?= $viewing && (int) $viewing['id'] === (int) $r['id'] ? ' data-modal-autoopen' : '' ?>>
        <div class="modal-box">
          <div class="modal-head">
            <h2><?= $r['client_id']
                  ? e(cm_client_display_name(['id' => $r['client_id'],
                      'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
                      'company' => $r['company'], 'email' => '']))
                  : 'Unmatched' ?></h2>
            <?php if ($r['service_name']): ?>
              <span class="pill pill-live"><?= e((string) $r['service_name']) ?></span>
            <?php endif; ?>
            <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
          </div>
          <p class="modal-sub">Answered <?= e(date('j M Y', strtotime((string) $r['completed_at']))) ?>.
          Any sign-in they gave is not here &mdash; it went straight into that
          client&rsquo;s vault.</p>
          <?= cm_onboard_answers_html((int) $r['id']) ?>
          <div class="modal-foot">
            <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
            <?php if ($r['client_id']): ?>
              <span class="spacer"></span>
              <a class="btn btn-sm btn-quiet"
                 href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'] . '&tab=comms#onboarding')) ?>">Open the client</a>
            <?php endif; ?>
          </div>
        </div>
      </dialog>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php cm_foot(); ?>
