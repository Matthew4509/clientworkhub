<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













































declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/assets.php';
require_once CM_LIB . '/snippets.php';
require_once CM_LIB . '/queueactions.php';

$admin = cm_require_admin();

$occ = cm_occurrence((int) ($_GET['id'] ?? 0));
if (!$occ) {
    cm_flash('There is no task with that number — it may have been removed.', 'warn');
    cm_redirect('admin/workflow.php');
}
$id  = (int) $occ['id'];
$job = cm_job((int) $occ['job_id']);
if (!$job) {
    cm_flash('That task belongs to a job that no longer exists.', 'warn');
    cm_redirect('admin/workflow.php');
}
$client = cm_client((int) $job['client_id']);



cm_queue_handle_post($admin, 'admin/task.php?id=' . $id);






cm_job_assets_handle_post($admin, $job, $client, 'admin/task.php?id=' . $id);


$assetErrors = cm_job_asset_add_new_handle_post($admin, $job, $client, 'admin/task.php?id=' . $id, 'fa_');


$occ = cm_occurrence($id) ?? $occ;

$running   = cm_timer_running();
$isRunning = $running !== null && (int) $running['id'] === $id;
$elapsed   = cm_time_totals('occurrence', $id);
$live      = $isRunning ? cm_timer_elapsed_minutes($running) : 0;

$clientId   = (int) $client['id'];
$clientName = cm_client_display_name($client);
$panel      = cm_core_details_for_job_panel($clientId);



























if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (string) ($_POST['action'] ?? '') === 'remember_client') {
    cm_require_csrf();
    $what = trim((string) ($_POST['remember'] ?? ''));

    if ($what === '') {
        cm_flash('Nothing typed, so nothing was added.', 'warn');
        cm_redirect('admin/task.php?id=' . $id);
    }

    if ((string) $_POST['action'] === 'remember_client') {











        $now  = (string) (cm_core_details($clientId)['anything_else'] ?? '');
        $next = $now === '' ? $what : rtrim($now) . "\n" . $what;
        cm_core_detail_set($clientId, 'anything_else', $next);
        cm_client_touch($clientId);
        cm_audit('client.core_detail', 'client', $clientId,
                 'a line added to "Anything else to keep in mind" from a task by ' . (string) $admin['email']
                 . ' — ' . mb_substr($what, 0, 80));
        cm_flash('Noted against ' . $clientName . '. It rides beside every job you ever do for them.');
    }
    cm_redirect('admin/task.php?id=' . $id);
}


$picked = cm_job_asset_ids((int) $job['id']);




$allAssets = cm_client_assets($clientId);
$assets = array_values(array_filter($allAssets,
    static fn ($a) => in_array((int) $a['id'], $picked, true)));

$snippets = [];
try { $snippets = cm_snippets_for_job((int) $job['id']); } catch (Throwable $ex) {}





