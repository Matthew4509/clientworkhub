<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0











declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/smartreminders.php';
require_once CM_LIB . '/settings.php';

$admin = cm_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'smart_send') {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        if (!$ids) {
            cm_flash('Tick at least one reminder to send.', 'error');
            cm_redirect('admin/smart-reminders.php');
        }
        if (count($ids) > CM_SMART_BATCH) $ids = array_slice($ids, 0, CM_SMART_BATCH);
        $r = cm_smart_send($ids, (string) $admin['email']);
        if ($r['queued'] === 0) {
            cm_flash('Nothing was sent — those reminders are no longer open. Reload and look again.', 'warn');
        } else {
            $mailOn = (string) cm_setting('mail.enabled', '') === '1';
            $said = $r['queued'] . ' reminder' . ($r['queued'] === 1 ? '' : 's') . ' pulled forward. '
                  . $r['sent'] . ' message' . ($r['sent'] === 1 ? '' : 's') . ' went out in this pass'
                  . ($r['sent'] < $r['queued']
                     ? '; the rest are queued and go out within the hourly cap on your next dashboard visit.'
                     : '.');
            if (!$mailOn) {
                $said .= ' Email sending is OFF, so they were written to storage/mail/ — '
                       . 'turn it on under Settings → Email when you are ready.';
            }
            cm_flash($said, $mailOn ? 'ok' : 'warn');
        }
        cm_redirect('admin/smart-reminders.php');
    }

    if ($action === 'smart_push') {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $weeks = (int) ($_POST['weeks'] ?? 0);
        if (!$ids) {
            cm_flash('Tick at least one reminder to push back.', 'error');
            cm_redirect('admin/smart-reminders.php#push');
        }
        $moved = cm_smart_pushback($ids, $weeks, (string) $admin['email']);
        cm_flash($moved > 0
            ? $moved . ' reminder' . ($moved === 1 ? '' : 's') . ' pushed back ' . $weeks
              . ' weeks. The original dates are kept on the rows — when work dries up, the '
              . 'Balanced list lifts the most-delayed first.'
            : 'Nothing moved — pick 2, 4, 8 or 12 weeks, and note VIPs are never pushed while '
              . 'that option is on.', $moved > 0 ? 'ok' : 'warn');
        cm_redirect('admin/smart-reminders.php#push');
    }

    if ($action === 'smart_opts') {
        $cool = (string) ($_POST['cooldown'] ?? '14');
        if (!in_array($cool, ['0', '7', '14', '30'], true)) $cool = '14';
        cm_setting_set('smart.cooldown', $cool, (string) $admin['email']);
        cm_setting_set('smart.no_push_vip', empty($_POST['no_push_vip']) ? '0' : '1', (string) $admin['email']);
        $wasPaused = cm_smart_long_paused();
        $nowPaused = !empty($_POST['pause_long']);
        cm_setting_set('smart.pause_long', $nowPaused ? '1' : '0', (string) $admin['email']);
        if ($wasPaused !== $nowPaused) {
            cm_audit('smart.pause', '', null, ($nowPaused
                ? 'ALL 6+ month reminder sends paused by '
                : '6+ month reminder sends resumed by ') . (string) $admin['email']);
        }
        $wasAll = cm_smart_all_paused();
        $nowAll = !empty($_POST['pause_all']);
        cm_setting_set('smart.pause_all', $nowAll ? '1' : '0', (string) $admin['email']);
        if ($wasAll !== $nowAll) {
            cm_audit('smart.pause_all', '', null, ($nowAll
                ? 'EVERY automatic reminder email paused by '
                : 'automatic reminder emails resumed by ') . (string) $admin['email']);
        }
        cm_flash($nowAll && !$wasAll
            ? 'Saved — and EVERYTHING is now HELD. No automatic reminder email of any '
              . 'cycle goes out until you untick it. Emails you send by hand still go.'
            : ($nowPaused && !$wasPaused
               ? 'Saved — and every 6+ month reminder is now HELD. Nothing long-cycle goes out until you resume.'
               : 'Saved.'), ($nowAll && !$wasAll) || ($nowPaused && !$wasPaused) ? 'warn' : 'ok');
        cm_redirect('admin/smart-reminders.php#options');
    }

    cm_redirect('admin/smart-reminders.php');
}

