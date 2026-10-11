<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/reminders.php';

$admin = cm_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $due   = trim((string) ($_POST['due'] ?? ''));
        $repeat = (string) ($_POST['repeat'] ?? 'none');
        $prio  = isset(cm_job_priorities()[(string) ($_POST['priority'] ?? '')]) ? (string) $_POST['priority'] : 'normal';
        $desc  = trim((string) ($_POST['description'] ?? ''));
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($clientId > 0 && !cm_client($clientId)) $clientId = 0;

        if ($title === '') {
            cm_flash('Say what to be reminded of.', 'error');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            cm_flash('Pick the day.', 'error');
        } else {
            cm_reminder_create($title, $due, $repeat, $prio,
                               $clientId > 0 ? $clientId : null, null, null, $desc);
            if ($clientId > 0) {
                cm_audit('reminder.created', 'client', $clientId,
                         '"' . $title . '" for ' . date('j M Y', strtotime($due)));
            }
            cm_flash('Reminder set for ' . date('j M Y', strtotime($due))
                   . ($repeat !== 'none' ? ', repeating' : '') . '.');
        }
        cm_redirect('admin/reminders.php');
    }


    if (isset($_POST['snooze_days'])) $action = 'snooze';

    if (in_array($action, ['done', 'snooze', 'cancel'], true)) {
        $r = cm_reminder((int) ($_POST['reminder_id'] ?? 0));
        if ($r && in_array((string) $r['status'], ['pending', 'snoozed'], true)) {
            if ($action === 'done') {
                $next = cm_reminder_complete($r);
                cm_flash('Done: ' . (string) $r['title']
                       . ($next !== '' ? '. The next one is set for ' . date('j M Y', strtotime($next)) . '.' : '.'));
            } elseif ($action === 'snooze') {
                $days = (int) ($_POST['snooze_days'] ?? 1);
                if (!in_array($days, [1, 7, 30], true)) $days = 1;
                $until = date('Y-m-d', strtotime('+' . $days . ' days'));
                cm_q("UPDATE reminder SET status='snoozed', snoozed_until=?, updated_at=? WHERE id=?",
                     [$until, cm_now(), (int) $r['id']]);
                cm_flash('Snoozed until ' . date('j M', strtotime($until)) . ' — it comes back, it does not disappear.');
            } else {
                cm_q("UPDATE reminder SET status='cancelled', updated_at=? WHERE id=?",
                     [cm_now(), (int) $r['id']]);
                cm_flash('Cancelled.');
            }
        }
        cm_redirect('admin/reminders.php');
    }
}