$recent = [];
try {
    $recent = cm_all(
        "SELECT o.scheduled_date, o.completed_at, o.note, o.internal_note,
                o.content_url, o.minutes_spent,
                " . cm_occurrence_name_sql('a', 'j') . " AS action_name
           FROM action_occurrence o
           LEFT JOIN job_action a ON a.id = o.action_id
           JOIN job j ON j.id = o.job_id
          WHERE o.job_id = ? AND o.status = 'completed' AND o.id <> ?
          ORDER BY COALESCE(o.completed_at, o.scheduled_date) DESC
          LIMIT 8", [(int) $job['id'], $id]);
} catch (Throwable $ex) {}



$days = [];
try { $days = cm_job_occurrences((int) $job['id']); } catch (Throwable $ex) {}
$taskSequence = $days;
usort($taskSequence, static function (array $a, array $b): int {
    $dateOrder = strcmp((string) ($a['scheduled_date'] ?? ''), (string) ($b['scheduled_date'] ?? ''));
    return $dateOrder !== 0 ? $dateOrder : ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
});
$taskTotal = count($taskSequence);
$taskNumber = 1;
foreach ($taskSequence as $i => $day) {
    if ((int) ($day['id'] ?? 0) === $id) { $taskNumber = $i + 1; break; }
}
$taskTotal = max(1, $taskTotal);
$tasksRemaining = count(array_filter($days, static fn(array $day): bool => cm_occurrence_is_open($day)));
$taskPosition = 'Task ' . $taskNumber . ' of ' . $taskTotal;



$taskName = trim((string) ($occ['action_name'] ?? ''));
if ($taskName === '') $taskName = (string) $job['name'];












$taskCrumb = $taskName;
if (strcasecmp($taskName, (string) $job['name']) === 0) {
    $whenTs    = strtotime((string) ($occ['scheduled_date'] ?? ''));
    $taskCrumb = $whenTs ? date('D j M', $whenTs) : $taskPosition;
}
$statuses = cm_job_statuses();
$open     = cm_occurrence_is_open($occ);




$settled  = !$open;
$isDone   = (string) $occ['status'] === 'completed';
$occStatuses = cm_occurrence_statuses();




$actionNote = '';
try {
    $actionNote = trim((string) (cm_val('SELECT notes FROM job_action WHERE id = ?',
                                        [(int) $occ['action_id']]) ?? ''));
} catch (Throwable $ex) {}
$tags = array_values(array_filter(array_map('trim',
    explode(',', (string) ($client['tags'] ?? '')))));

cm_head($taskName . ' - ' . $job['name'], '', true);
cm_render_flashes();
cm_admin_nav('workflow');
?>
<?php 


 ?>
<script defer src="<?= e(cm_url('js/snippets.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>

<?php 

























 ?>
<?php 





 ?>
<?= cm_crumbs_html([
      ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
      ['label' => cm_client_display_name($client),
       'href'  => cm_url('admin/client.php?id=' . $clientId)],
      ['label' => 'All Jobs',
       'href'  => cm_url('admin/client.php?id=' . $clientId . '&tab=jobs')],
      ['label' => (string) $job['name'], 'href' => cm_url('admin/job.php?id=' . (int) $job['id'])],
      ['label' => $taskCrumb],
    ]) ?>

<?php 






 ?>
<div class="page-hero<?= $isDone ? ' is-done' : '' ?>">
<div class="card-head align-baseline">
  <div class="task-title-stack">
    <?php 


 ?>
    <span class="eyebrow"><?= e($taskPosition) ?></span>
    <h1 class="mb-0"><?= e($taskName) ?></h1>
    <p class="task-context"><a href="<?= e(cm_url('admin/job.php?id=' . (int) $job['id'])) ?>"><?= e((string) $job['name']) ?></a> &middot; <a href="<?= e(cm_url('admin/client.php?id=' . $clientId)) ?>"><?= e(cm_client_display_name($client)) ?></a></p>
  </div>
  <?php if ($isRunning): ?>
    <span class="pill pill-live"><?= e(cm_minutes_label($live)) ?> so far</span>
  <?php elseif ($elapsed['total'] > 0): ?>
    <span class="pill pill-approved"><?= e(cm_minutes_label($elapsed['total'])) ?> tracked</span>
  <?php endif; ?>
  <?php $st = $statuses[(string) $job['status']] ?? null; ?>
  <?php 



 ?>
  <?php if ((string) $occ['status'] !== 'scheduled'):
          $ost = $occStatuses[(string) $occ['status']] ?? null; ?>
    <span class="pill <?= e($ost['pill'] ?? 'pill-pending') ?>"><?=
      e($ost['label'] ?? (string) $occ['status']) ?></span>
  <?php endif; ?>
  <span class="spacer"></span>
  <?php if ($open): ?>
    <form method="post" class="inline">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="occ_id" value="<?= $id ?>">
      <?php if ($isRunning): ?>
        <button class="btn btn-quiet" name="action" value="occ_pause" type="submit">Pause</button>
      <?php else: ?>
        <button class="btn" name="action" value="occ_start" type="submit">Start work</button>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>
<?php 









 ?>
<p class="sub">
  <?php 


 ?>
  <?php 




 ?>
  <?php if ($isDone && $occ['completed_at']): ?>
    <b>completed <?= e(date('D j M Y, g:ia', strtotime((string) $occ['completed_at']))) ?></b>
  <?php elseif ($settled): ?>
    <?= e($occStatuses[(string) $occ['status']]['label'] ?? '') ?>,
    was due <?= e(date('D j M Y', strtotime((string) $occ['scheduled_date']))) ?>
  <?php else: ?>
    <b>Due <?= e(date('D j M Y', strtotime((string) $occ['scheduled_date']))) ?></b>
    &middot; <?= $tasksRemaining ?> task<?= $tasksRemaining === 1 ? '' : 's' ?> remaining
  <?php endif; ?>
  <?php if ($isRunning): ?>
    &middot; started <?= e(date('g:ia', strtotime((string) $running['started_at']))) ?>
  <?php endif; ?>
</p>
</div><?php  ?>

<div class="day-grid">
<div>

  <?php 








 ?>
  <div class="card">
    <div class="card-head">
      <h2>Job description</h2>
      <?php 




 ?>
    </div>
    <?php if (trim((string) ($job['description'] ?? '')) !== ''): ?>
      <p class="small prewrap m-0"><?= e((string) $job['description']) ?></p>
    <?php else: ?>
      <p class="muted small m-0">Nothing here yet &mdash; the job description
      is written on the job page and reads on every task of this job.</p>
    <?php endif; ?>
  </div>

  <?php 












 ?>
  <div class="card" data-cred-endpoint="<?= e(cm_url('admin/credential.php')) ?>"
       data-cred-csrf="<?= e(cm_csrf_token()) ?>">
    <div class="card-head">
      <h2>Sign in and go</h2>
      <span class="spacer"></span>
      <?php 




 ?>
      <?php if ($allAssets): ?>
        <button class="btn btn-sm" type="button" data-modal-open="dlg-job-assets">Add or remove properties</button>
      <?php endif; ?>
    </div>

    <?php if (!$assets): ?>
      <p class="muted small">No properties are ticked on this job, so there is
      nothing to sign in to.
      <?php if ($allAssets): ?>
        <button class="btn btn-sm mt-6" type="button" data-modal-open="dlg-job-assets">Tick the ones this job touches</button>
      <?php else: ?>
        <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '#properties')) ?>">Add one on the client</a>
        and it can be ticked here.
      <?php endif; ?></p>
    <?php else: ?>
      <?php foreach ($assets as $a): ?>
        <?= cm_asset_signin_html($a) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php 









 ?>
  <div class="card">
    <div class="card-head">
      <h2>What was done</h2>
      <span class="spacer"></span>
      <?php if ($elapsed['total'] > 0): ?>
        <span class="muted small"><?= e(cm_minutes_label($elapsed['total'])) ?> tracked so far</span>
      <?php endif; ?>
    </div>

    <?php 











 ?>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="occ_id" value="<?= $id ?>">

      <div class="row">
        <div class="lbl-row">
          <label class="lbl m-0" for="occ_note">The client's record
            <span class="pill pill-live va-baseline">they can see this</span></label>
          <span class="spacer"></span>
          <?php 
 ?>
          <?php if ($snippets && !$settled): ?>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-snips-note">Add a snippet</button>
          <?php endif; ?>
        </div>
        <textarea id="occ_note" name="occ_note" rows="7"
                  class="task-write<?= $settled ? ' is-locked' : '' ?>"<?= $settled ? ' readonly' : '' ?>
                  placeholder="<?= $settled ? 'Nothing was written here.' : 'What you did, in the words you would use to the client.' ?>"><?= e((string) $occ['note']) ?></textarea>
        <div class="hint">This is what goes on their work summary. Write as
        much as the job deserves.</div>
      </div>

      <div class="row">
        <div class="lbl-row">
          <label class="lbl m-0" for="occ_internal">Your own note
            <span class="pill pill-spam va-baseline">never sent</span></label>
          <span class="spacer"></span>
          <?php if ($snippets && !$settled): ?>
            <button class="btn btn-sm btn-quiet" type="button"
                    data-modal-open="dlg-snips-internal">Add a snippet</button>
          <?php endif; ?>
        </div>
        <textarea id="occ_internal" name="occ_internal" rows="7"
                  class="task-write<?= $settled ? ' is-locked' : '' ?>"<?= $settled ? ' readonly' : '' ?>
                  placeholder="<?= $settled ? 'Nothing was written here.' : 'What you would not say to them. Where you got to, what to pick up next time, what went wrong.' ?>"><?= e((string) ($occ['internal_note'] ?? '')) ?></textarea>
        <div class="hint">Never appears on a summary, an invoice or an email
        &mdash; it is here so the next session starts where this one stopped.</div>
      </div>

      <div class="row">
        <label class="lbl" for="occ_url">Link to the work</label>
        <?php 

 ?>
        <input type="text" inputmode="url" id="occ_url" name="occ_url" autocomplete="off" spellcheck="false"
               class="f-full<?= $settled ? ' is-locked' : '' ?>"<?= $settled ? ' readonly' : '' ?>
               placeholder="<?= $settled ? '—' : 'example.com/the-page' ?>" value="<?= e((string) ($occ['content_url'] ?? '')) ?>">
        <div class="hint">The post, the article, the page — whatever you made.</div>
      </div>

      <?php if ($open): ?>
        <div class="actions">
          <button class="btn" name="action" value="occ_complete_next" type="submit">Complete &amp; start next &rarr;</button>
          <button class="btn btn-quiet" name="action" value="occ_complete" type="submit">Complete</button>
          <span class="spacer"></span>
          <button class="btn btn-quiet" name="action" value="occ_skip" type="submit"
                  formnovalidate>Skip it</button>
        </div>
      <?php else: ?>
        <p class="note <?= $isDone ? 'note-ok' : '' ?> m-0">
          <?php if ($isDone): ?>
            <b>TASK DONE</b><?= $occ['completed_at']
              ? ' &mdash; completed ' . e(date('D j M Y, g:ia', strtotime((string) $occ['completed_at'])))
              : '' ?>. The record above is locked; it is on the job&rsquo;s
            history and on the client&rsquo;s work summary.
          <?php else: ?>
            <b>This one is <?= e($occStatuses[(string) $occ['status']]['label'] ?? (string) $occ['status']) ?>.</b>
            The record above is locked and it is on the job&rsquo;s history.
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </form>
  </div>