$focuses = cm_smart_focuses();
$focus   = (string) ($_GET['focus'] ?? 'balanced');
if (!isset($focuses[$focus])) $focus = 'balanced';

$pool = cm_smart_pool();
[$list, $skipped] = cm_smart_pick($pool['cands'], $focus);
$pushList = cm_smart_pushback_candidates();
$waiting  = cm_smart_waiting_today();
$cap      = cm_followup_hourly_cap();
$usedHour = cm_followups_sent_last_hour();
$paused   = cm_smart_long_paused();
$pausedAll = cm_smart_all_paused();



$apptFortnight = 0;
try {
    $apptFortnight = (int) cm_val(
        'SELECT COUNT(*) FROM appointment WHERE on_date BETWEEN ? AND ?',
        [date('Y-m-d'), date('Y-m-d', time() + 14 * 86400)]);
} catch (Throwable $ex) {}

cm_head('Smart reminders', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'smart', 'Smart reminders');
?>
<?php  ?>
<p class="sub">
  Reminders can be a great lead gen, but when you set a reminder now for 6 months,
  1 year, or more, who knows what your schedule will be like. The idea of smart
  reminders is to give you a &ldquo;1 click tool&rdquo; to be able to send some
  reminders earlier, ahead of schedule, and/or pause them if your schedule is
  full, or if you are away for holidays.
</p>

<div class="card">
  <div class="card-head">
    <h2>Where things stand</h2>
    <span class="spacer"></span>
    <?php $vipNote = cm_smart_no_push_vip() ? ' (VIPs still send)' : ''; ?>
    <?php if ($pausedAll): ?>
      <span class="pill pill-spam">EVERYTHING PAUSED<?= e($vipNote) ?></span>
    <?php elseif ($paused): ?>
      <span class="pill pill-spam">All 6+ month sends PAUSED<?= e($vipNote) ?></span>
    <?php else: ?>
      <span class="pill pill-approved">Running</span>
    <?php endif; ?>
  </div>
  <div class="mini-grid">
    <div class="mini"><span class="n"><?= count($pool['cands']) ?></span> in the pull-forward pool</div>
    <div class="mini"><span class="n"><?= $waiting ?></span> already due, waiting on the next send pass</div>
    <div class="mini"><span class="n"><?= max(0, $cap - $usedHour) ?></span> of <?= $cap ?> left in this hour&rsquo;s email cap</div>
    <div class="mini"><span class="n"><?= $apptFortnight ?></span> appointments in the next 14 days</div>
  </div>
  <?php  ?>
  <div class="caps-pick mt-12">
    <label class="caps-row">
      <input type="checkbox" disabled>
      <span>
        <b>Automatic</b> <span class="pill pill-pending">coming soon</span>
        <span class="muted small d-block">One day this screen will read the diary and run these
        decisions itself. Today every send and every push-back is a button you press.</span>
      </span>
    </label>
  </div>
</div>

<?php 
 ?>
