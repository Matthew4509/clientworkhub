<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0













declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/jobs.php';

require_once CM_LIB . '/unsubscribe.php';



require_once CM_LIB . '/worksummary.php';

$admin = cm_require_admin();

$job = cm_job((int) ($_GET['id'] ?? 0));
if (!$job) {
    cm_flash('There is no job with that number.', 'warn');
    cm_redirect('admin/jobs.php');
}
$id     = (int) $job['id'];
$client = cm_client((int) $job['client_id']);

$includeTime = !empty($_REQUEST['with_time']);
$spent = cm_time_totals('job', $id);







$doneRows = cm_all(
    "SELECT o.scheduled_date, o.note, o.content_url,
            " . cm_occurrence_name_sql('a', 'j') . " AS action_name
       FROM action_occurrence o
       LEFT JOIN job_action a ON a.id = o.action_id
       JOIN job j ON j.id = o.job_id
      WHERE o.job_id = ? AND o.status = 'completed'
      ORDER BY o.scheduled_date, " . cm_occurrence_name_sql('a', 'j'), [$id]);






function cm_summary_text(array $job, array $client, array $doneRows, bool $includeTime, array $spent): string
{
    $name = cm_client_display_name($client);
    $out  = 'Work summary — ' . (string) $job['name'] . "\n";
    $out .= 'For: ' . $name . "\n";
    $range = ($job['start_date'] ? date('j M Y', strtotime((string) $job['start_date'])) : '')
           . ' – ' . ($job['end_date'] ? date('j M Y', strtotime((string) $job['end_date'])) : 'ongoing');
    $out .= 'Period: ' . $range . "\n";
    if ((string) $job['status'] === 'completed' && $job['completed_at']) {
        $out .= 'Completed: ' . date('j M Y', strtotime((string) $job['completed_at'])) . "\n";
    }
    if (trim((string) $job['description']) !== '') {
        $out .= "\n" . trim((string) $job['description']) . "\n";
    }
    $out .= "\nWork completed (" . count($doneRows) . " task" . (count($doneRows) === 1 ? '' : 's') . "):\n";
    foreach ($doneRows as $o) {
        $line = '  · ' . date('j M', strtotime((string) $o['scheduled_date'])) . ' — ' . (string) $o['action_name'];
        if (trim((string) $o['note']) !== '') $line .= ': ' . trim((string) $o['note']);
        if ((string) $o['content_url'] !== '') $line .= ' (' . (string) $o['content_url'] . ')';
        $out .= $line . "\n";
    }
    if ($includeTime && $spent['total'] > 0) {
        $out .= "\nTime spent: " . cm_minutes_label($spent['total']) . "\n";
    }



    $out .= "\n— " . cm_business_display_name() . "\n";
    return $out;
}

$text = cm_summary_text($job, $client, $doneRows, $includeTime, $spent);







$tab  = (string) ($_GET['tab'] ?? 'client') === 'internal' ? 'internal' : 'client';
$sort = (string) ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
$internalRows = [];
if ($tab === 'internal') {
    $internalRows = cm_all(
        "SELECT o.id, o.scheduled_date, o.completed_at, o.internal_note, o.note,
                " . cm_occurrence_name_sql('a', 'j') . " AS action_name,
                COALESCE((SELECT SUM(t.minutes) FROM time_entry t WHERE t.occurrence_id = o.id), 0) AS minutes
           FROM action_occurrence o
           LEFT JOIN job_action a ON a.id = o.action_id
           JOIN job j ON j.id = o.job_id
          WHERE o.job_id = ? AND o.status = 'completed'
          ORDER BY o.scheduled_date " . ($sort === 'oldest' ? 'ASC' : 'DESC')
             . ", o.completed_at " . ($sort === 'oldest' ? 'ASC' : 'DESC') . ", o.id " . ($sort === 'oldest' ? 'ASC' : 'DESC'),
        [$id]);
}





require_once CM_LIB . '/branding.php';
$pdfLogo = '';
$pdfLogoType = 'PNG';
$pdfLogoSize = null;
$logoFile = cm_logo_file();
if ($logoFile !== null && @filesize($logoFile) < 800000) {
    $raw = @file_get_contents($logoFile);
    if ($raw !== false) {
        $pdfLogo = 'data:' . cm_logo_mime() . ';base64,' . base64_encode($raw);
        $pdfLogoType = cm_logo_mime() === 'image/jpeg' ? 'JPEG' : 'PNG';
        $pdfLogoSize = cm_logo_size();
    }
}
$bizLines = [];
foreach (['business.address', 'business.suburb', 'business.state', 'business.postcode', 'business.country'] as $k) {
    $v = trim((string) (cm_setting($k, '') ?? ''));
    if ($v !== '') $bizLines[] = $v;
}

$addr = trim((string) (cm_setting('business.address', '') ?? ''));
$town = trim(implode(' ', array_filter([trim((string) (cm_setting('business.suburb', '') ?? '')),
                                        trim((string) (cm_setting('business.state', '') ?? '')),
                                        trim((string) (cm_setting('business.postcode', '') ?? ''))])));
$country = trim((string) (cm_setting('business.country', '') ?? ''));
$bizLines = array_values(array_filter([$addr, $town, $country], static fn($l) => $l !== ''));
$forLines = array_values(array_filter([
    cm_client_display_name($client),
    trim((string) ($client['company'] ?? '')) !== '' && trim((string) ($client['company'] ?? '')) !== cm_client_display_name($client)
        ? trim((string) $client['company']) : '',
    trim((string) ($client['email'] ?? '')),
], static fn($l) => $l !== ''));
$facts = [['Period', ($job['start_date'] ? date('j M Y', strtotime((string) $job['start_date'])) : '')
                     . ' - ' . ($job['end_date'] ? date('j M Y', strtotime((string) $job['end_date'])) : 'ongoing')]];
if ((string) $job['status'] === 'completed' && $job['completed_at']) {
    $facts[] = ['Completed', date('j M Y', strtotime((string) $job['completed_at']))];
}
$facts[] = ['Job', (string) $job['name']];
$pdfData = [
    'business' => ['name' => cm_business_display_name(), 'lines' => $bizLines,
                   'phone' => trim((string) (cm_setting('public.phone', '') ?? '')),
                   'email' => trim((string) (cm_setting('public.email', '') ?? '')),
                   'website' => trim((string) (cm_setting('public.home_url', '') ?? '')),
                   'logo' => $pdfLogo, 'logoType' => $pdfLogoType,
                   'logoW' => $pdfLogoSize['w'] ?? 0, 'logoH' => $pdfLogoSize['h'] ?? 0],
    'doc' => ['title' => 'Work summary', 'date' => date('j M Y'),
              'forLines' => $forLines, 'facts' => $facts,
              'description' => trim((string) $job['description']),
              'rows' => array_map(static fn(array $o): array => [
                  'date' => date('j M Y', strtotime((string) $o['scheduled_date'])),
                  'task' => (string) $o['action_name'],
                  'note' => trim((string) $o['note']),
                  'url'  => (string) $o['content_url'],
              ], $doneRows),
              'timeSpent' => $includeTime && $spent['total'] > 0 ? cm_minutes_label($spent['total']) : '',
              'filename' => 'work-summary-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $job['name']) . '-' . date('Y-m-d') . '.pdf'],
];

$summaryUrl = static fn(string $tabKey, string $sortKey = 'newest'): string =>
    cm_url('admin/summary.php?id=' . $id . '&tab=' . $tabKey . ($tabKey === 'internal' && $sortKey !== 'newest' ? '&sort=' . $sortKey : ''));











require_once CM_LIB . '/emails.php';
require_once CM_LIB . '/publicsite.php';

$myLink = '';
try {
    $myLink = cm_public_enabled() ? cm_link_url(cm_link_for((int) $client['id'])) : '';
} catch (Throwable $ex) {}

$firstName = trim((string) ($client['first_name'] ?? ''));
$composed = cm_email_compose('job_summary', [
    'client_name'       => cm_client_display_name($client),
    'client_first_name' => $firstName !== '' ? $firstName : cm_client_display_name($client),
    'job_name'          => (string) $job['name'],
    'summary'           => $text,
    'my_link'           => $myLink,
]);
$emailText    = $composed['body'];
$emailSubject = $composed['subject'] !== '' ? $composed['subject'] : 'Work summary — ' . (string) $job['name'];


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();


    if (cm_home()) cm_redirect('admin/summary.php?id=' . $id);





    if ((string) ($_POST['action'] ?? '') === 'share') {
        $made = cm_work_summary_issue($id, (int) $client['id'], $doneRows, [
            'expiry_days'  => $_POST['expiry_days'] ?? null,
            'heading'      => (string) ($_POST['heading'] ?? ''),
            'intro'        => (string) ($_POST['intro'] ?? ''),
            'include_time' => $includeTime,
        ], (string) $admin['email']);

        cm_flash($made['ok']
            ? 'Link created. Copy it below and send it however you like — the client can sign '
              . 'the summary off or reply to it, and whatever they do appears on this screen.'
            : $made['error'], $made['ok'] ? 'ok' : 'error');
        cm_redirect('admin/summary.php?id=' . $id . '#share');
    }

    if ((string) ($_POST['action'] ?? '') === 'revoke_share') {
        cm_work_summary_revoke((int) ($_POST['share_id'] ?? 0), (string) $admin['email']);
        cm_flash('That link has been stopped. Anything the client already signed or said is kept.');
        cm_redirect('admin/summary.php?id=' . $id . '#share');
    }

    if ((string) ($_POST['action'] ?? '') === 'send') {
        $to   = strtolower(trim((string) ($_POST['to'] ?? '')));
        $body = (string) ($_POST['body'] ?? '');

        if (!cm_email_valid($to)) {
            cm_flash('That does not look like an email address.', 'error');
        } elseif (trim($body) === '') {
            cm_flash('The summary box is empty — nothing to send.', 'error');
        } else {



            $ok = cm_mail($to, $emailSubject, $body,
                          ['kind' => 'job_summary', 'client_id' => (int) $client['id']]);
            if (!$ok && cm_client_is_unsubscribed((int) $client['id'])) {


                cm_flash('Not sent — this client has unsubscribed from all emails. '
                       . 'They have to call and confirm before you can turn it back on.', 'error');
                cm_redirect('admin/summary.php?id=' . $id);
            }
            if ($ok) {
                cm_audit('summary.emailed', 'client', (int) $client['id'],
                         'job "' . (string) $job['name'] . '" summary emailed to ' . $to
                         . (cm_mail_can_deliver() ? '' : ' (spooled — email is off)'));
                cm_flash(cm_mail_can_deliver()
                    ? 'Sent to ' . $to . '.'
                    : 'Written to storage/mail/ — email sending is off, so nothing has actually gone out. '
                    . 'Turn it on under Settings → Email when you are ready.');
                cm_redirect('admin/job.php?id=' . $id);
            }
            cm_flash('The mailer refused it — check Settings → Email.', 'error');
        }
    }
}

cm_head('Summary — ' . $job['name'], '', true);
cm_render_flashes();
cm_admin_nav('jobs');




echo cm_crumbs_html([
    ['label' => 'Clients', 'href' => cm_url('admin/clients.php')],
    ['label' => cm_client_display_name($client),
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'])],
    ['label' => 'All Jobs',
     'href'  => cm_url('admin/client.php?id=' . (int) $client['id'] . '&tab=jobs')],
    ['label' => (string) $job['name'],
     'href'  => cm_url('admin/job.php?id=' . (int) $job['id'])],
    ['label' => 'Work summary'],
]);

?>
<p class="muted small no-print mb-4">
  <a href="<?= e(cm_url('admin/job.php?id=' . $id)) ?>">← <?= e((string) $job['name']) ?></a>
</p>
<h1>Work summary</h1>

<nav class="subnav no-print" aria-label="Summary views">
  <a class="subnav-item<?= $tab === 'client' ? ' is-on' : '' ?>" href="<?= e($summaryUrl('client')) ?>"
     <?= $tab === 'client' ? 'aria-current="page"' : '' ?>>Client summary</a>
  <a class="subnav-item<?= $tab === 'internal' ? ' is-on' : '' ?>" href="<?= e($summaryUrl('internal', $sort)) ?>"
     <?= $tab === 'internal' ? 'aria-current="page"' : '' ?>>Internal note summary</a>
</nav>

<?php if ($tab === 'internal'): ?>
<p class="sub">The other record of the same days — what you wrote for yourself,
and the time each one took. Nothing on this tab is ever sent or printed for a
client.</p>

<div class="card">
  <div class="card-head">
    <h2>Internal notes</h2>
    <span class="spacer"></span>
    <nav class="subnav subnav-sm" aria-label="Sort">
      <a class="subnav-item<?= $sort === 'newest' ? ' is-on' : '' ?>" href="<?= e($summaryUrl('internal', 'newest')) ?>">Newest first</a>
      <a class="subnav-item<?= $sort === 'oldest' ? ' is-on' : '' ?>" href="<?= e($summaryUrl('internal', 'oldest')) ?>">Oldest first</a>
    </nav>
  </div>
  <?php if (!$internalRows): ?>
    <p class="muted small m-0">Nothing completed on this job yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Time</th><th>Time spent</th><th>Note</th></tr></thead>
        <tbody>
        <?php foreach ($internalRows as $r): ?>
          <tr>
            <td class="mono small nowrap"><?= e(date('D j M Y', strtotime((string) $r['scheduled_date']))) ?></td>
            <td class="mono small nowrap"><?= $r['completed_at'] ? e(date('g:ia', strtotime((string) $r['completed_at']))) : '&mdash;' ?></td>
            <td class="mono small nowrap"><?= (int) $r['minutes'] > 0 ? e(cm_minutes_label((int) $r['minutes'])) : '&mdash;' ?></td>
            <td class="small">
              <?php if (trim((string) $r['internal_note']) !== ''): ?>
                <span class="prewrap"><?= e(trim((string) $r['internal_note'])) ?></span>
              <?php else: ?>
                <span class="muted">&mdash; no internal note</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php cm_foot(); return; ?>
<?php endif; ?>

<p class="sub no-print">Everything you typed under <b>What was done</b>, and
nothing else. Internal notes, credentials and rates have no path into this
sheet. <?= cm_home() ? 'Print it (Ctrl+P) or save it as a PDF.' : 'Print it (Ctrl+P) or edit and email it below.' ?></p>

<div class="card" id="summary-sheet">
  <div class="card-head no-print">
    <h2>The sheet</h2>
    <span class="spacer"></span>
    <?php 
 ?>
    <button class="btn btn-sm" type="button" data-summary-pdf>Download PDF</button>
  </div>
  <pre class="small sheet-text"><?= e($text) ?></pre>
</div>
<?php 
 ?>
<script type="application/json" id="summary-pdf-data"><?= json_encode($pdfData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<script defer src="<?= e(cm_url('js/jspdf.umd.min.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<script defer src="<?= e(cm_url('js/summary-pdf.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>

<div class="card no-print">
  <h2>Options</h2>
  <form method="get" action="<?= e(cm_url('admin/summary.php')) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label class="tick">
      <input type="checkbox" name="with_time" value="1" <?= $includeTime ? 'checked' : '' ?>>
      <span><b>Include time spent</b> — <?= e(cm_minutes_label($spent['total'])) ?> tracked.
        Off by default: hours are internal unless you decide otherwise.</span>
    </label>
    <button class="btn btn-sm btn-quiet" type="submit">Redo the summary</button>
  </form>
</div>

<?php $unsub = cm_unsub_state((int) $client['id']); ?>
<?php if (cm_home()): ?>
<?php elseif ($unsub !== null && $unsub['unsubscribed']): ?>
  <?php 

 ?>
  <div class="card no-print">
    <h2>Email it</h2>
    <div class="note note-error m-0">
      <b>This client has unsubscribed from all emails.</b>
      They asked us to stop on
      <?= e(date('j M Y', strtotime((string) $unsub['unsubscribed_at']))) ?>,
      so nothing can be sent to them — this summary included.
      <p class="m-0 mt-8">You can still print this sheet, or read it to them.
      To start emails again they have to call and confirm, and then you turn
      it back on from
      <a href="<?= e(cm_url('admin/client.php?id=' . (int) $client['id'])) ?>">their file</a>.</p>
    </div>
  </div>
<?php else: ?>
<?php 






 ?>
<div class="card no-print" id="share">
  <div class="card-head">
    <h2>Share it with the client</h2>
    <span class="spacer"></span>
    <?php $shares = cm_work_summaries_for_job($id); ?>
    <?php if ($shares): ?>
      <span class="muted small"><?= count($shares) ?> sent</span>
    <?php endif; ?>
  </div>

  <?php if (!cm_work_summary_ready()): ?>
    <?php 
 ?>
    <div class="note note-warn">
      <b>Sharing needs a database update that has not run yet.</b>
      <a href="<?= e(cm_url('admin/update.php')) ?>">Open Update &amp; backup</a> and apply what is waiting.
    </div>
  <?php else: ?>

    <p class="muted small">
      Creates a private web page of this summary, at its own address. The client
      can read it, <b>sign it off</b>, or <b>reply</b> without signing — and whatever
      they do appears here.
    </p>

    <details class="why">
      <summary>What the client sees, and what they cannot</summary>
      <p class="muted small">
        Your business details at the top, then the dates and the notes from
        <b>What was done</b> above — the same four fields the printed sheet uses.
        Internal notes, rates, credentials and their own contact details have no
        path onto it.
      </p>
      <p class="muted small">
        <b>The page is frozen when you create it.</b> Work marked done afterwards
        does not appear on a link already sent, because a summary somebody has
        signed must not change underneath their signature. Send a second link to
        show the newer work.
      </p>
    </details>

    <form method="post" class="mt-12">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="action" value="share">
      <div class="row">
        <label class="lbl" for="sh_heading">Title <span class="muted">(optional)</span></label>
        <input type="text" id="sh_heading" name="heading" maxlength="190"
               placeholder="Work summary" class="f-full">
        <div class="hint">Shown at the top of the page. Empty uses &ldquo;Work summary&rdquo;.</div>
      </div>
      <div class="row">
        <label class="lbl" for="sh_intro">A line to the client <span class="muted">(optional)</span></label>
        <textarea id="sh_intro" name="intro" rows="3" class="f-full"
                  placeholder="Here is everything we did this month &mdash; have a look and sign it off when you are happy."></textarea>
      </div>
      <div class="row">
        <label class="lbl">How long the link works</label>
        <?= cm_link_expiry_radios('expiry_days') ?>
        <div class="hint">Once they have signed it off, the record is kept here whatever the link does.</div>
      </div>
      <button class="btn" type="submit">Create the link</button>
    </form>

    <?php if ($shares): ?>
      <h3 class="mt-16">Links sent</h3>
      <?php foreach ($shares as $sh): $st = cm_work_summary_state($sh); $tok = cm_work_summary_token_of($sh); ?>
        <div class="list-row">
          <div class="grow">
            <div>
              <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
              <span class="muted small">
                created <?= e(date('j M Y', strtotime((string) $sh['created_at']))) ?>
                &middot; <?= e(cm_link_expiry_label($sh['expires_at'] === null ? null : (string) $sh['expires_at'])) ?>
              </span>
            </div>

            <?php if ($tok !== '' && $sh['revoked_at'] === null): ?>
              <?php 



 ?>
              <div class="cred-pair mt-8">
                <input type="text" id="shlink<?= (int) $sh['id'] ?>" class="mono cred-box" readonly tabindex="-1"
                       aria-label="The link to send" value="<?= e(cm_work_summary_url($tok)) ?>">
                <button class="pw-eye cred-copy" type="button" data-copy-for="shlink<?= (int) $sh['id'] ?>"
                        aria-label="Copy the link" title="Copy the link"><?= cm_icon_copy() ?></button>
              </div>
            <?php elseif ($sh['revoked_at'] === null): ?>
              <p class="hint">The address for this one cannot be read back on this site. Create a new link.</p>
            <?php endif; ?>

            <?php if ($sh['approved_at'] !== null): ?>
              <p class="small mt-8">
                <b>Signed off</b> by <?= e((string) $sh['signed_name']) ?>
                on <?= e(date('j M Y, g:ia', strtotime((string) $sh['approved_at']))) ?>.
              </p>
              <?= cm_signature_img((string) $sh['signature'], (string) $sh['signed_name'], 'ws-signature') ?>
            <?php endif; ?>

            <?php if (trim((string) ($sh['feedback'] ?? '')) !== ''): ?>
              <div class="note mt-8">
                <b>What they said</b>
                <div class="prewrap small"><?= e(trim((string) $sh['feedback'])) ?></div>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($sh['revoked_at'] === null): ?>
            <form method="post" data-confirm="Stop this link working? Anything the client already signed or said is kept.">
              <?= cm_csrf_field() ?>
              <input type="hidden" name="action" value="revoke_share">
              <input type="hidden" name="share_id" value="<?= (int) $sh['id'] ?>">
              <button class="btn btn-sm btn-quiet" type="submit">Stop this link</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  <?php endif; ?>
</div>

<div class="card no-print">
  <h2>Email it</h2>
  <p class="muted small">What is in the box is exactly what goes — edit it
  freely first. <?= cm_mail_can_deliver()
      ? '' : '<b>Email sending is currently off</b>, so this will be written to storage/mail/ instead of sent.' ?></p>
  <?php 

 ?>
  <form method="post"
        data-confirm="Email this summary to <?= e((string) ($client['email'] ?: 'the address above')) ?>?">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="action" value="send">
    <div class="row">
      <label class="lbl" for="to">To</label>
      <input type="email" id="to" name="to" value="<?= e((string) $client['email']) ?>" required>
      <div class="hint"><?= (string) $client['email'] !== '' ? 'The client\'s address, from their page.' : 'This client has no email stored — type one.' ?></div>
    </div>
    <div class="row">
      <label class="lbl" for="body">The summary</label>
      <textarea id="body" name="body" rows="18" class="small"><?= e($emailText) ?></textarea>
      <?php 
 ?>
      <div class="hint">An unsubscribe link is included in all emails sent by
      this platform.</div>
    </div>
    <button class="btn" type="submit">Send the summary</button>
  </form>
</div>
<?php endif; ?>
<?php 
 ?>
<?= cm_credential_tools_script_once() ?>
<?php cm_foot(); ?>