</div>

<?php 


















 ?>
<?php 





 ?>
<?= cm_job_assets_dialog_html($allAssets, $picked,
                              cm_asset_add_form_html('fa_', 'add_new_asset', $assetErrors, true),
                              cm_asset_add_refused('fa_', $assetErrors)) ?>

<?php if ($snippets): ?>
  <dialog class="modal" id="dlg-snips-note">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Snippets &mdash; the client&rsquo;s record</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">One click adds a phrase to <b>The client&rsquo;s
      record</b>. Add as many as you like, then press Done &mdash; you can
      edit every word of it afterwards.</p>
      <?= cm_snippet_picker_html((int) $job['id'], 'occ_note') ?>
      <div class="modal-foot">
        <button class="btn" type="button" data-modal-close>Done</button>
        <span class="spacer"></span>
        <a class="small" href="<?= e(cm_url('admin/services.php?tab=snippets')) ?>">Edit the snippets</a>
      </div>
    </div>
  </dialog>

  <dialog class="modal" id="dlg-snips-internal">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Snippets &mdash; your own note</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">One click adds a phrase to <b>Your own note</b>,
      which is never sent anywhere. Press Done when you have finished.</p>
      <?= cm_snippet_picker_html((int) $job['id'], 'occ_internal') ?>
      <div class="modal-foot">
        <button class="btn" type="button" data-modal-close>Done</button>
        <span class="spacer"></span>
        <a class="small" href="<?= e(cm_url('admin/services.php?tab=snippets')) ?>">Edit the snippets</a>
      </div>
    </div>
  </dialog>