<div class="split-2 mb-16">
<section class="card m-0">
  <div class="card-head">
    <h2>Pull forward and send</h2>
    <span class="spacer"></span>
    <span class="pill <?= $list ? 'pill-pending' : 'pill-approved' ?>">
      <?= count($list) ?> of up to <?= CM_SMART_BATCH ?></span>
  </div>

  <?php  ?>
  <nav class="subnav subnav-sm" aria-label="Focus">
    <?php foreach ($focuses as $key => $f): ?>
      <a class="subnav-item<?= $key === $focus ? ' is-on' : '' ?>"
         href="<?= e(cm_url('admin/smart-reminders.php?focus=' . $key)) ?>"
         <?= $key === $focus ? 'aria-current="page"' : '' ?>><?= e($f['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <p class="muted small mt-6 mb-6"><?= e($focuses[$focus]['blurb']) ?></p>
  <details class="why">
    <summary>The logic behind &ldquo;<?= e($focuses[$focus]['label']) ?>&rdquo;</summary>
    <ul class="tile-list">
      <?php foreach ($focuses[$focus]['logic'] as $line): ?>
        <li class="small"><?= e($line) ?></li>
      <?php endforeach; ?>
      <li class="small muted">Seats are dealt across the distance bands — a couple from the
        next fortnight, a couple from next month, a couple from further out — so pulling
        forward never empties just next week. One seat per client; a client&rsquo;s other
        reminders ride along as &ldquo;+N more&rdquo;.</li>
      <li class="small muted">Eligible means: a follow-up email on a cycle of six months or
        longer, at least two thirds of the way through its wait — a 6-month reminder from
        4 months in, a 3-year from 2 years.</li>
    </ul>
  </details>

  <?php 




 ?>
  <p class="muted small mt-12">
    <?php if (!$list): ?>
      Nothing to pull forward right now<?= $pool['extended'] ? ' — the look even stretched a year out' : '' ?>.
    <?php else: ?>
      <?= count($list) ?> client<?= count($list) === 1 ? ' is' : 's are' ?> worth a reminder
      under this focus. Nothing sends from this page — the window shows every one
      before anything goes.
    <?php endif; ?>
  </p>
  <button class="btn mt-8" type="button" data-modal-open="dlg-smart-send">Choose who to send to</button>
  <p class="hint">Sent ones leave the list on their own — come back for the next twenty.
    What the hourly cap holds goes out on your next dashboard visit.</p>

  <?php if ($skipped): ?>
    <details class="morelist mt-10">
      <summary>Held back by the cooldown: <?= count($skipped) ?> client<?= count($skipped) === 1 ? '' : 's' ?></summary>
      <ul class="tile-list">
        <?php foreach ($skipped as $c): ?>
          <li class="small"><b><?= e($c['client_name']) ?></b>
            <span class="muted">&mdash; <?= e($c['skip_why']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</section>

<section class="card m-0" id="push">
  <div class="card-head">
    <h2>Push back</h2>
    <span class="spacer"></span>
    <span class="pill <?= $pushList ? 'pill-pending' : 'pill-approved' ?>"><?= count($pushList) ?> due soon</span>
  </div>
  <p class="muted small">
    The long-cycle follow-ups due in the next six weeks &mdash; overdue ones
    included &mdash; <b>lowest lifetime value first</b>, for the weeks you cannot
    take more work. Nothing here sends anything: ticking and pushing moves the
    date and <b>keeps the original on the row</b>, so a quiet month later the
    Balanced list lifts the most-delayed first, instead of them sliding for ever.
    <?= cm_smart_no_push_vip() ? 'VIPs are never listed while that option is on below.' : '' ?>
  </p>
  <?php 
 ?>
  <p class="muted small">
    <?= $pushList
        ? count($pushList) . ' reminder' . (count($pushList) === 1 ? ' is' : 's are') . ' due within six weeks.'
        : 'Nothing long-cycle is due in the next six weeks.' ?>
  </p>
  <button class="btn mt-8" type="button" data-modal-open="dlg-smart-push">Choose what to push back</button>
  <p class="hint">Pushed ones move down this list with their original date noted beside
    the new one.</p>
</section>
</div>

<div class="card" id="options">
  <h2>Options</h2>
  <form method="post">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="smart_opts">
    <div class="field">
      <label class="lbl" for="smart-cool">Do not send to a client who already had a reminder within</label>
      <select id="smart-cool" name="cooldown" class="f-auto">
        <?php 




 ?>
        <?php foreach (['0' => 'No limit', '7' => '7 days', '14' => '14 days', '30' => '30 days'] as $v => $lbl):
            $v = (string) $v; ?>
          <option value="<?= e($v) ?>" <?= (string) cm_smart_cooldown_days() === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <?php  ?>
      <div class="hint">You can set a reminder per job. Great if a client has one job
        &mdash; not ideal if they have 50 and you&rsquo;re flooding them with 50 emails.
        This helps avoid annoying the living f out of your clients by accident.</div>
    </div>
    <div class="caps-pick">
      <label class="caps-row">
        <input type="checkbox" name="no_push_vip" value="1" <?= cm_smart_no_push_vip() ? 'checked' : '' ?>>
        <span><b>Never push back VIPs</b>
          <span class="muted small">A VIP&rsquo;s reminder keeps its date whatever the
          workload &mdash; and keeps SENDING through either pause below. Untick it and
          VIPs are treated like everyone else.</span></span>
      </label>
      <?php 


 ?>
      <label class="caps-row">
        <input type="checkbox" name="pause_long" value="1" <?= $paused ? 'checked' : '' ?>>
        <span><b>Pause ALL 6+ month reminders
          <span id="pause-long-vips"><?= cm_smart_no_push_vip() ? 'excluding' : 'including' ?> VIPs</span></b>
          <?php  ?>
          <span class="muted small">If you are overloaded and cannot take more work, this
          pauses every reminder set for six months or longer &mdash; a year, two years.
          Those reminders can be crucial to bringing in leads, so it is only ever a pause,
          never a cancel. Reminders set for a week &mdash; or anything well under six
          months &mdash; keep going out: you may need those to follow something up
          urgently, while a 6-month-to-3-year reminder is safer to pause.</span></span>
      </label>
      <label class="caps-row">
        <input type="checkbox" name="pause_all" value="1" <?= $pausedAll ? 'checked' : '' ?>>
        <span><b>Pause EVERYTHING
          <span id="pause-all-vips"><?= cm_smart_no_push_vip() ? 'excluding' : 'including' ?> VIPs</span></b>
          <span class="muted small">The holiday switch: no automatic reminder email of any
          cycle goes out &mdash; a week, six months, three years &mdash; until this is
          unticked. Held, never cancelled: everything flows again the moment you are back,
          inside the hourly cap. Emails you press Send on yourself still go, and your own
          on-screen reminders still show &mdash; this stops only what the application would
          send on its own.</span></span>
      </label>
    </div>
    <button class="btn mt-12" type="submit">Save the options</button>
  </form>
</div>

<?php 








 ?>
<dialog class="modal modal-widest" id="dlg-smart-send">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Send reminders &mdash; <?= e($focuses[$focus]['label']) ?></h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php if (!$list): ?>
      <p class="modal-sub">Nobody is eligible right now, so there is nothing to tick.</p>
      <p class="muted small">
        This window fills with <b>client follow-up emails on a cycle of six months or
        longer</b> — a job&rsquo;s &ldquo;email them when it is time again&rdquo; — once
        each is two thirds of the way to its date. A 6-month reminder appears from
        4 months in; a 3-year from 2 years. Set one on a job, and it will surface
        here when its time nears<?= cm_smart_cooldown_days() > 0
          ? '; clients emailed within the last ' . cm_smart_cooldown_days()
            . ' days wait under the cooldown fold' : '' ?>.
      </p>
      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
      </div>
    <?php else: ?>
    <p class="modal-sub">Tick who gets one. Each tick arms one real email (or a spooled
    one while sending is off), sent inside the hourly cap the moment you press Send.</p>
    <form method="post" id="smart-send-form"
          data-progress="Queuing the reminders|Sending the emails|Finishing up"
          data-progress-title="Sending the reminders"
          data-progress-done="Sent — the message on the page has the count">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="smart_send">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Client</th><th>Client value</th><th>Job</th><th>Job value</th>
                     <th>Completed</th><th>Send</th></tr></thead>
          <tbody>
          <?php foreach ($list as $c): ?>
            <tr>
              <td><a href="<?= e(cm_url('admin/client.php?id=' . $c['cid'])) ?>"><b><?= e($c['client_name']) ?></b></a>
                <?php if ($c['more'] > 0): ?><span class="muted small">+<?= (int) $c['more'] ?> more</span><?php endif; ?></td>
              <td class="small mono"><?= $c['total_cents'] > 0 ? e(cm_money($c['total_cents'])) : '—' ?></td>
              <td class="small"><?= $c['job_name'] !== '' ? e($c['job_name']) : '—' ?></td>
              <td class="small mono"><?= $c['job_cents'] > 0 ? e(cm_money($c['job_cents'])) : '—' ?></td>
              <td class="small mono"><?= $c['job_done'] !== '' ? e(date('j M Y', (int) strtotime($c['job_done']))) : '—' ?></td>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $c['r']['id'] ?>"
                         aria-label="Send to <?= e($c['client_name']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit" id="smart-send-btn">Send the ticked reminders</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</dialog>
<?php 


 ?>
<dialog class="modal modal-widest" id="dlg-smart-push">
  <div class="modal-box">
    <div class="modal-head">
      <h2>Push reminders back</h2>
      <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
    </div>
    <?php if (!$pushList): ?>
      <p class="modal-sub">Nothing long-cycle is due in the next six weeks, so there is
      nothing to push.</p>
      <p class="muted small">
        This window lists the 6+ month follow-ups due within six weeks — overdue ones
        included — lowest lifetime value first<?= cm_smart_no_push_vip()
          ? ', with VIPs never listed while that option is on' : '' ?>. Pushing moves
        the date and keeps the original on the row.
      </p>
      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Close</button>
      </div>
    <?php else: ?>
    <p class="modal-sub">Tick what waits, pick how long, press push. Nothing here sends
    anything — dates move, and every original date stays on its row.</p>
    <form method="post"
          data-confirm="Push the ticked reminders back? Their original dates stay on the rows.">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="smart_push">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Client</th><th>Reminder</th><th>Due</th><th>Their history</th><th>Push</th></tr></thead>
          <tbody>
          <?php foreach ($pushList as $r): ?>
            <tr>
              <td><a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['cid'])) ?>"><b><?= e($r['client_name']) ?></b></a></td>
              <td class="small"><?= e((string) $r['title']) ?></td>
              <td class="small mono"><?= e(date('j M', (int) strtotime((string) $r['due_date']))) ?><?php
                if (($r['original_due'] ?? null) !== null && (string) $r['original_due'] !== (string) $r['due_date']): ?>
                <span class="muted">(was <?= e(date('j M', (int) strtotime((string) $r['original_due']))) ?>)</span>
              <?php endif; ?></td>
              <td class="small muted"><?= (int) $r['jobs_n'] ?> job<?= (int) $r['jobs_n'] === 1 ? '' : 's' ?><?=
                $r['total_cents'] > 0 ? ', ' . e(cm_money((int) $r['total_cents'])) . ' all time' : '' ?></td>
              <td><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>"
                         aria-label="Push back <?= e($r['client_name']) ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="row mt-12">
        <?php foreach ([2, 4, 8, 12] as $w): ?>
          <label class="mr-12"><input type="radio" name="weeks" value="<?= $w ?>" <?= $w === 4 ? 'checked' : '' ?>>
            <?= $w ?> weeks</label>
        <?php endforeach; ?>
      </div>
      <div class="modal-foot">
        <button class="btn" type="submit">Push the ticked reminders back</button>
        <button class="btn btn-quiet" type="button" data-modal-close>Cancel</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</dialog>
<script defer src="<?= e(cm_url('js/smart-send.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php cm_foot(); ?>
