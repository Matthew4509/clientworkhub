<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0































declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/invoices.php';

cm_public_require_enabled();

$link = cm_link_resolve((string) ($_GET['k'] ?? ''));
if ($link === null) {

    cm_public_404('That link has expired or is no longer in use. '
                . 'Get in touch and we will send you a new one.');
}

$client = cm_link_client_view($link);
if ($client === null) {
    cm_public_404('That link is no longer in use.');
}




$token = (string) ($_GET['k'] ?? '');
$done  = false;




if (cm_is_opened_ping()) {
    cm_require_csrf();
    cm_link_touch($link);
    cm_opened_ping_done();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    $spam = cm_public_spam_check('feedback');
    if ($spam === 'refused') {
        cm_redirect('portal/my.php?k=' . $token . '&thanks=1');
    }
    if ($spam === '') {
        $wants = trim((string) ($_POST['wants'] ?? ''));
        $mood  = (string) ($_POST['sentiment'] ?? 'unsaid');















        if ($wants !== '' || $mood === 'happy' || $mood === 'issue') {





            $jobId = (int) ($_POST['job_id'] ?? 0);
            if ($jobId > 0 && !in_array($jobId, array_map(static fn(array $h): int => (int) $h['id'],
                                                          cm_link_history((int) $client['id'], 50)), true)) {
                $jobId = 0;
            }
            cm_feedback_create($client['id'], $jobId > 0 ? $jobId : null,
                               (int) $link['id'], $mood, $wants);




            $to = cm_mail_reply_to() !== '' ? cm_mail_reply_to() : cm_mail_from();
            if ($to !== '') {




                $subject = match ($mood) {
                    'issue' => 'Not happy — ',
                    'happy' => $wants !== '' ? 'Happy, and wants more — ' : 'Happy with it — ',
                    default => 'Wants something different — ',
                };
                cm_mail($to, $subject . $client['greet'],
                    "From the end-of-job link.\n\n"
                    . "Mood: " . $mood . "\n\n"
                    . ($wants !== '' ? $wants : '(no words — they ticked how it went and nothing else)')
                    . "\n\n"
                    . 'Open it: ' . cm_url('admin/bookings.php#feedback'),
                    ['kind' => 'feedback']);
            }
        }
    }
    cm_redirect('portal/my.php?k=' . $token . '&thanks=1');
}

$history = cm_link_history($client['id']);
$thanks  = isset($_GET['thanks']);

cm_public_head('Welcome back');
echo cm_opened_ping_marker();
?>

<?php if ($thanks): ?>
  <div class="note note-ok pub-flash">
    <b>Thank you &mdash; we have got that.</b> We read every one of these
    properly, and we will come back to you.
  </div>
<?php endif; ?>

<section class="pub-hero pub-hero-tight">
  <h1>Hello <?= e($client['greet']) ?></h1>
  <p class="pub-intro">Everything you have had from us is below, ready to run
  again whenever you are &mdash; and we would love to hear how the last one
  went first. That is what the box underneath is for.</p>
</section>

<?php 
















 ?>