$open = [];
$done = [];
try {








    $open = cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                c.vip AS client_vip
         FROM reminder r LEFT JOIN client c ON c.id = r.client_id
         WHERE r.kind = 'nudge' AND r.status IN ('pending','snoozed') ORDER BY r.due_date");


    $scheduled = cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email,
                c.vip AS client_vip, j.name AS job_name
           FROM reminder r
      LEFT JOIN client c ON c.id = r.client_id
      LEFT JOIN job j ON j.id = r.job_id
          WHERE r.kind = 'email' AND r.status = 'pending' AND r.sent_at IS NULL
          ORDER BY r.due_date");
    $done = cm_all(
        "SELECT r.*, c.first_name, c.last_name, c.company, c.email AS client_email
         FROM reminder r LEFT JOIN client c ON c.id = r.client_id
         WHERE r.kind = 'nudge' AND r.status IN ('completed','cancelled')
         ORDER BY r.updated_at DESC LIMIT 20");
} catch (Throwable $ex) {

}
if (!isset($scheduled)) $scheduled = [];
$clients = [];
try {
    $clients = cm_all("SELECT * FROM client WHERE status <> 'inactive' ORDER BY company, first_name");
} catch (Throwable $ex) {
}

$today = date('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (string) ($_POST['action'] ?? '') === 'cancel_followup') {
    cm_require_csrf();
    $rid = (int) ($_POST['reminder_id'] ?? 0);
    $r = cm_reminder($rid);
    if ($r && (string) $r['kind'] === 'email' && $r['sent_at'] === null) {
        cm_q("UPDATE reminder SET status = 'cancelled', updated_at = ? WHERE id = ?",
             [cm_now(), $rid]);
        cm_flash('Cancelled — that email will not go out.');
    }
    cm_redirect('admin/reminders.php');
}

cm_head('Reminders', '', true);
cm_render_flashes();
cm_admin_nav('reminders');
?>
<div class="card-head">
  <h1>Reminders</h1>
  <span class="spacer"></span>
  <?php 

 ?>
  <?php if (!cm_home()): ?>
  <a class="btn btn-quiet" href="<?= e(cm_url('admin/smart-reminders.php')) ?>">Smart reminders</a>
  <?php endif; ?>
  <button class="btn" type="button" data-modal-open="dlg-new-reminder">Set a reminder</button>
</div>
<?php 


 ?>
<p class="sub">Follow up on clients, quotes, renewals and future work.</p>

<?php 



 ?>
<?php if (!cm_home()): ?>
<div class="card" id="scheduled">
  <div class="card-head">
    <h2>Automatic emails to clients<?= $scheduled ? ' — ' . count($scheduled) : '' ?></h2>
    <span class="spacer"></span>
    <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/email-templates.php?open=client_followup')) ?>">Edit the wording</a>
  </div>

  <?php if (!$scheduled): ?>
    <?php 

 ?>
    <p class="muted small">None scheduled. Marking a job complete offers you
    an email to the client on a date you pick; it waits here until then.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Goes out</th><th>To</th><th>About</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($scheduled as $r):
            $late = (string) $r['due_date'] < $today;
            $rowVip = (int) ($r['client_vip'] ?? 0) === 1; ?>
          <tr<?= $rowVip ? ' class="is-vip"' : '' ?>>
            <td class="mono small nowrap<?= $late ? ' occ-date occ-overdue' : '' ?>">
              <?= e(date('D j M', strtotime((string) $r['due_date']))) ?>
            </td>
            <td class="small">
              <?php if ($r['client_id']): ?>
                <a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'])) ?>">
                  <?= e(cm_client_display_name(['id' => $r['client_id'],
                        'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
                        'company' => $r['company'], 'email' => $r['client_email']])) ?></a>
                <?php if (trim((string) ($r['client_email'] ?? '')) === ''): ?>
                  <span class="pill pill-spam">no email address</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="small"><?= e((string) ($r['job_name'] ?? $r['title'])) ?></td>
            <td class="row-actions">
              <form method="post" class="inline"
                    data-confirm="Cancel this follow-up? Nothing will be sent.">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="action" value="cancel_followup">
                <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-sm btn-quiet" type="submit">Cancel</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php 






 ?>
  <?php $cronOn = false;





        try { $cronLastRun = (int) cm_setting('cron.last_run', 0);
              $cronOn = $cronLastRun > 0 && (time() - $cronLastRun) < 172800;
        } catch (Throwable $ex) {} ?>
  <p class="hint">
    <?php if (!$cronOn): ?>
      <a href="<?= e(cm_url('admin/settings.php#set-mail')) ?>"><b>Set up scheduled sending</b></a>
      so these go out on time. Otherwise they send when you next open the dashboard.
    <?php else: ?>
      A scheduled run sends these on their date.
    <?php endif; ?>
    <?php if (!cm_mail_enabled()): ?>
      <br><b class="txt-err">Email sending is off.</b> Messages are saved to storage/mail/ instead.
      <a href="<?= e(cm_url('admin/settings.php#set-mail')) ?>">Turn it on</a>.
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>

<div class="card">
  <h2>Open — <?= count($open) ?></h2>
  <?php if (!$open): ?>
    <p class="muted small">Nothing pending.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Due</th><th>Reminder</th><th>Client</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($open as $r):
            $overdue = (string) $r['status'] === 'pending' && (string) $r['due_date'] < $today;
            $rowVip  = (int) ($r['client_vip'] ?? 0) === 1;
            $rst = cm_reminder_statuses()[(string) $r['status']]; ?>
          <?php 
 ?>
          <tr class="<?= trim(($overdue ? 'occ-overdue ' : '') . ($rowVip ? 'is-vip' : '')) ?>">
            <td class="mono small occ-date nowrap">
              <?= e(date('D j M Y', strtotime((string) $r['due_date']))) ?>
              <?= $overdue ? '<span class="pill pill-spam">overdue</span>' : '' ?>
              <?php if ((string) $r['status'] === 'snoozed'): ?>
                <span class="pill pill-live">snoozed to <?= e(date('j M', strtotime((string) $r['snoozed_until']))) ?></span>
              <?php endif; ?>
            </td>
            <td><b><?= e((string) $r['title']) ?></b>
              <?php if ((string) $r['repeat_every'] !== 'none'): ?>
                <span class="muted small">(<?= e(strtolower(cm_reminder_repeats()[(string) $r['repeat_every']])) ?>)</span>
              <?php endif; ?>
              <?php if (trim((string) $r['description']) !== ''): ?>
                <div class="muted small"><?= e((string) $r['description']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($r['client_id']): ?>
                <a href="<?= e(cm_url('admin/client.php?id=' . (int) $r['client_id'])) ?>"><?= e(cm_client_display_name(['id' => $r['client_id'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'], 'company' => $r['company'], 'email' => $r['client_email']])) ?></a>
              <?php else: ?><span class="muted">—</span><?php endif; ?>
              <?php if ($rowVip): ?><span class="pill pill-vip">VIP</span><?php endif; ?>
            </td>
            <?php 









 ?>
            <td>
              <div class="btn-row">
              <form method="post" class="inline">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="reminder_id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-sm" name="action" value="done">Done</button>
                <button class="btn btn-sm btn-quiet" name="snooze_days" value="1">Snooze 1d</button>
                <button class="btn btn-sm btn-quiet" name="snooze_days" value="7">1w</button>
                <button class="btn btn-sm btn-quiet" name="snooze_days" value="30">1mo</button>
                <button class="btn btn-sm btn-quiet" name="action" value="cancel">Cancel</button>
              </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>



<?php if ($done): ?>
  <details class="morelist">
    <summary>Recently settled — <?= count($done) ?></summary>
    <div class="card mt-10">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Was due</th><th>Reminder</th><th>Outcome</th></tr></thead>
          <tbody>
          <?php foreach ($done as $r): $rst = cm_reminder_statuses()[(string) $r['status']]; ?>
            <tr>
              <td class="mono small"><?= e(date('j M Y', strtotime((string) $r['due_date']))) ?></td>
              <td class="small"><?= e((string) $r['title']) ?></td>
              <td><span class="pill <?= e($rst['pill']) ?>"><?= e($rst['label']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </details>
<?php endif; ?>
<?php cm_foot(); ?>
