<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/worksummary.php';
require_once CM_LIB . '/branding.php';
require_once CM_LIB . '/jobs.php';
require_once CM_LIB . '/clients.php';




cm_public_require_enabled();

$row = cm_work_summary_resolve($_GET['k'] ?? null);
if ($row === null) {



    cm_public_404('That summary link is not available. If you were sent one recently, check the address, or reply to the email it came in.');
}

$job    = cm_job((int) $row['job_id']);
$client = $row['client_id'] !== null ? cm_client((int) $row['client_id']) : null;
$rows   = cm_work_summary_rows($row);
$biz    = cm_business_block();






if (cm_is_opened_ping()) {
    cm_require_csrf();
    if (empty($_SESSION['account_id'])) cm_work_summary_opened($row);
    cm_opened_ping_done();
}
if (empty($_SESSION['account_id']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_work_summary_opened($row);
}

$error = '';
$done  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $what = (string) ($_POST['what'] ?? '');



    $spam = cm_public_spam_check('summary');
    if ($spam === 'refused') {
        cm_redirect('portal/summary.php?k=' . rawurlencode((string) ($_GET['k'] ?? '')) . '&done=reply');
    }
    if ($spam !== '') {
        $error = $spam;
    } elseif ($what === 'approve') {
        $error = cm_work_summary_approve($row, (string) ($_POST['signature'] ?? ''),
                                         (string) ($_POST['signed_name'] ?? ''));
        if ($error === '') {




            $alongside = trim((string) ($_POST['feedback'] ?? ''));
            if ($alongside !== '') cm_work_summary_reply($row, $alongside);


            if ($row['approved_at'] === null) {
                cm_work_summary_notify($row, $job, $client, 'approved', $alongside);
            } elseif ($alongside !== '') {
                cm_work_summary_notify($row, $job, $client, 'replied', $alongside);
            }
            cm_redirect('portal/summary.php?k=' . rawurlencode((string) ($_GET['k'] ?? '')) . '&done=signed');
        }
    } elseif ($what === 'reply') {
        $text  = (string) ($_POST['feedback'] ?? '');
        $error = cm_work_summary_reply($row, $text);
        if ($error === '') {
            cm_work_summary_notify($row, $job, $client, 'replied', trim($text));
            cm_redirect('portal/summary.php?k=' . rawurlencode((string) ($_GET['k'] ?? '')) . '&done=reply');
        }
    }



    $fresh = cm_work_summary_resolve($_GET['k'] ?? null);
    if ($fresh !== null) $row = $fresh;
}

$done     = (string) ($_GET['done'] ?? '');
$signed   = $row['approved_at'] !== null;
$heading  = trim((string) $row['heading']) !== '' ? (string) $row['heading'] : 'Work summary';
$intro    = trim((string) ($row['intro'] ?? ''));









$who      = $client !== null ? trim((string) ($client['first_name'] ?? '')) : '';

cm_public_head($heading, '');
echo cm_opened_ping_marker();
?>

<?php if ($done === 'signed'): ?>
  <div class="note note-ok pub-flash">
    <b>Thank you — that is signed off.</b> We have a record of it, and a copy of
    this page stays at this address. Nothing else is needed from you.
  </div>
<?php elseif ($done === 'reply'): ?>
  <div class="note note-ok pub-flash">
    <b>Thank you — that has reached us.</b> We will come back to you about it.
    You can still sign the summary off below once you are happy.
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="note note-error pub-flash"><b>That did not save.</b> <?= e($error) ?></div>
<?php endif; ?>

<?php 



 ?>
<header class="ws-head">
  <div class="ws-biz">
    <?php if ($biz['logo'] !== ''): ?>
      <img class="ws-logo" src="<?= e($biz['logo']) ?>" alt="<?= e($biz['name']) ?>">
    <?php endif; ?>
    <div>
      <div class="ws-biz-name"><?= e($biz['name']) ?></div>
      <?php foreach ($biz['lines'] as $line): ?>
        <div class="ws-biz-line"><?= e($line) ?></div>
      <?php endforeach; ?>
      <?php if ($biz['phone'] !== '' || $biz['email'] !== ''): ?>
        <div class="ws-biz-line">
          <?php if ($biz['email'] !== ''): ?><a href="mailto:<?= e($biz['email']) ?>"><?= e($biz['email']) ?></a><?php endif; ?>
          <?php if ($biz['phone'] !== ''): ?>
            <?= $biz['email'] !== '' ? ' &middot; ' : '' ?>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $biz['phone'])) ?>"><?= e($biz['phone']) ?></a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="ws-doc">
    <div class="ws-doc-title"><?= e($heading) ?></div>
    <div class="ws-biz-line">Issued <?= e(date('j M Y', strtotime((string) $row['created_at']))) ?></div>
    <?php if ($signed): ?>
      <span class="pill pill-approved">Signed off</span>
    <?php endif; ?>
  </div>
</header>