<section class="pub-section">
  <div class="offer-box">
    <h2 class="pub-h2">How did we do?</h2>
    <p>We would love your feedback. Did we nail it &mdash; or is there
    something we could do differently on the next one? Either answer is
    useful, and both shape what we do for you from here.</p>

    <p>If you would like something tailored, tell us what you would change
    and we will price it up. There is no obligation and it costs you nothing
    to ask.</p>

    <p class="muted small">Things people usually ask for next time: more
    content each day, premium stock images, longer articles, deeper keyword
    and competitor research, or a different mix of channels.</p>

    <form method="post" class="pub-form mt-12">
      <?= cm_csrf_field() ?>
      <div class="hp-field" aria-hidden="true">
        <label for="company_website">Leave this empty</label>
        <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
      </div>
      <?php if ($history): ?>
        <div class="field">
          <label class="lbl" for="f_job">Which one is this about?</label>
          <select id="f_job" name="job_id" class="f-full">
            <option value="0">Not about a particular one</option>
            <?php foreach ($history as $h): ?>
              <option value="<?= (int) $h['id'] ?>"><?= e((string) $h['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="field">
        <label class="lbl">How did the last one go?</label>
        <div class="caps-pick">
          <label class="caps-row">
            <input type="radio" name="sentiment" value="happy">
            <span><b>Happy with it</b> <span class="muted small">— it went the way I wanted</span></span>
          </label>
          <label class="caps-row">
            <input type="radio" name="sentiment" value="issue">
            <span><b>Not happy</b> <span class="muted small">— something was not right and I would like it sorted</span></span>
          </label>
          <label class="caps-row">
            <input type="radio" name="sentiment" value="unsaid" checked>
            <span><b>Rather not say</b> <span class="muted small">— I just want to ask about something</span></span>
          </label>
        </div>
      </div>

      <div class="field">
        <?php 


 ?>
        <label class="lbl" for="f_wants">Anything you would like for the next one?</label>
        <textarea id="f_wants" name="wants" rows="5" class="f-full"
                  placeholder="Loved the posts — could we do more each week, and push harder on the local keywords next time?"></textarea>
        <div class="hint">Leave it empty if you just wanted to tell us how it
        went. That is worth having on its own.</div>
      </div>
      <button class="btn" type="submit">Send it to us</button>
    </form>
  </div>
</section>

<?php  ?>
<section class="pub-section">
  <h2 class="pub-h2">Ready to go again?</h2>

  <?php if (!$history): ?>
    <p class="muted">Nothing on your record yet.
      <a href="<?= e(cm_portal_url()) ?>">See what we do</a>.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="pkg-table">
        <thead>
          <tr><th>What you had</th><th>When</th><th class="num">Price</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h):



              $canReorder = (int) ($h['tpl_published'] ?? 0) === 1 && $h['template_id'] !== null;
              $cents = (int) ($h['tpl_cents'] ?? 0) > 0
                     ? (int) $h['tpl_cents'] : (int) ($h['amount_cents'] ?? 0);
              $freq  = (string) ($h['tpl_freq'] ?? $h['billing_freq'] ?? 'oneoff');
          ?>
            <tr>
              <td><b><?= e((string) $h['name']) ?></b></td>
              <td class="small muted">
                <?= $h['start_date'] ? e(date('M Y', strtotime((string) $h['start_date']))) : '' ?>
              </td>
              <td class="num"><?= $cents > 0 ? e(cm_public_price($cents, $freq)) : '—' ?></td>
              <td class="num">
                <?php if ($canReorder): ?>
                  <a class="btn btn-sm"
                     href="<?= e(cm_url('portal/service.php?id=' . (int) $h['template_id'] . '&k=' . $token)) ?>#book">Book it again</a>
                <?php else: ?>
                  <span class="muted small">ask us</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="mt-14"><a class="btn btn-quiet" href="<?= e(cm_portal_url()) ?>">See everything we do</a></p>
</section>

<?php 










 ?>
<?php $invoices = cm_client_invoices((int) $client['id'], true); ?>
<?php if ($invoices): ?>
  <section class="pub-section">
    <h2 class="pub-h2">Your invoices</h2>
    <p class="pub-lede">Anything paid prints as a receipt. Open it and use your
    browser&rsquo;s print command to save a PDF.</p>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Number</th><th>Date</th><th class="num">Amount</th><th></th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
            <?php $paid = (string) $inv['status'] === 'paid';
                  $void = (string) $inv['status'] === 'void'; ?>
            <tr>
              <td class="mono"><?= e((string) $inv['number']) ?></td>
              <td class="small"><?= e(date('j M Y', strtotime((string) $inv['issued_on']))) ?></td>
              <td class="num mono"><?= e(cm_invoice_money($inv, (int) $inv['total_cents'])) ?></td>
              <td class="small">
                <?php if ($void): ?><span class="pill">Cancelled</span>
                <?php elseif ($paid): ?><span class="pill pill-approved">Paid</span>
                <?php else: ?><span class="pill pill-live">Due
                  <?= e(date('j M', strtotime((string) $inv['due_on']))) ?></span>
                <?php endif; ?>
              </td>
              <td class="num">
                <?php if (!$void): ?>
                  <a class="btn btn-sm btn-quiet"
                     href="<?= e(cm_url('portal/receipt.php?k=' . rawurlencode($token) . '&i=' . (int) $inv['id'])) ?>">
                    <?= $paid ? 'Receipt' : 'Invoice' ?></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php cm_public_foot(); ?>
