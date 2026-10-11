<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0














declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/publicsite.php';
require_once CM_LIB . '/onboarding.php';

$admin = cm_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['booking_id'] ?? 0);

    if ($action === 'make_client' && $id > 0) {
        $b = cm_row('SELECT * FROM booking WHERE id = ?', [$id]);
        if ($b && $b['client_id'] === null) {



            $whole = trim((string) $b['name']);
            $parts = preg_split('/\s+/', $whole, 2) ?: [$whole];
            $cid = cm_insert(
                'INSERT INTO client (first_name, last_name, company, email, phone,
                                     status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$parts[0] ?? '', $parts[1] ?? '', (string) $b['company'],
                 (string) $b['email'], (string) $b['phone'], 'active', cm_now(), cm_now()]);
            cm_q('UPDATE booking SET client_id = ?, updated_at = ? WHERE id = ?',
                 [$cid, cm_now(), $id]);
            cm_audit('client.created', 'client', $cid,
                     'created from booking ' . (string) $b['reference'] . ' by ' . (string) $admin['email']);
            cm_flash('Client created. Their booking is linked to them.');
        }
        cm_redirect('admin/bookings.php');
    }
















    if ($action === 'booking_identity' && $id > 0) {
        $b = cm_row('SELECT * FROM booking WHERE id = ?', [$id]);
        if ($b === null) {
            cm_flash('That request is not here any more.', 'error');
            cm_redirect('admin/bookings.php');
        }
        $mode = (string) ($_POST['mode'] ?? '');
        $was  = $b['client_id'] !== null ? (int) $b['client_id'] : 0;
        $ref  = (string) $b['reference'];
        $parts = cm_booking_name_parts((string) $b['name']);

        if ($mode === 'keep') {
            cm_q('UPDATE booking SET identity_checked = 1, updated_at = ? WHERE id = ?', [cm_now(), $id]);
            cm_audit('booking.identity', 'client', $was ?: null,
                     'booking ' . $ref . ' confirmed as the same client, record unchanged, by '
                     . (string) $admin['email']);
            cm_flash('Kept as they are. This request will not ask again.');

        } elseif ($mode === 'update') {
            if ($was <= 0) {
                cm_flash('That request is not attached to anybody yet.', 'error');
                cm_redirect('admin/bookings.php');
            }



            $take = (array) ($_POST['take'] ?? []);
            $vals = ['first_name' => $parts['first'], 'last_name' => $parts['last'],
                     'company' => trim((string) $b['company']), 'email' => trim((string) $b['email']),
                     'phone' => trim((string) $b['phone'])];
            $set = [];
            $params = [];
            $said = [];
            foreach ($vals as $col => $v) {
                if (empty($take[$col]) || $v === '') continue;
                $set[] = $col . ' = ?';
                $params[] = $v;
                $said[] = $col;
            }
            if (!$set) {
                cm_flash('Nothing was ticked, so nothing changed. Tick the details to bring '
                       . 'across, or choose one of the other answers.', 'warn');
                cm_redirect('admin/bookings.php');
            }
            $params[] = cm_now();
            $params[] = $was;
            cm_q('UPDATE client SET ' . implode(', ', $set) . ', updated_at = ? WHERE id = ?', $params);
            cm_q('UPDATE booking SET identity_checked = 1, updated_at = ? WHERE id = ?', [cm_now(), $id]);
            cm_audit('client.updated', 'client', $was,
                     implode(', ', $said) . ' taken from booking ' . $ref . ' by ' . (string) $admin['email']);
            cm_flash('Updated: ' . implode(', ', $said) . '. Their history stays where it is.');

        } elseif ($mode === 'separate') {



            $cid = cm_insert(
                'INSERT INTO client (first_name, last_name, company, email, phone,
                                     status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$parts['first'], $parts['last'], (string) $b['company'],
                 (string) $b['email'], (string) $b['phone'], 'active', cm_now(), cm_now()]);
            cm_q('UPDATE booking SET client_id = ?, identity_checked = 1, updated_at = ? WHERE id = ?',
                 [$cid, cm_now(), $id]);
            cm_audit('client.created', 'client', $cid,
                     'created as a SEPARATE client from booking ' . $ref
                     . ($was ? ' (it had matched client #' . $was . ' on email; that record was left untouched)' : '')
                     . ' by ' . (string) $admin['email']);
            cm_flash('New client created from this request. The client it had matched — and all '
                   . 'their work — is untouched.');

        } elseif ($mode === 'move') {
            $to = (int) ($_POST['to_client'] ?? 0);
            $target = $to > 0 ? cm_row('SELECT id FROM client WHERE id = ?', [$to]) : null;
            if ($target === null) {
                cm_flash('That client is not here.', 'error');
                cm_redirect('admin/bookings.php');
            }
            cm_q('UPDATE booking SET client_id = ?, identity_checked = 1, updated_at = ? WHERE id = ?',
                 [$to, cm_now(), $id]);
            cm_audit('booking.identity', 'client', $to,
                     'booking ' . $ref . ' moved to this client' . ($was ? ' from #' . $was : '')
                     . ' by ' . (string) $admin['email']);
            cm_flash('Moved to that client.');

        } else {
            cm_flash('Pick one of the answers.', 'error');
        }
        cm_redirect('admin/bookings.php');
    }

    if ($action === 'set_status' && $id > 0) {
        $to = (string) ($_POST['status'] ?? '');
        if (in_array($to, ['new', 'accepted', 'declined'], true)) {
            cm_q('UPDATE booking SET status = ?, updated_at = ? WHERE id = ?', [$to, cm_now(), $id]);
            $said = 'Marked ' . $to . '.';






            if ($to === 'accepted') {
                $b = cm_row('SELECT * FROM booking WHERE id = ?', [$id]);
                $already = $b !== null && cm_val(
                    'SELECT id FROM onboarding_response WHERE booking_id = ?', [$id]) !== null;




                $skip = $b !== null && cm_onboard_skipped(
                    $b['template_id'] !== null ? (int) $b['template_id'] : null);
                if ($skip) {



                    $said .= cm_onboard_feature_ready()
                        ? ' This service does not use an onboarding form.'
                        : ' Onboarding forms are not switched on yet — you can still make one'
                          . ' by hand on the Onboarding screen.';
                } elseif ($b !== null && $b['client_id'] !== null && !$already) {
                    $token = cm_onboard_start((int) $b['client_id'], $id,
                                              $b['template_id'] !== null ? (int) $b['template_id'] : null);




                    $said .= cm_onboard_links_blocked() === ''
                        ? ' An onboarding form is ready to send — it is on the Onboarding screen.'
                        : ' An onboarding form is on the Onboarding screen, but its link will not '
                          . 'open until your client portal is switched on (Settings).';
                    cm_audit('onboarding.started', 'client', (int) $b['client_id'],
                             'onboarding form created on accepting booking ' . (string) $b['reference']);
                } elseif ($b !== null && $b['client_id'] === null) {
                    $said .= ' Add them as a client first and the onboarding form can go out.';
                }
            }
            cm_flash($said);
        }
        cm_redirect('admin/bookings.php');
    }

    if ($action === 'feedback_done') {
        $fid = (int) ($_POST['feedback_id'] ?? 0);
        cm_q("UPDATE client_feedback SET status = 'handled', handled_at = ? WHERE id = ?",
             [cm_now(), $fid]);
        cm_flash('Marked as dealt with.');
        cm_redirect('admin/bookings.php#feedback');
    }
}