<section class="ws-facts">
  <?php if ($who !== ''): ?>
    <div><span class="ws-k">For</span><span class="ws-v"><?= e($who) ?></span></div>
  <?php endif; ?>
  <div><span class="ws-k">Items</span><span class="ws-v"><?= e((string) count($rows)) ?></span></div>
  <?php if ($job !== null && $job['start_date']): ?>
    <div><span class="ws-k">Period</span><span class="ws-v"><?= e(date('j M Y', strtotime((string) $job['start_date']))) ?><?php
      if ($job['end_date']) echo ' – ' . e(date('j M Y', strtotime((string) $job['end_date']))); ?></span></div>
  <?php endif; ?>
</section>

<?php if ($intro !== ''): ?>
  <div class="ws-intro"><?= nl2br(e($intro)) ?></div>
<?php endif; ?>

<?php 



 ?>
<section class="ws-work">
  <h2 class="ws-h2">What was done</h2>
  <?php if (!$rows): ?>
    <p class="muted">Nothing was recorded against this job when the summary was
    made. If that looks wrong, reply below and we will check it.</p>
  <?php else: ?>
    <ol class="ws-list">
      <?php foreach ($rows as $r): ?>
        <li class="ws-item">
          <div class="ws-when mono"><?= e($r['date'] !== '' ? date('j M Y', strtotime($r['date'])) : '') ?></div>
          <div class="ws-what">
            <div class="ws-task"><?= e($r['task']) ?></div>
            <?php if ($r['note'] !== ''): ?>
              <div class="ws-note"><?= nl2br(e($r['note'])) ?></div>
            <?php endif; ?>
            <?php if ($r['url'] !== ''): ?>
              <?php 

 ?>
              <div class="ws-link">
                <a href="<?= e(cm_safe_href($r['url'])) ?>" target="_blank" rel="noopener nofollow"><?= e($r['url']) ?></a>
              </div>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>

<?php 




 ?>
<section class="ws-signoff" id="signoff">
  <h2 class="ws-h2"><?= $signed ? 'Signed off' : 'Sign this off' ?></h2>

  <?php if ($signed): ?>
    <div class="ws-signed">
      <?= cm_signature_img((string) $row['signature'], (string) $row['signed_name'], 'ws-signature') ?>
      <p class="muted small">
        Signed by <b><?= e((string) $row['signed_name']) ?></b>
        on <?= e(date('j M Y, g:ia', strtotime((string) $row['approved_at']))) ?>.
      </p>
    </div>

    <form method="post" class="pub-form mt-16">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="what" value="reply">
      <div class="hp-field" aria-hidden="true">
        <label for="company_website">Leave this empty</label>
        <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
      </div>
      <div class="field">
        <label class="lbl" for="fb">Anything else you would like to say?</label>
        <textarea id="fb" name="feedback" rows="4" class="f-full"></textarea>
      </div>
      <button class="btn btn-quiet" type="submit">Send it</button>
    </form>

  <?php else: ?>
    <p class="muted">
      If this is a fair record of the work, sign below to confirm it. If it is
      not, or you would rather say something first, use the box underneath —
      you do not have to sign to reply.
    </p>

    <form method="post" class="pub-form">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="what" value="approve">
      <div class="hp-field" aria-hidden="true">
        <label for="company_website">Leave this empty</label>
        <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
      </div>

      <div class="field ob-sign">
        <label class="lbl" for="signed_name">Your full name <span class="muted">(as you sign it)</span></label>
        <input type="text" id="signed_name" name="signed_name" class="f-full" maxlength="120"
               autocomplete="name" value="<?= e((string) ($_POST['signed_name'] ?? '')) ?>">

        <label class="lbl mt-8">Your signature</label>
        <div class="sig-wrap">
          <canvas id="sig" aria-label="Sign here with your mouse or finger"></canvas>
          <button type="button" class="sig-clear" id="sig-clear">Clear</button>
        </div>
        <?php 

 ?>
        <?php $keepSig = (string) ($_POST['signature'] ?? '');
              if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $keepSig)) $keepSig = ''; ?>
        <input type="hidden" id="sig-data" name="signature" value="<?= e($keepSig) ?>">
      </div>

      <div class="field">
        <label class="lbl" for="fb-with">Anything to add? <span class="muted">(optional)</span></label>
        <textarea id="fb-with" name="feedback" rows="3" class="f-full"><?= e((string) ($_POST['feedback'] ?? '')) ?></textarea>
      </div>

      <button class="btn" type="submit">Sign this off</button>
    </form>

    <?php 

 ?>
    <form method="post" class="pub-form ws-reply-only">
      <?= cm_csrf_field() ?>
      <input type="hidden" name="what" value="reply">
      <div class="hp-field" aria-hidden="true">
        <label for="company_website2">Leave this empty</label>
        <input type="text" id="company_website2" name="company_website" tabindex="-1" autocomplete="off">
      </div>
      <div class="field">
        <label class="lbl" for="fb-only">Or reply without signing</label>
        <textarea id="fb-only" name="feedback" rows="4" class="f-full"
                  placeholder="Tell us what is missing, wrong, or what you would like changed."></textarea>
      </div>
      <button class="btn btn-quiet" type="submit">Send a reply instead</button>
    </form>
  <?php endif; ?>
</section>

<?php if (!$signed): ?>
  <script defer src="<?= e(cm_url('js/signature-pad.js')) ?>?v=<?= e(rawurlencode(cm_asset_stamp())) ?>"></script>
<?php endif; ?>
<?php cm_public_foot(); ?>