<?php endif; ?>

<?php 









 ?>
<dialog class="modal" id="dlg-remember-client">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Remember this about <?= e($clientName) ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <p class="modal-sub">A permanent rule for this client &mdash; how they
    like the work done. It goes on the end of <b>Anything else to keep in
    mind</b> on their Core Details and sits beside every job you ever do for
    them. Nothing already written there is changed.</p>
    <form method="post">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="remember_client">
      <div class="row">
        <label class="lbl" for="remember_c">What to remember</label>
        <textarea id="remember_c" name="remember" rows="3" class="f-full"
                  placeholder="Prefers 500 word articles, not 300."></textarea>
        <div class="hint">One rule per line. Edit or remove them any time on
          <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '&tab=core')) ?>">their Core Details</a>.</div>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Remember it</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
  </div>
</dialog>


<?php 





 ?>
<aside class="day-rail">

  <?php 

 ?>
  <div class="card">
    <div class="card-head">
      <h2>Who this is for</h2>
      <span class="spacer"></span>
      <?php 


 ?>
      <button class="btn btn-sm" type="button" data-modal-open="dlg-remember-client">Remember something</button>
      <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . $clientId)) ?>">Their file</a>
    </div>

    <p class="task-who">
      <b><?= e($clientName) ?></b>
      <?php if (trim((string) $client['company']) !== ''
                && trim((string) $client['company']) !== $clientName): ?>
        <span class="muted">&middot; <?= e((string) $client['company']) ?></span>
      <?php endif; ?>
    </p>

    <?php if ($tags || $actionNote !== ''): ?>
      <div class="task-keys">
        <?php foreach ($tags as $t): ?>
          <span class="pill pill-approved"><?= e($t) ?></span>
        <?php endforeach; ?>
        <?php if ($actionNote !== ''): ?>
          <p class="small prewrap mt-6 m-0"><b>This task:</b> <?= e($actionNote) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php 

 ?>
    <?php if (!$panel): ?>
      <?php 



 ?>
      <p class="muted small mt-8">No Core Details for this client yet —
      <a href="<?= e(cm_url('admin/client.php?id=' . $clientId . '&tab=core')) ?>">write some details
      about the company</a>, so you can easily see it when working on their
      projects.</p>
    <?php else: ?>
      <div class="task-brief mt-8">
        <?php foreach ($panel as $key => $fld): ?>
          <h4 class="work-f<?= str_contains($key, 'avoid') ? ' work-avoid' : '' ?>"><?= e($fld['label']) ?></h4>
          <p class="small prewrap mb-8"><?= e($fld['value']) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (trim((string) ($client['notes'] ?? '')) !== ''): ?>
      <h4 class="work-f">Notes on the client</h4>
      <p class="small prewrap m-0"><?= e((string) $client['notes']) ?></p>
    <?php endif; ?>
  </div>

  <?php 
 ?>
  <div class="card">
    <?php 



 ?>
    <h2 class="t-ui">See recent work notes</h2>
    <?php if (!$recent): ?>
      <p class="muted small">Nothing completed on this job yet.</p>
    <?php else: ?>
      <p class="muted small mb-8">The last <?= count($recent) ?> on this job,
      newest first. Read before you write.</p>
      <?php foreach ($recent as $r): ?>
        <details class="task-past">
          <summary>
            <span class="mono small"><?= e(date('j M', strtotime((string) $r['scheduled_date']))) ?></span>
            <span class="small"><?= e((string) $r['action_name']) ?></span>
          </summary>
          <div class="task-past-body">
            <?php if (trim((string) $r['note']) !== ''): ?>
              <p class="small prewrap mb-6"><?= e((string) $r['note']) ?></p>
            <?php endif; ?>
            <?php if (trim((string) ($r['internal_note'] ?? '')) !== ''): ?>
              <p class="small prewrap note-internal mb-6"><b>Yours:</b>
                <?= e((string) $r['internal_note']) ?></p>
            <?php endif; ?>
            <?php if (trim((string) ($r['content_url'] ?? '')) !== ''): ?>
              <p class="small m-0"><a href="<?= e(cm_safe_href((string) $r['content_url'])) ?>"
                 target="_blank" rel="noopener noreferrer">what was made</a></p>
            <?php endif; ?>
            <?php if (trim((string) $r['note']) === ''
                      && trim((string) ($r['internal_note'] ?? '')) === ''): ?>
              <p class="muted small m-0">Nothing was written down that day.</p>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <h2 class="t-ui">The schedule</h2>
      <span class="spacer"></span>
      <a class="small" href="<?= e(cm_url('admin/job.php?id=' . (int) $job['id'] . '#schedule')) ?>">all of it</a>
    </div>
    <?php 








 ?>
    <?php if (!$days): ?>
      <p class="muted small">No days generated for this job.</p>
    <?php else: ?>
      <?php 

 ?>
      <?php 






 ?>
      <?= cm_job_progress_html($job) ?>
      <ul class="task-days">
        <?php foreach ($days as $d): $isThis = (int) $d['id'] === $id; ?>
          <li class="task-day<?= $isThis ? ' is-now' : '' ?><?= (string) $d['status'] === 'completed' ? ' is-done' : '' ?>">
            <?php if ($isThis): ?>
              <span class="mono"><?= e(date('D j M', strtotime((string) $d['scheduled_date']))) ?></span>
              <span class="task-day-n"><?= e((string) $d['action_name']) ?></span>
            <?php else: ?>
              <a href="<?= e(cm_url('admin/task.php?id=' . (int) $d['id'])) ?>">
                <span class="mono"><?= e(date('D j M', strtotime((string) $d['scheduled_date']))) ?></span>
                <span class="task-day-n"><?= e((string) $d['action_name']) ?></span>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

</aside>
</div>
<?php cm_foot(); ?>