$bookings = [];
$feedback = [];
try {
    $bookings = cm_all(
        "SELECT b.*, t.name AS service_name, c.vip AS client_vip
           FROM booking b LEFT JOIN job_template t ON t.id = b.template_id
                          LEFT JOIN client c ON c.id = b.client_id
          ORDER BY FIELD(b.status, 'new', 'accepted', 'declined'), b.created_at DESC
          LIMIT 100");


    $feedback = cm_all(
        "SELECT f.*, c.first_name, c.last_name, c.company, c.vip AS client_vip,
                j.name AS job_name
           FROM client_feedback f
      LEFT JOIN client c ON c.id = f.client_id
      LEFT JOIN job j ON j.id = f.job_id
          WHERE f.status = 'new'
          ORDER BY FIELD(f.sentiment, 'issue', 'unsaid', 'happy'), f.created_at
          LIMIT 50");
} catch (Throwable $ex) {

}

$newCount = 0;
foreach ($bookings as $b) if ((string) $b['status'] === 'new') $newCount++;

cm_head('Bookings', '', true);
cm_render_flashes();
cm_admin_nav('bookings');
?>
<div class="topbar">
  <h1>Bookings</h1>
  <?php if (cm_public_enabled()): ?>
    <a class="btn btn-quiet" href="<?= e(cm_portal_url()) ?>" target="_blank" rel="noopener">View your client portal</a>
  <?php endif; ?>
</div>
<p class="sub">What came in from your client portal. Nothing here becomes a
client or a job on its own.</p>

<?php if (!cm_public_enabled()): ?>
  <div class="note note-warn">
    <b>Your client portal is switched off</b>, so nothing new can arrive.
    <a href="<?= e(cm_url('admin/settings.php')) ?>">Turn it on under Settings → Your client portal</a>,
    then publish the services you want to sell.
  </div>
<?php endif; ?>

<?php  ?>
<div class="card" id="feedback">
  <div class="card-head">
    <?php 




 ?>
    <h2>Returning clients &amp; feedback<?= $feedback ? ' — ' . count($feedback) : '' ?></h2>
  </div>
  <?php if (!$feedback): ?>
    <p class="muted small">Nothing waiting. These arrive from the link a client
    is sent at the end of a job — someone asking for a tailored quote, or
    telling you something was not right.</p>
  <?php else: ?>
    <?php foreach ($feedback as $f): ?>
      <div class="fb-item<?= (string) $f['sentiment'] === 'issue' ? ' fb-issue' : '' ?>">
        <div class="fb-head">
          <b><?php
            echo $f['client_id']
              ? e(cm_client_display_name(['id' => $f['client_id'], 'first_name' => $f['first_name'],
                    'last_name' => $f['last_name'], 'company' => $f['company'], 'email' => '']))
              : 'Someone';
          ?></b>
          <?php if ((string) $f['sentiment'] === 'issue'): ?>
            <span class="pill pill-spam">not happy</span>
          <?php elseif ((string) $f['sentiment'] === 'happy'): ?>
            <span class="pill pill-approved">happy, wants more</span>
          <?php endif; ?>
          <?php if ($f['job_name']): ?>
            <span class="muted small">about <?= e((string) $f['job_name']) ?></span>
          <?php endif; ?>
          <span class="spacer"></span>
          <span class="muted small mono"><?= e(date('j M', strtotime((string) $f['created_at']))) ?></span>
        </div>
        <p class="small prewrap fb-body"><?= e((string) $f['wants']) ?></p>
        <div class="row-actions">
          <?php if ($f['client_id']): ?>
            <a class="btn btn-sm btn-quiet" href="<?= e(cm_url('admin/client.php?id=' . (int) $f['client_id'])) ?>">Open the client</a>
          <?php endif; ?>
          <form method="post" class="inline">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="feedback_done">
            <input type="hidden" name="feedback_id" value="<?= (int) $f['id'] ?>">
            <button class="btn btn-sm btn-quiet" type="submit">Dealt with</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php  ?>
<div class="card">
  <div class="card-head">
    <h2>Requests<?= $newCount > 0 ? ' — ' . $newCount . ' new' : '' ?></h2>
  </div>
  <?php if (!$bookings): ?>
    <p class="muted small">Nothing yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Ref</th><th>Who</th><th>Wants</th><th>Dates quoted</th><th></th></tr>
        </thead>
        <tbody>
          <?php 



 ?>
          <?php foreach ($bookings as $b): $rowVip = (int) ($b['client_vip'] ?? 0) === 1;



              $needsCheck = cm_booking_needs_identity_check($b); ?>
            <tr class="<?= trim(((string) $b['status'] === 'declined' ? 'muted ' : '') . ($rowVip ? 'is-vip' : '')) ?>">
              <td class="mono small nowrap">
                <?= e((string) $b['reference']) ?>
                <span class="muted d-block"><?= e(date('j M', strtotime((string) $b['created_at']))) ?></span>
              </td>
              <td class="small">
                <b><?= e((string) $b['name']) ?></b>
                <?php if ((string) $b['company'] !== ''): ?>
                  <span class="muted d-block"><?= e((string) $b['company']) ?></span>
                <?php endif; ?>
                <a href="mailto:<?= e((string) $b['email']) ?>" class="d-block"><?= e((string) $b['email']) ?></a>
                <?php if ((string) $b['phone'] !== ''): ?>
                  <span class="muted d-block"><?= e((string) $b['phone']) ?></span>
                <?php endif; ?>
                <?php if ($b['client_id']): ?>
                  <a class="pill pill-approved" href="<?= e(cm_url('admin/client.php?id=' . (int) $b['client_id'])) ?>">known client</a>
                <?php else: ?>
                  <span class="pill pill-pending">not on file</span>
                <?php endif; ?>
                <?php 



 ?>
                <?php if ($needsCheck): ?>
                  <button class="btn btn-sm mt-4" type="button" data-modal-open="dlg-who-<?= (int) $b['id'] ?>">
                    Is this the same client?</button>
                <?php endif; ?>
              </td>
              <td class="small">
                <?= $b['service_name'] ? '<b>' . e((string) $b['service_name']) . '</b>' : '<span class="muted">General enquiry</span>' ?>
                <?php if ((int) $b['quoted_cents'] > 0): ?>
                  <span class="mono d-block"><?= e(cm_money((int) $b['quoted_cents'])) ?></span>
                <?php endif; ?>
                <?php if (trim((string) ($b['message'] ?? '')) !== ''): ?>
                  <?php






                    $msg = trim((string) $b['message']);
                    $matchNote = '';
                    if (preg_match('/\n*\[(certain|likely|new)\]\s*(.*)$/s', $msg, $mm)) {
                        $matchNote = trim($mm[2]);
                        $msg = trim(substr($msg, 0, (int) strpos($msg, '[' . $mm[1] . ']')));
                    }
                  ?>
                  <details class="mt-4">
                    <summary class="small">what they said</summary>
                    <?php if ($msg !== ''): ?>
                      <p class="small prewrap mt-4"><?= e($msg) ?></p>
                    <?php endif; ?>
                    <?php if ($matchNote !== ''): ?>
                      <p class="muted small mt-4">When it arrived: <?= e($matchNote) ?></p>
                    <?php endif; ?>
                  </details>
                <?php endif; ?>
              </td>
              <td class="small nowrap">
                <?php if ($b['planned_start']): ?>
                  <?= e(date('j M', strtotime((string) $b['planned_start']))) ?>
                  <?php if ($b['planned_end']): ?>
                    &rarr; <?= e(date('j M', strtotime((string) $b['planned_end']))) ?>
                  <?php endif; ?>
                  <?php if ((string) $b['plan_note'] !== ''): ?>
                    <span class="muted small d-block"><?= e((string) $b['plan_note']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="row-actions">
                <?php if ($b['client_id'] === null): ?>
                  <form method="post" class="inline">
                    <?= cm_csrf_field() ?>
                    <input type="hidden" name="action" value="make_client">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-sm btn-quiet" type="submit">Add as a client</button>
                  </form>
                <?php elseif ($b['job_id']): ?>
                  <a class="btn btn-sm btn-quiet"
                     href="<?= e(cm_url('admin/job.php?id=' . (int) $b['job_id'])) ?>">Open the job</a>
                <?php else: ?>
                  <?php 






 ?>
                  <a class="btn btn-sm btn-quiet"
                     href="<?= e(cm_url('admin/client-next.php?id=' . (int) $b['client_id']
                                        . '&booking=' . (int) $b['id'])) ?>">Set them up</a>
                <?php endif; ?>
                <?php if ((string) $b['status'] === 'new'): ?>
                  <form method="post" class="inline">
                    <?= cm_csrf_field() ?>
                    <input type="hidden" name="action" value="set_status">
                    <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                    <button class="btn btn-sm" name="status" value="accepted">Accepted</button>
                    <button class="btn btn-sm btn-quiet" name="status" value="declined">Declined</button>
                  </form>
                <?php else: ?>
                  <span class="pill <?= (string) $b['status'] === 'accepted' ? 'pill-approved' : 'pill-spam' ?>">
                    <?= e((string) $b['status']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php 













 ?>
<?php foreach ($bookings as $b): ?>
  <?php if (!cm_booking_needs_identity_check($b)) continue; ?>
  <?php
    $wc = cm_row('SELECT * FROM client WHERE id = ?', [(int) $b['client_id']]);
    if ($wc === null) continue;
    $diffs = cm_booking_client_diffs($b, $wc);
    $rivals = cm_booking_rival_clients($b, (int) $b['client_id']);
    $wcName = cm_client_display_name($wc);
  ?>
  <dialog class="modal modal-widest" id="dlg-who-<?= (int) $b['id'] ?>">
    <div class="modal-box">
      <div class="modal-head">
        <h2>Is this the same client?</h2>
        <button class="modal-x" type="button" data-modal-close aria-label="Close">&times;</button>
      </div>
      <p class="modal-sub">
        Request <span class="mono"><?= e((string) $b['reference']) ?></span> was matched to
        <b><?= e($wcName) ?></b> because the email address is on their record. The details
        below disagree, so it is worth a look before any work is filed against them.
      </p>

      <div class="table-wrap">
        <table>
          <thead><tr><th></th><th>This request says</th><th>On file for <?= e($wcName) ?></th>
                     <th>Use the new one</th></tr></thead>
          <tbody>
          <?php foreach ($diffs as $d): ?>
            <tr<?= $d['differs'] ? ' class="is-vip"' : '' ?>>
              <td class="small"><b><?= e($d['label']) ?></b></td>
              <td class="small"><?= $d['booking'] !== '' ? e($d['booking']) : '<span class="muted">—</span>' ?></td>
              <td class="small"><?= $d['client'] !== '' ? e($d['client']) : '<span class="muted">—</span>' ?></td>
              <td>
                <?php if ($d['differs']): ?>
                  <input type="checkbox" form="who-update-<?= (int) $b['id'] ?>"
                         name="take[<?= e($d['key']) ?>]" value="1"
                         aria-label="Use the new <?= e(mb_strtolower($d['label'])) ?>">
                <?php else: ?>
                  <span class="muted small">same</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php 


 ?>
      <div class="split-2 mt-12">
        <div>
          <h3>Same client</h3>
          <form method="post" class="mb-12">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="booking_identity">
            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
            <input type="hidden" name="mode" value="keep">
            <button class="btn btn-quiet" type="submit">Keep their record as it is</button>
            <div class="hint">Nothing changes. This request stops asking.</div>
          </form>
          <form method="post" id="who-update-<?= (int) $b['id'] ?>"
                data-confirm="Take the ticked details from this request onto <?= e($wcName) ?>'s record? Their jobs and history are not touched.">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="booking_identity">
            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
            <input type="hidden" name="mode" value="update">
            <button class="btn" type="submit">Bring the ticked details across</button>
            <div class="hint">Their jobs, properties and history stay exactly where they are —
              only the details you ticked above change.</div>
          </form>
        </div>
        <div>
          <h3>Not the same client</h3>
          <form method="post"
                data-confirm="Create a brand new client from this request? <?= e($wcName) ?> and all their work are left untouched.">
            <?= cm_csrf_field() ?>
            <input type="hidden" name="action" value="booking_identity">
            <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
            <input type="hidden" name="mode" value="separate">
            <button class="btn" type="submit">Make this a separate client</button>
            <div class="hint">For the same person at a different business. The new record starts
              empty; <?= e($wcName) ?> keeps every job, property and file already done for them.</div>
          </form>
          <?php if ($rivals): ?>
            <p class="muted small mt-12"><b>Or it belongs to one of these</b> — same name or
              same business, already on file:</p>
            <?php foreach ($rivals as $rv): ?>
              <form method="post" class="mb-8">
                <?= cm_csrf_field() ?>
                <input type="hidden" name="action" value="booking_identity">
                <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                <input type="hidden" name="mode" value="move">
                <input type="hidden" name="to_client" value="<?= (int) $rv['id'] ?>">
                <button class="btn btn-sm btn-quiet" type="submit">Move to
                  <?= e(cm_client_display_name($rv)) ?></button>
              </form>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-quiet" type="button" data-modal-close>Decide later</button>
      </div>
    </div>
  </dialog>
<?php endforeach; ?>
<?php cm_foot(); ?>
