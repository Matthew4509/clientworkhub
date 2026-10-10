<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/snippets.php';
require_once CM_LIB . '/publicsite.php';
require_once CM_LIB . '/servicefinder.php';
require_once CM_LIB . '/onboarding.php';

$admin = cm_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_template') {













        require_once CM_LIB . '/services.php';
        $name = trim((string) ($_POST['name'] ?? ''));
        $amt  = trim((string) ($_POST['amount'] ?? ''));
        $freq = isset(cm_billing_frequencies()[(string) ($_POST['billing_freq'] ?? '')])
              ? (string) $_POST['billing_freq'] : 'oneoff';




        $span = (string) ($_POST['frequency'] ?? '') === 'daily' ? 'daily' : 'oneoff';






        $hours = trim((string) ($_POST['total_hours'] ?? ''));
        $perday = trim((string) ($_POST['per_day_minutes'] ?? ''));
        $setup = trim((string) ($_POST['setup_minutes'] ?? ''));
        $compl = trim((string) ($_POST['completion_minutes'] ?? ''));
        $mode  = (string) ($_POST['completion_mode'] ?? 'last_day') === 'own_day' ? 'own_day' : 'last_day';

        $bad = '';
        if ($name === '' || mb_strlen($name) > 160) $bad = 'Give the template a name — "Facebook weekly management".';
        $cents = 0;
        if ($amt !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amt)) $bad = 'The price: a plain amount, like 250 or 199.50.';
            else $cents = (int) round(((float) $amt) * 100);
        }
        $totalMin = 0;
        if ($hours !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $hours)) $bad = 'Total time: hours, like 7 or 3.5.';
            else $totalMin = (int) round(((float) $hours) * 60);
        }
        $perDayMin = $perday !== '' && ctype_digit($perday) ? (int) $perday : 0;
        if ($perday !== '' && !ctype_digit($perday)) $bad = 'Time per day: minutes, like 60.';
        if ($totalMin > 0 && $perDayMin > 0) {
            $bad = 'Fill in time per day OR total time, not both — they answer the same question two ways.';
        }
        $setupMin = $setup !== '' && ctype_digit($setup) ? (int) $setup : 0;
        $complMin = $compl !== '' && ctype_digit($compl) ? (int) $compl : 0;
        $durDays  = trim((string) ($_POST['duration_days'] ?? ''));
        $durDays  = $durDays !== '' && ctype_digit($durDays) ? min(365, (int) $durDays) : 0;









        if ($span === 'oneoff') {
            $durDays   = 1;
            $perDayMin = 0;
        }

        if ($bad !== '') {
            cm_flash($bad, 'error');
        } else {




            $taxable = cm_tax_charges() ? (!empty($_POST['taxable']) ? 1 : 0) : 1;





            $blurb = trim((string) ($_POST['public_blurb'] ?? ''));
            if (mb_strlen($blurb) > 500) $blurb = mb_substr($blurb, 0, 500);












            $catRaw = (int) ($_POST['category_id'] ?? 0);
            $catId  = ($catRaw > 0 && isset(cm_service_category_map()[$catRaw])) ? $catRaw : null;

            $newTid = cm_insert('INSERT INTO job_template (name, notes, public_blurb, category_id, amount_cents,
                                                 billing_freq, taxable, total_minutes, per_day_minutes,
                                                 setup_minutes, completion_minutes, completion_mode,
                                                 duration_days, frequency, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                      [$name, '', $blurb, $catId, $cents, $freq, $taxable, $totalMin, $perDayMin, $setupMin,
                       $complMin, $mode, $durDays, $span, cm_now(), cm_now()]);
















            cm_audit('template.created', '', null,
                     'template "' . $name . '" created by ' . (string) $admin['email']);
            cm_flash('"' . $name . '" created.');
            cm_redirect('admin/services.php');
        }
        cm_redirect('admin/services.php');
    }



































    if ($action === 'add_snippet') {
        $body = trim((string) ($_POST['snippet_body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 400) {
            cm_flash('Write the line as you would want the client to read it — up to 400 characters.', 'error');
        } else {
            cm_snippet_add($body);
            cm_flash('Added. It is now offered on every job, until you tag it to a Service below.');
        }
        cm_redirect('admin/services.php?tab=snippets');
    }

    if ($action === 'edit_snippet') {
        $sid  = (int) ($_POST['snippet_id'] ?? 0);
        $body = trim((string) ($_POST['snippet_body'] ?? ''));
        if ($sid > 0 && $body !== '' && mb_strlen($body) <= 400) {
            cm_snippet_update($sid, $body);




            cm_flash('Reworded.');
        }
        cm_redirect('admin/services.php?tab=snippets');
    }





    if ($action === 'move_snippet') {
        $sid = (int) ($_POST['snippet_id'] ?? 0);
        $dir = (string) ($_POST['dir'] ?? '');
        if ($sid > 0 && cm_snippet($sid) !== null) {
            $moved = cm_snippet_move($sid, $dir);
            cm_flash($moved
                ? 'Moved ' . $dir . '.'
                : 'It is already ' . ($dir === 'up' ? 'first' : 'last') . ' in the list.',
                $moved ? 'ok' : 'warn');
        }
        cm_redirect('admin/services.php?tab=snippets&open=snipm-' . $sid);
    }

    if ($action === 'archive_snippet') {
        $sid = (int) ($_POST['snippet_id'] ?? 0);
        $on  = !empty($_POST['restore']);
        if ($sid > 0) {
            cm_snippet_archive($sid, !$on);
            cm_flash($on ? 'Back in the list.' : 'Put away — it stays on every job that already used it.');
        }
        cm_redirect('admin/services.php?tab=snippets');
    }
















































    if (in_array($action, ['add_field', 'save_field', 'delete_field', 'move_field'], true)) {
        require_once CM_LIB . '/onboarding.php';
        cm_onboard_handle_field_post($action, (string) $admin['email']);
    }










    if ($action === 'add_fields') {
        require_once CM_LIB . '/onboarding.php';
        $tid = (int) ($_POST['template_id'] ?? 0);
        $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$tid]);
        if (!$tpl) {
            cm_flash('That service no longer exists.', 'error');
            cm_redirect('admin/services.php');
        }

        $labels = (array) ($_POST['q_label'] ?? []);
        $types  = (array) ($_POST['q_type'] ?? []);
        $reqs   = (array) ($_POST['q_required'] ?? []);

        $made = 0;
        foreach ($labels as $i => $raw) {
            if (trim((string) $raw) === '') continue;
            cm_onboard_field_add($tid, (string) $raw, (string) ($types[$i] ?? 'text'),
                                 '', '', !empty($reqs[$i]));
            $made++;
        }

        if ($made > 0) {
            cm_audit('template.updated', '', null,
                     $made . ' onboarding question(s) added to service #' . $tid
                   . ' by ' . (string) $admin['email']);
        }
        cm_flash($made > 0
            ? $made . ' question' . ($made === 1 ? '' : 's') . ' added to ' . (string) $tpl['name'] . '.'
            : 'Nothing to add — write at least one question.',
            $made > 0 ? 'ok' : 'warn');
        cm_redirect('admin/services.php?open=svco-' . $tid);
    }

    if ($action === 'save_bookings') {
        $tid = (int) ($_POST['template_id'] ?? 0);
        $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$tid]);
        if ($tpl) {
            cm_q('UPDATE job_template SET published = ?, featured = ?, public_sort = ?, updated_at = ?
                   WHERE id = ?',
                 [





                  !empty($_POST['published']) ? 1 : 0,
                  !empty($_POST['featured']) ? 1 : 0,
                  ctype_digit((string) ($_POST['public_sort'] ?? '')) ? (int) $_POST['public_sort'] : 100,
                  cm_now(), $tid]);






            if (!empty($_POST['onboard_shown'])) {
                require_once CM_LIB . '/onboarding.php';
                $when = (string) ($_POST['onboard_when'] ?? 'accept');
                if (!isset(cm_onboard_when_options()[$when])) $when = 'accept';
                cm_q('UPDATE job_template SET onboard_when = ? WHERE id = ?', [$when, $tid]);
            }




            if (!empty($_POST['tax_shown'])) {
                cm_q('UPDATE job_template SET taxable = ? WHERE id = ?',
                     [!empty($_POST['taxable']) ? 1 : 0, $tid]);
            }

            cm_audit('template.updated', '', null,
                     'service #' . $tid . ' booking settings saved by ' . (string) $admin['email']);
            cm_flash(!empty($_POST['published'])
                ? '"' . (string) $tpl['name'] . '" is on your client portal.'
                : 'Saved. "' . (string) $tpl['name'] . '" is not on your client portal.');
        }
        cm_redirect('admin/services.php');
    }

    if ($action === 'save_service') {
        require_once CM_LIB . '/services.php';
        $sid = (int) ($_POST['template_id'] ?? 0);
        $problem = cm_service_save($sid, $_POST);
        if ($problem === '') {
            cm_audit('template.updated', '', null,
                     'service #' . $sid . ' updated by ' . (string) $admin['email']);
        }
        cm_flash($problem !== '' ? $problem : 'Saved.', $problem !== '' ? 'error' : 'ok');
        cm_redirect('admin/services.php');
    }

    if ($action === 'move_service') {
        require_once CM_LIB . '/services.php';
        $sid = (int) ($_POST['template_id'] ?? 0);
        $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$sid]);
        if ($tpl) {
            $dir = (string) ($_POST['dir'] ?? '');
            if ($dir === 'up' || $dir === 'down') {
                $moved = cm_service_move($sid, $dir);
                cm_flash($moved
                    ? '"' . (string) $tpl['name'] . '" moved ' . $dir . '.'
                    : 'It is already ' . ($dir === 'up' ? 'first' : 'last') . ' in its group.',
                    $moved ? 'ok' : 'warn');
            } else {
                $raw = (string) ($_POST['category_id'] ?? '');
                $cat = $raw === '' ? null : (int) $raw;
                $moved = cm_service_set_category($sid, $cat);
                cm_flash($moved
                    ? '"' . (string) $tpl['name'] . '" moved to '
                      . ($cat === null ? 'Everything else' : '"' . cm_service_category_map()[$cat] . '"')
                      . ', at the bottom of it.'
                    : 'It was already there.', $moved ? 'ok' : 'warn');
            }
            cm_audit('template.updated', '', null,
                     'service #' . $sid . ' moved by ' . (string) $admin['email']);
        }


        cm_redirect('admin/services.php?open=svcm-' . $sid);
    }

    if ($action === 'add_service_category') {
        require_once CM_LIB . '/services.php';
        $name = trim((string) ($_POST['category_new'] ?? ''));
        if ($name === '') {
            cm_flash('Give the category a name.', 'error');
        } else {
            $had = cm_service_category_map();
            $cid = cm_service_category_ensure($name);
            cm_flash($cid > 0 && isset($had[$cid])
                ? '"' . $name . '" already exists — pick it on any service.'
                : '"' . $name . '" added. Pick it on any service from now on.');
        }
        cm_redirect('admin/services.php');
    }






    if ($action === 'finder_q_add') {
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($prompt === '') {
            cm_flash('Write the question first.', 'error');
        } else {
            $next = (int) (cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM service_finder_question') ?? 10);
            cm_insert('INSERT INTO service_finder_question (prompt, hint, sort, created_at, updated_at)
                       VALUES (?, ?, ?, NOW(), NOW())', [$prompt, '', $next]);
            cm_flash('Question added. Give it some answers.');
        }
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'finder_q_save') {
        $qid = (int) ($_POST['qid'] ?? 0);
        $prompt = trim((string) ($_POST['prompt'] ?? ''));
        if ($qid > 0 && $prompt !== '') {
            cm_q('UPDATE service_finder_question SET prompt = ?, hint = ?, updated_at = NOW()
                   WHERE id = ?', [$prompt, trim((string) ($_POST['hint'] ?? '')), $qid]);
            cm_flash('Saved.');
        } else {
            cm_flash('A question needs something to ask.', 'error');
        }
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'finder_q_delete') {
        $qid = (int) ($_POST['qid'] ?? 0);



        if ($qid > 0) cm_q('DELETE FROM service_finder_question WHERE id = ?', [$qid]);
        cm_flash('Question removed, with its answers.');
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'finder_o_add') {
        $qid = (int) ($_POST['qid'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($qid > 0 && $label !== '' && cm_val('SELECT id FROM service_finder_question WHERE id = ?', [$qid])) {
            $next = (int) (cm_val('SELECT COALESCE(MAX(sort), 0) + 10 FROM service_finder_option
                                    WHERE question_id = ?', [$qid]) ?? 10);
            cm_insert('INSERT INTO service_finder_option (question_id, label, sort) VALUES (?, ?, ?)',
                      [$qid, $label, $next]);
            cm_flash('Answer added. Now say what it suggests.');
        } else {
            cm_flash('Give the answer some words.', 'error');
        }
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'finder_o_save') {
        $oid = (int) ($_POST['oid'] ?? 0);
        $label = trim((string) ($_POST['label'] ?? ''));
        $opt = $oid > 0 ? cm_row('SELECT * FROM service_finder_option WHERE id = ?', [$oid]) : null;
        if ($opt === null || $label === '') {
            cm_flash('That answer could not be saved.', 'error');
            cm_redirect('admin/services.php?tab=finder');
        }
        cm_q('UPDATE service_finder_option SET label = ? WHERE id = ?', [$label, $oid]);



        cm_q('DELETE FROM service_finder_points WHERE option_id = ?', [$oid]);
        $kept = 0;
        foreach ((array) ($_POST['templates'] ?? []) as $raw) {
            $tid = (int) $raw;
            if ($tid <= 0) continue;
            if (!cm_val('SELECT id FROM job_template WHERE id = ? AND published = 1', [$tid])) continue;
            cm_q('INSERT INTO service_finder_points (option_id, template_id) VALUES (?, ?)', [$oid, $tid]);
            $kept++;
        }
        cm_flash($kept === 0
            ? 'Saved, but that answer points at nothing yet, so it cannot help anybody.'
            : 'Saved — that answer suggests ' . $kept . ' service' . ($kept === 1 ? '' : 's') . '.',
            $kept === 0 ? 'error' : 'ok');
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'finder_o_delete') {
        $oid = (int) ($_POST['oid'] ?? 0);
        if ($oid > 0) cm_q('DELETE FROM service_finder_option WHERE id = ?', [$oid]);
        cm_flash('Answer removed.');
        cm_redirect('admin/services.php?tab=finder');
    }

    if ($action === 'delete_template') {
        $tid = (int) ($_POST['template_id'] ?? 0);
        $tpl = cm_row('SELECT * FROM job_template WHERE id = ?', [$tid]);
        if ($tpl) {
            cm_q('DELETE FROM job_template WHERE id = ?', [$tid]);
            cm_audit('template.deleted', '', null,
                     'template "' . (string) $tpl['name'] . '" deleted by ' . (string) $admin['email']);
            cm_flash('Template deleted. Jobs stamped from it are untouched.');
        }
        cm_redirect('admin/services.php');
    }
}

require_once CM_LIB . '/services.php';



$tab = (string) ($_GET['tab'] ?? 'services');
if (!in_array($tab, ['services', 'snippets', 'finder'], true)) $tab = 'services';

$templates = [];
try { $templates = cm_templates(); } catch (Throwable $ex) {}
$snippets = [];
try { $snippets = cm_snippets(true); } catch (Throwable $ex) {}

cm_head('Services', '', true);
cm_render_flashes();
cm_admin_nav('services');
?>
<?php 


 ?>
<?php 





 ?>
<div class="card-head">
  <h1>Services</h1>
  <span class="spacer"></span>
  <button class="btn" type="button" data-modal-open="dlg-new-service">+ New service</button>
</div>

<nav class="subnav" aria-label="Sections">
  <?php 
 ?>
  <?php foreach (['services' => 'Services', 'snippets' => 'Snippets',
                  'finder' => 'Service finder'] as $k => $label): ?>
    <a class="subnav-item<?= $tab === $k ? ' is-on' : '' ?>"
       href="<?= e(cm_url('admin/services.php?tab=' . $k)) ?>"
       <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'services'): ?>
<?php  ?>
<p class="sub">List your services and pricing, and set whether each is a
one-time job or a service spread over several days.</p>

<?php 











 ?>

<?php if (!$templates): ?>
  <div class="card"><p class="muted small">Nothing yet. A service is a job you
  sell more than once &mdash; give it a price and a time and every new job
  made from it starts with both.</p></div>
<?php else: ?>
  <div class="card">
    <?php foreach (cm_services_grouped($templates) as $grp): ?>
      <?php if ($grp['name'] !== ''): ?>
        <h2 class="svc-group"><?= e($grp['name']) ?></h2>
      <?php endif; ?>
      <ul class="svc-list">
        <?php foreach ($grp['services'] as $t): ?>
          <li class="svc-row">
            <?php 


 ?>
            <button class="svc-grip" type="button" data-modal-open="dlg-svcm-<?= (int) $t['id'] ?>"
                    aria-label="Move <?= e((string) $t['name']) ?>" title="Move this service">
              <span></span><span></span><span></span>
            </button>
            <div class="svc-main">
              <button class="svc-name" type="button" data-modal-open="dlg-svc-<?= (int) $t['id'] ?>"><?=
                e((string) $t['name']) ?></button>
              <?php 




 ?>
              <?php $blurb = trim((string) ($t['public_blurb'] ?? '')); ?>
              <?php if ($blurb !== ''): ?>
                <div class="svc-desc" title="<?= e(preg_replace('/\s+/', ' ', $blurb)) ?>"><?=
                  e(mb_strimwidth(preg_replace('/\s+/', ' ', $blurb), 0, 90, '…')) ?></div>
              <?php endif; ?>
              <div class="svc-sub"><?= e(cm_service_summary($t)) ?></div>
            </div>
            <?php 

 ?>
            <div class="svc-tags">
              <?php if ((int) $t['published'] === 1): ?>
                <span class="pill pill-approved">public</span>
              <?php endif; ?>
              <?php if (cm_tax_charges() && (int) $t['taxable'] === 0): ?>
                <span class="pill">no <?= e(cm_tax_settings()['name']) ?></span>
              <?php endif; ?>
              <?php 



 ?>
            </div>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-svc-<?= (int) $t['id'] ?>">Manage</button>
            <?php 












 ?>
            <?php 
 ?>
            <details class="action-more">
              <summary class="btn btn-sm btn-quiet">More</summary>
              <div class="action-more-body">
                <button class="btn btn-sm btn-quiet" type="button"
                        data-modal-open="dlg-svcb-<?= (int) $t['id'] ?>">Bookings</button>
                <button class="btn btn-sm btn-quiet" type="button"
                        data-modal-open="dlg-svco-<?= (int) $t['id'] ?>">Onboarding</button>
              </div>
            </details>
            <?php 














 ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>

    <?php 


 ?>
    <?php 

 ?>
    <div class="tile-admin-foot mt-8">
      <?php 

















 ?>
      <?php if (cm_public_enabled()): ?>
        <a class="btn btn-sm btn-quiet" href="<?= e(cm_portal_url()) ?>"
           target="_blank" rel="noopener">Preview booking page &rarr;</a>
      <?php else: ?>
        <span class="hint">Your client portal is off, so there is no booking
        page yet &mdash; <a href="<?= e(cm_url('admin/settings.php')) ?>">turn it
        on in Settings</a>.</span>
      <?php endif; ?>
      <span class="spacer"></span>
      <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-svc-cat">Create a category</button>
    </div>
  </div>

  <dialog class="modal" id="dlg-svc-cat">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Create a category</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">Your own words &mdash; by discipline, by duration, by
      platform. Pick it on any service from its Manage window.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_service_category">
        <div class="row">
          <label class="lbl" for="svc_cat_new">Category name</label>
          <input class="f-full" type="text" id="svc_cat_new" name="category_new" maxlength="80"
                 placeholder="Web management, 7-day, YouTube…">
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
  <?php foreach ($templates as $t): ?>
    <?php 














 ?>
    <?php
      $tid = (int) $t['id'];






      $svcSpans = (string) ($t['frequency'] ?? '') === 'daily'
               || ((string) ($t['frequency'] ?? '') === '' && (int) $t['duration_days'] > 0);
      $svcTotalH = (int) ($t['total_minutes'] ?? 0) > 0
        ? rtrim(rtrim(number_format($t['total_minutes'] / 60, 2, '.', ''), '0'), '.') : '';
    ?>
    <dialog class="modal modal-widest" id="dlg-svc-<?= $tid ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= e((string) $t['name']) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">What this service is, how its work falls, and what
        it sells for. Jobs are stamped from it.</p>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="save_service">
          <input type="hidden" name="template_id" value="<?= $tid ?>">

          <div class="form-2col">
            <div class="form-col">

              <section class="form-step">
                <h3><span class="step-n">1</span> Service details</h3>
                <div class="row">
                  <label class="lbl" for="sn<?= $tid ?>">Service name</label>
                  <input type="text" id="sn<?= $tid ?>" name="name" class="f-full"
                         maxlength="160" value="<?= e((string) $t['name']) ?>" required>
                </div>
                <div class="row">
                  <label class="lbl" for="sd<?= $tid ?>">Description <span class="muted">(optional)</span></label>
                  <textarea id="sd<?= $tid ?>" name="public_blurb" rows="3" class="f-full"
                            maxlength="500"><?= e((string) ($t['public_blurb'] ?? '')) ?></textarea>
                  <div class="hint">Shown on the client portal, if you use it.</div>
                </div>
                <?php 


 ?>
                <?php $cats = cm_service_category_map(); ?>
                <div class="row">
                  <label class="lbl" for="sc<?= $tid ?>">Category</label>
                  <div class="snip-add">
                    <select id="sc<?= $tid ?>" name="category_id" class="f-lg">
                      <option value="0">&mdash; none &mdash;</option>
                      <?php foreach ($cats as $cid => $cname): ?>
                        <option value="<?= (int) $cid ?>" <?= (int) ($t['category_id'] ?? 0) === (int) $cid ? 'selected' : '' ?>><?=
                          e($cname) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-quiet" type="button" data-modal-open="dlg-svc-cat">Add new</button>
                  </div>
                </div>
              </section>

              <section class="form-step">
                <h3><span class="step-n">2</span> Work required</h3>

                <div class="row">
                  <label class="lbl">How is this work completed?</label>
                  <div class="caps-pick caps-2 caps-abreast" data-shows="svcedit-<?= $tid ?>">
                    <label class="caps-row">
                      <input type="radio" name="frequency" value="oneoff" <?= $svcSpans ? '' : 'checked' ?>>
                      <span><b>One-off</b>
                        <span class="muted small d-block">Done in one day</span></span>
                    </label>
                    <label class="caps-row">
                      <input type="radio" name="frequency" value="daily" <?= $svcSpans ? 'checked' : '' ?>>
                      <span><b>Spread over several days</b>
                        <span class="muted small d-block">A share each working day.</span></span>
                    </label>
                  </div>
                </div>

                <?php 

 ?>
                <div>
                  <h4 class="work-h">Schedule</h4>
                  <div class="form-row row-even">
                    <div class="field field-tight">
                      <label class="lbl" for="sdur<?= $tid ?>">Working days</label>
                      <input class="f-sm" type="text" id="sdur<?= $tid ?>" name="duration_days" inputmode="numeric"
                             data-avg-days
                             value="<?= (int) $t['duration_days'] > 0 ? (int) $t['duration_days'] : '' ?>">
                    </div>
                    <div class="field field-tight">
                      <label class="lbl" for="stot<?= $tid ?>">Total work (hours)</label>
                      <input class="f-sm" type="text" id="stot<?= $tid ?>" name="total_hours" inputmode="decimal"
                             data-exclusive-with="sper<?= $tid ?>" data-avg-total
                             value="<?= e($svcTotalH) ?>">
                    </div>
                    <div class="field field-tight">
                      <label class="lbl" for="savg<?= $tid ?>">Average per day</label>
                      <input class="f-sm f-derived" type="text" id="savg<?= $tid ?>" tabindex="-1" readonly
                             data-avg-out placeholder="&mdash;">
                    </div>
                  </div>

                  <?php 

 ?>
                  <div class="row">
                    <label class="lbl" for="sper<?= $tid ?>">Optional: time per day (minutes)</label>
                    <input class="f-sm" type="text" id="sper<?= $tid ?>" name="per_day_minutes" inputmode="numeric"
                           data-exclusive-with="stot<?= $tid ?>"
                           value="<?= (int) ($t['per_day_minutes'] ?? 0) > 0 ? (int) $t['per_day_minutes'] : '' ?>">
                    <div class="hint">Enter total work time <b>or</b> a time
                    per day, never both.</div>
                  </div>
                </div>

              </section>

            </div><?php  ?>

            <div class="form-col">

              <section class="form-step" data-when="svcedit-<?= $tid ?>" data-when-value="daily"
                       data-when-effect="dim">
                <h3><span class="step-n">3</span> Additional time</h3>
                <?php 











 ?>
                <div class="form-row row-even">
                  <div class="field field-tight">
                    <label class="lbl" for="ss<?= $tid ?>">Setup time (minutes)</label>
                    <input class="f-full" type="text" id="ss<?= $tid ?>" name="setup_minutes" inputmode="numeric"
                           value="<?= (int) $t['setup_minutes'] > 0 ? (int) $t['setup_minutes'] : '' ?>">
                    <div class="hint">Extra time for the 1st working day.</div>
                  </div>
                  <div class="field field-tight">
                    <label class="lbl" for="sw<?= $tid ?>">Wrap-up time (minutes)</label>
                    <input class="f-full" type="text" id="sw<?= $tid ?>" name="completion_minutes" inputmode="numeric"
                           value="<?= (int) $t['completion_minutes'] > 0 ? (int) $t['completion_minutes'] : '' ?>">
                    <div class="hint">Added to the end of the project.</div>
                  </div>
                </div>
                <div class="row">
                  <label class="lbl">When the wrap-up happens</label>
                  <?php 



 ?>
                  <div class="caps-pick caps-2 caps-abreast">
                    <label class="caps-row">
                      <input type="radio" name="completion_mode" value="last_day"
                             <?= (string) $t['completion_mode'] !== 'own_day' ? 'checked' : '' ?>>
                      <span><b>Add to the last day</b>
                        <span class="muted small d-block">Rolled into the last day.</span></span>
                    </label>
                    <label class="caps-row">
                      <input type="radio" name="completion_mode" value="own_day"
                             <?= (string) $t['completion_mode'] === 'own_day' ? 'checked' : '' ?>>
                      <span><b>Give it its own day</b>
                        <span class="muted small d-block">Adds one day to the run.</span></span>
                    </label>
                  </div>
                </div>
              </section>

              <section class="form-step">
                <h3><span class="step-n">4</span> Pricing</h3>
                <div class="row">
                  <label class="lbl" for="sp<?= $tid ?>">Standard price</label>
                  <input class="f-sm" type="text" id="sp<?= $tid ?>" name="amount" inputmode="decimal"
                         value="<?= (int) $t['amount_cents'] > 0 ? e(number_format((int) $t['amount_cents'] / 100, 2, '.', '')) : '' ?>">
                  <div class="hint">Set default price. <b>NOTE:</b> Customizable when setting up jobs.</div>
                </div>
                <?php $pn = cm_tax_price_note(); ?>
                <?php if ($pn !== ''): ?><p class="hint"><b><?= e($pn) ?></b></p><?php endif; ?>
                <?php if (cm_tax_charges()): ?>
                  <label class="tick">
                    <input type="checkbox" name="taxable" value="1"
                           <?= (int) ($t['taxable'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span><?= e(cm_tax_settings()['name']) ?> applies to this service</span>
                  </label>
                <?php endif; ?>
              </section>

              <?php 






















 ?>
              <?php 








 ?>
              <section class="form-step">
                <h3><span class="step-n">5</span> Snippets
                  <span class="pill pill-soon">coming soon</span></h3>
                <div class="row">
                  <label class="lbl" for="snipcat<?= $tid ?>">Snippet library</label>
                  <select id="snipcat<?= $tid ?>" name="snippet_category" class="f-full" disabled>
                    <option value="all" selected>All snippets</option>
                  </select>
                  <div class="hint">Custom snippet categories coming soon.</div>
                </div>
              </section>

            </div><?php  ?>
          </div>

          <?php 





 ?>
          <div class="modal-foot">
            <button class="btn" type="submit">Save</button>
            <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
          </div>
        </form>
      </div>
    </dialog>

    <?php  ?>
    <?php $tid = (int) $t['id']; ?>
    <dialog class="modal modal-wider" id="dlg-svco-<?= $tid ?>"<?= (string) ($_GET['open'] ?? '') === 'svco-' . $tid ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= e((string) $t['name']) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <?php 




















 ?>
        <p class="modal-sub">This creates a custom form. To ask a client to
        fill it in, you make a private link only they can open, and send it to
        them however you like. It can also go out on its own as part of
        onboarding — whether and when that happens is set under <b>Bookings</b>.</p>

        <?php 









 ?>
        <?php $obFields = cm_onboard_service_fields($tid); ?>
        <?php if ($obFields): ?>
          <h3 class="work-h">The form, in order</h3>
          <?= cm_onboard_builder_html($obFields, 'svc' . $tid, "this service's form") ?>
        <?php endif; ?>
        <div class="actions mb-16">
          <button class="btn btn-sm" type="button" data-modal-open="dlg-addblk-<?= $tid ?>">+ Add a question or a message</button>
        </div>

        <form method="post" id="addqs<?= $tid ?>">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="add_fields">
          <input type="hidden" name="template_id" value="<?= $tid ?>">
          <h3 class="work-h">Or add several at once</h3>
          <?php 









 ?>
          <p class="muted small">Below are some suggested things to ask —
          nothing is added until you type it. You can change any row from a
          question to a message, a heading, or another kind of answer.</p>
          <div class="qgrid" id="qgrid<?= $tid ?>">
            <div class="qgrid-head"><span>The question</span><span>Kind of answer</span><span>Needed</span></div>
            <?php $sugg = cm_onboard_field_suggestions(); ?>
            <?php for ($r = 0; $r < 10; $r++): ?>
              <div class="qgrid-row" data-task-row>
                <input type="text" name="q_label[<?= $r ?>]" id="ql<?= $tid ?>-r<?= $r ?>"
                       class="f-full" maxlength="190" aria-label="Question <?= $r + 1 ?>"
                       placeholder="<?= e($sugg[$r] ?? '') ?>">
                <select name="q_type[<?= $r ?>]" id="qt<?= $tid ?>-r<?= $r ?>"
                        aria-label="Kind of answer, question <?= $r + 1 ?>">
                  <?php foreach (cm_onboard_types() as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= $k === 'textarea' ? 'selected' : '' ?>><?= e($lab) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php 


 ?>
                <input type="checkbox" name="q_required[<?= $r ?>]" value="1"
                       aria-label="Needed, question <?= $r + 1 ?>">
              </div>
            <?php endfor; ?>
          </div>
          <div class="actions mt-8">
            <button class="btn btn-quiet btn-sm" type="button"
                    data-task-add="qgrid<?= $tid ?>">+ Add another row</button>
            <span class="muted small ml-8">Empty lines are ignored.</span>
          </div>
        </form>

        <?= cm_onboard_add_block_window_html('dlg-addblk-' . $tid, ['template' => $tid]) ?>
        <?= cm_onboard_field_windows_html($obFields, 'svc' . $tid) ?>

        <?php 
 ?>
        <div class="modal-foot">
          <button class="btn" type="submit" form="addqs<?= $tid ?>">Save the questions</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
          <span class="spacer"></span>
          <span class="muted small">When they are asked is under <b>Bookings</b>.</span>
        </div>
      </div>
    </dialog>

    <?php  ?>
    <dialog class="modal" id="dlg-svcm-<?= (int) $t['id'] ?>"<?= (string) ($_GET['open'] ?? '') === 'svcm-' . (int) $t['id'] ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2>Move <?= e((string) $t['name']) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <?php 


 ?>
        <p class="modal-sub">Changes where it sits on this specific page.</p>

        <h3 class="work-h">Move it up or down</h3>
        <form method="post" class="mb-16">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="move_service">
          <input type="hidden" name="template_id" value="<?= (int) $t['id'] ?>">
          <div class="snip-add">
            <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="up">&uarr; Move up</button>
            <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="down">&darr; Move down</button>
          </div>
          <p class="hint">The window stays open, so you can press it again.</p>
        </form>

        <h3 class="work-h">Put it in another group</h3>
        <form method="post" class="mb-16">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="move_service">
          <input type="hidden" name="template_id" value="<?= (int) $t['id'] ?>">
          <div class="snip-add">
            <select name="category_id" class="f-lg" aria-label="Category">
              <option value=""<?= $t['category_id'] === null ? ' selected' : '' ?>>Everything else</option>
              <?php foreach (cm_service_category_map() as $cid => $cname): ?>
                <option value="<?= (int) $cid ?>"<?= (int) ($t['category_id'] ?? 0) === (int) $cid ? ' selected' : '' ?>><?= e($cname) ?></option>
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

    <dialog class="modal modal-wider" id="dlg-svcb-<?= (int) $t['id'] ?>">
      <div class="modal-box">
        <div class="modal-head">
          <h2><?= e((string) $t['name']) ?></h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub"><b>Bookings</b> &mdash; how this service is offered and booked.</p>
        <?php 



 ?>
        <?php $tid = (int) $t['id']; ?>
        <?php  ?>
        <h3 class="work-h">On your client portal</h3>
        <p class="muted small mb-6">What a client sees &mdash; only if you use the
        client portal.</p>

        <?php 













 ?>
        <?php if ((int) $t["published"] === 1 && cm_public_enabled()): ?>
          <?php 




 ?>
          <?php if (empty($copyJs)): $copyJs = true; ?>
            <?= cm_credential_tools_script_once() ?>
          <?php endif; ?>
          <?php $direct = cm_url('portal/service.php?id=' . $tid); ?>
          <div class="row">
            <label class="lbl" for="du<?= $tid ?>">Direct link &mdash; paste this into your own website</label>
            <div class="lbl-row">
              <input type="text" id="du<?= $tid ?>" class="mono f-full small" readonly
                     value="<?= e($direct) ?>">
              <button class="btn btn-sm btn-quiet" type="button" data-copy-for="du<?= $tid ?>">Copy</button>
            </div>
            <div class="hint">Goes straight to this one service, and keeps
            working whatever your portal does with visitors who arrive
            without a link (Settings &rarr; Your client portal).</div>
          </div>
        <?php endif; ?>

        <?php  ?>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="save_bookings">
          <input type="hidden" name="template_id" value="<?= $tid ?>">

          <?php 























 ?>
          <h3 class="work-h">How can clients get this service?</h3>
          <div class="caps-pick caps-2 mb-12">
            <label class="caps-row">
              <input type="radio" name="published" value="1" <?= (int) $t['published'] === 1 ? 'checked' : '' ?>>
              <span><b>They can request it</b>
                <span class="muted small d-block">It appears on your portal. A
                request arrives in Bookings and you decide whether to take
                it.</span></span>
            </label>
            <label class="caps-row">
              <input type="radio" name="published" value="0" <?= (int) $t['published'] === 1 ? '' : 'checked' ?>>
              <span><b>Not offered online</b>
                <span class="muted small d-block">Nobody can ask for it. You
                add jobs from this service yourself.</span></span>
            </label>
            <?php 









 ?>
            <label class="caps-row is-disabled">
              <input type="radio" disabled>
              <span><b>Book it outright</b> <span class="pill pill-soon">coming soon</span>
                <span class="muted small d-block">Client picks a time and it is
                booked, no review.</span></span>
            </label>
          </div>

          <?php 
















 ?>
          <?php if (!cm_public_enabled()): ?>
            <p class="hint mb-12">Your client portal is switched off, so
            nothing here is visible to a client yet and the preview would not
            load. Turn it on in
            <a href="<?= e(cm_url('admin/settings.php')) ?>">Settings</a>.</p>
          <?php elseif ((int) $t['published'] === 1): ?>
            <p class="hint mb-12">
              <a href="<?= e(cm_url('portal/service.php?id=' . $tid)) ?>" target="_blank" rel="noopener">
                Preview the client view &rarr;</a>
              &mdash; exactly what somebody sees when they follow the link below.
            </p>
          <?php endif; ?>
          <label class="tick mb-8">
            <input type="checkbox" name="featured" value="1" <?= (int) ($t['featured'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Feature it &mdash; a card at the top rather than a row underneath</span>
          </label>
          <div class="row">
            <label class="lbl" for="ps<?= $tid ?>">Order</label>
            <input type="text" id="ps<?= $tid ?>" name="public_sort" class="f-xs" inputmode="numeric"
                   value="<?= (int) ($t['public_sort'] ?? 100) ?>">
            <?php  ?>
            <div class="hint">Choose the listing priority on the client portal.
            A &ldquo;1&rdquo; is listed above a service set to &ldquo;2&rdquo;.
            100 is the default.</div>
          </div>

        <?php  ?>
        <?php if (cm_tax_charges()): ?>
          <h3 class="work-h"><?= e(cm_tax_settings()['name']) ?></h3>
          <?php  ?>
          <input type="hidden" name="tax_shown" value="1">
          <label class="tick mb-8">
              <input type="checkbox" name="taxable" value="1" <?= (int) $t['taxable'] === 1 ? 'checked' : '' ?>>
              <span>This service carries <?= e(cm_tax_settings()['name']) ?></span>
          </label>
        <?php endif; ?>

        <?php 

 ?>
        <?php  ?>
        <?php $onbReady = cm_onboard_feature_ready(); ?>
        <h3 class="work-h">Onboarding<?php if (!$onbReady): ?>
          <span class="pill pill-soon">coming soon</span><?php endif; ?></h3>
        <?php if ($onbReady): ?><input type="hidden" name="onboard_shown" value="1"><?php endif; ?>
          <div class="caps-pick caps-2">
            <?php 





 ?>
            <?php $onbNow = cm_onboard_when_effective((int) $t['id']); ?>
            <?php foreach (cm_onboard_when_options() as $k => $c): ?>
              <label class="caps-row<?= $onbReady ? '' : ' is-off' ?>">
                <input type="radio" name="onboard_when" value="<?= e($k) ?>"
                       <?= $onbNow === $k ? 'checked' : '' ?>
                       <?= $onbReady ? '' : 'disabled' ?>>
                <span><b><?= e($c[0]) ?></b>
                  <span class="muted small d-block"><?= e($c[1]) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="muted small mt-8"><?php if (!$onbReady): ?>Asking automatically is
            not switched on yet, so no service asks. You can still make a form by
            hand and send it — <?php else: ?>The questions themselves live on
            <?php endif; ?><a href="<?= e(cm_url('admin/onboarding.php')) ?>">Onboarding</a>.</p>


          <?php 

 ?>
          <div class="modal-foot">
            <button class="btn" type="submit">Save</button>
            <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
          </div>
        </form>
      </div>
    </dialog>
  <?php endforeach; ?>

  <?php 














 ?>
  <?php foreach ($templates as $t): $tid = (int) $t['id']; ?>
    <?php 













 ?>

    <?php 





 ?>
    <form id="delsvc<?= $tid ?>" method="post"
          data-confirm="Delete &quot;<?= e((string) $t['name']) ?>&quot;? Jobs already stamped from it are untouched, but you cannot use it again.">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="delete_template">
      <input type="hidden" name="template_id" value="<?= $tid ?>">
    </form>
  <?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'snippets'): ?>
<div class="card" id="quick-lines">
  <div class="card-head">
    <h2>Snippets</h2>
    <span class="spacer"></span>
    <?php 


 ?>
    <span class="muted small">These allow you to 1 tap a message when working
    on jobs. Helps avoid repetitive typing.</span>
  </div>

  <?php $live = array_values(array_filter($snippets, static fn($x) => (int) $x['archived'] === 0)); ?>
  <?php $put = array_values(array_filter($snippets, static fn($x) => (int) $x['archived'] === 1)); ?>

  <?php 






















 ?>

  <div class="actions mb-12">
    <button class="btn" type="button" data-modal-open="dlg-snip-new">+ New snippet</button>
  </div>

  <?php if (!$live && !$put): ?>
    <p class="muted small">None yet. Add the sentence you type most often —
    &ldquo;Updated the Facebook page&rdquo; — and it will be one tap on every job.</p>
  <?php else: ?>
    <ul class="svc-list">
      <?php foreach ($live as $sn): ?>
        <li class="svc-row">
          <?php 

 ?>
          <button class="svc-grip" type="button" data-modal-open="dlg-snipm-<?= (int) $sn['id'] ?>"
                  aria-label="Move this line" title="Move this line">
            <span></span><span></span><span></span>
          </button>
          <div class="svc-main">
            <button class="svc-name" type="button" data-modal-open="dlg-snip-<?= (int) $sn['id'] ?>"><?=
              e((string) $sn['body']) ?></button>
          </div>
          <button class="btn btn-sm btn-quiet" type="button"
                  data-modal-open="dlg-snip-<?= (int) $sn['id'] ?>">Edit</button>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($put): ?>
      <details class="mt-12">
        <summary><b>Put away</b> <span class="muted small">(<?= count($put) ?>)</span></summary>
        <?php 


 ?>
        <p class="hint">Out of the picker. Work already written keeps the words
        exactly as they were saved.</p>
        <ul class="svc-list">
          <?php foreach ($put as $sn): ?>
            <li class="svc-row">
              <div class="svc-main">
                <span class="muted"><?= e((string) $sn['body']) ?></span>
              </div>
              <form method="post" class="inline">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="action" value="archive_snippet">
                <input type="hidden" name="snippet_id" value="<?= (int) $sn['id'] ?>">
                <input type="hidden" name="restore" value="1">
                <button class="btn btn-sm btn-quiet" type="submit">Put back</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  <?php endif; ?>

  <?php 



 ?>

  <?php 





 ?>
  <dialog class="modal" id="dlg-snip-new">
    <div class="modal-box">
      <div class="modal-head">
        <h2>New snippet</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">The sentence you type most &mdash; &ldquo;Updated the
      Facebook page&rdquo;. Written once, then one tap on every job.</p>
      <form method="post">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="add_snippet">
        <div class="row">
          <label class="lbl" for="snippet_body">The line</label>
          <input type="text" id="snippet_body" name="snippet_body" class="f-full"
                 maxlength="400" placeholder="Updated the Facebook page" required>
        </div>
        <div class="modal-foot">
          <button class="btn" type="submit">Add it</button>
          <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
        </div>
      </form>
    </div>
  </dialog>

  <?php foreach ($live as $sn): ?>
    <?php $sid = (int) $sn['id']; ?>
    <dialog class="modal" id="dlg-snip-<?= $sid ?>"<?= (string) ($_GET['open'] ?? '') === 'snip-' . $sid ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2>Edit line</h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">Rewording it changes what the picker offers from
        now on. Work already written keeps the words it was saved with.</p>
        <form method="post" class="mb-16">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="edit_snippet">
          <input type="hidden" name="snippet_id" value="<?= $sid ?>">
          <div class="row">
            <label class="lbl" for="sb<?= $sid ?>">The line</label>
            <input type="text" id="sb<?= $sid ?>" name="snippet_body" class="f-full"
                   maxlength="400" value="<?= e((string) $sn['body']) ?>" required>
          </div>
          <div class="actions">
            <button class="btn" type="submit">Save</button>
          </div>
        </form>

        <h3 class="work-h">Stop offering it</h3>
        <p class="muted small mb-6">It leaves the picker and keeps everything it
        was used on. You can put it back at any time.</p>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="archive_snippet">
          <input type="hidden" name="snippet_id" value="<?= $sid ?>">
          <button class="btn btn-sm btn-quiet" type="submit">Put away</button>
        </form>

        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </dialog>

    <dialog class="modal" id="dlg-snipm-<?= $sid ?>"<?= (string) ($_GET['open'] ?? '') === 'snipm-' . $sid ? ' data-modal-autoopen' : '' ?>>
      <div class="modal-box">
        <div class="modal-head">
          <h2>Move this line</h2>
          <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
        </div>
        <p class="modal-sub">Where it sits in the picker. The wording does not
        change, and nothing already written is touched.</p>
        <form method="post">
          <?= cm_csrf_field() ?>
          <input type="hidden" name="action" value="move_snippet">
          <input type="hidden" name="snippet_id" value="<?= $sid ?>">
          <div class="snip-add">
            <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="up">&uarr; Move up</button>
            <button class="btn btn-sm btn-quiet" type="submit" name="dir" value="down">&darr; Move down</button>
          </div>
          <p class="hint">The window stays open, so you can press it again.</p>
        </form>

        <div class="modal-foot">
          <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
        </div>
      </div>
    </dialog>
  <?php endforeach; ?>
  <?php 




 ?>
  <?php 

 ?>
  <p class="hint"><span class="pill pill-soon">coming soon</span>
  <b>Snippet categories</b> &mdash; so you can make a bunch of specific
  snippets for specific services. Guys, if you love the product I can add
  this feature in.</p>
</div>
<?php endif; ?>

<?php if ($tab === 'finder'): ?>
<?php 






 ?>
<?php
$fqs = cm_finder_questions();
$fPublished = array_values(array_filter($templates, fn($t) => (int) ($t['published'] ?? 0) === 1));
?>
<div class="card">
  <h2><?= e(cm_finder_heading()) ?></h2>
  <p class="hint"><?= e(cm_finder_intro()) ?>
    The wording of those two lines is on <a href="<?= e(cm_url('admin/settings.php#public')) ?>">Settings</a>.</p>

  <?php if (!$fPublished): ?>
    <p class="hint"><span class="pill">nothing to point at</span>
      Publish a service first &mdash; a question whose answers lead nowhere is worse
      than no question.</p>
  <?php endif; ?>

  <p class="hint">
    <?php if (cm_finder_ready()): ?>
      <span class="pill pill-on">live</span> Your portal offers this to anyone who is
      not sure. <a href="<?= e(cm_url('portal/find.php')) ?>">See what they see</a>.
    <?php else: ?>
      <span class="pill">not shown yet</span> It appears on your portal as soon as one
      answer below points at a published service. There is no switch to forget.
    <?php endif; ?>
  </p>

  <?php foreach ($fqs as $q): ?>
    <div class="row-line">
      <form method="post" action="<?= e(cm_url('admin/services.php?tab=finder')) ?>" class="stack">
        <?= cm_csrf_field() ?>
        <input type="hidden" name="action" value="finder_q_save">
        <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
        <label class="lbl">Question</label>
        <input type="text" name="prompt" class="f-full" maxlength="200"
               value="<?= e((string) $q['prompt']) ?>" required>
        <label class="lbl">Note underneath <span class="muted">(optional)</span></label>
        <input type="text" name="hint" class="f-full" maxlength="255"
               value="<?= e((string) $q['hint']) ?>">
        <button class="btn btn-sm" type="submit">Save</button>
        <button class="btn btn-sm btn-quiet" type="submit" name="action" value="finder_q_delete"
                onclick="return confirm('Delete this question and its answers?')">Delete</button>
      </form>

      <table class="tbl">
        <tr><th>Answer</th><th>Suggests</th><th></th></tr>
        <?php foreach ($q['options'] as $o): ?>
        <tr>
          <td colspan="3">
            <form method="post" action="<?= e(cm_url('admin/services.php?tab=finder')) ?>" class="row">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="finder_o_save">
              <input type="hidden" name="oid" value="<?= (int) $o['id'] ?>">
              <input type="text" name="label" maxlength="200" required
                     value="<?= e((string) $o['label']) ?>">
              <select name="templates[]" multiple size="4">
                <?php foreach ($fPublished as $t): ?>
                  <option value="<?= (int) $t['id'] ?>"<?=
                    in_array((int) $t['id'], $o['templates'], true) ? ' selected' : '' ?>><?=
                    e((string) $t['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm" type="submit">Save</button>
              <button class="btn btn-sm btn-quiet" type="submit" name="action" value="finder_o_delete">&times;</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="3">
          <form method="post" action="<?= e(cm_url('admin/services.php?tab=finder')) ?>" class="row">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="finder_o_add">
            <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
            <input type="text" name="label" maxlength="200" placeholder="Another answer" required>
            <button class="btn btn-sm btn-quiet" type="submit">+ Add answer</button>
          </form>
        </td></tr>
      </table>
      <p class="hint">Hold Ctrl (or Cmd) to point one answer at more than one service.
        Nothing is ruled out by an answer &mdash; the service pointed at most often wins.</p>
    </div>
  <?php endforeach; ?>

  <form method="post" action="<?= e(cm_url('admin/services.php?tab=finder')) ?>" class="row">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="finder_q_add">
    <input type="text" name="prompt" maxlength="200" required
           placeholder="e.g. When was it last tuned?">
    <button class="btn" type="submit">+ Add question</button>
  </form>
  <p class="hint">Three is usually plenty. Ask what the customer knows &mdash; when it was
    last done, what they have noticed, what they are hoping for &mdash; never what a
    technician would know.</p>
</div>
<?php endif; ?>

<?php cm_foot(); ?>
