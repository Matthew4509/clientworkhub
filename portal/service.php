<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';

require_once CM_LIB . '/captcha.php';

cm_public_require_enabled();

$service = cm_public_service((int) ($_GET['id'] ?? 0));
if ($service === null) {
    cm_public_404('That service is not available.');
}




$link = cm_link_resolve((string) ($_GET['k'] ?? ''));

$quote = cm_public_quote($service);
$name  = cm_public_service_name($service);




$draft = cm_public_draft_take();
$dv    = static fn(string $f): string => cm_public_draft_value($draft, $f);

cm_public_head($name, trim((string) ($service['public_blurb'] ?? '')));
?>
<p class="pub-back"><a href="<?= e(cm_portal_url()) ?>">&larr; Everything we do</a></p>

<?php if ($draft['error'] !== ''): ?>
  <div class="note note-error pub-flash">
    <b>That did not send.</b> <?= e($draft['error']) ?>
  </div>
<?php endif; ?>

<div class="pub-split">
  <div>
    <?php 




 ?>
    <div class="pub-hero">
      <h1><?= e($name) ?></h1>
      <?php if (trim((string) ($service['public_blurb'] ?? '')) !== ''): ?>
        <p class="pub-intro prewrap"><?= e(trim((string) $service['public_blurb'])) ?></p>
      <?php endif; ?>
    </div>

    <?php 

 ?>
    <?php
      $actions = [];
      try {
          $actions = cm_all('SELECT name, frequency FROM job_template_action
                             WHERE template_id = ? ORDER BY id', [(int) $service['id']]);
      } catch (Throwable $ex) {}
    ?>
    <?php if ($actions): ?>
      <h2 class="pub-h3">What is included</h2>
      <ul class="pub-list">
        <?php foreach ($actions as $a): ?>
          <li><?= e((string) $a['name']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <aside class="pub-buy">
    <?php 



 ?>
    <p class="buy-price"><?= e(cm_public_price($quote['cents'], $quote['freq'])) ?></p>
    <?php if ($quote['days'] > 0): ?>
      <p class="buy-when">Runs over <?= e(cm_public_duration($quote['days'])) ?></p>
    <?php endif; ?>

    <?php if (!$quote['available']): ?>
      <?php 

 ?>
      <div class="note note-warn">
        <b>Fully booked at the moment.</b>
        <?= e($quote['note']) ?>
        Send a message below and we will let you know the moment there is room.
      </div>
    <?php elseif ($quote['start'] !== ''): ?>
      <p class="pkg-start<?= $quote['delayed'] || $quote['extended'] ? ' pkg-start-late' : '' ?>">
        Could start <b><?= e(date('l j F', strtotime($quote['start']))) ?></b><br>
        <?php if ($quote['end'] !== ''): ?>
          finishing around <b><?= e(date('j F', strtotime($quote['end']))) ?></b>
        <?php endif; ?>
      </p>
      <?php if ($quote['note'] !== ''): ?>
        <?php 

 ?>
        <p class="muted small"><?= e($quote['note']) ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php 












 ?>
    <?php if ($quote['available']): ?>
      <?php 




 ?>
      <a class="btn" href="#book">Book it</a>
    <?php endif; ?>

    <p class="buy-note">No account needed, and nothing is charged here. Tell
    us where to reach you and we will confirm the dates before anything
    starts.</p>
  </aside>
</div>

<section class="pub-section" id="book">
  <h2 class="pub-h2">Book <?= e($name) ?></h2>

  <form method="post" action="<?= e(cm_url('portal/book.php')) ?>" class="pub-form">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="kind" value="booking">
    <input type="hidden" name="template_id" value="<?= (int) $service['id'] ?>">
    <?php if ($link !== null): ?>
      <input type="hidden" name="k" value="<?= e((string) ($_GET['k'] ?? '')) ?>">
    <?php endif; ?>
    <div class="hp-field" aria-hidden="true">
      <label for="company_website">Leave this empty</label>
      <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
    </div>

    <div class="form-row">
      <div class="field">
        <label class="lbl" for="b_name">Your name</label>
        <input type="text" id="b_name" name="name" class="f-full" maxlength="160" required
               value="<?= e($dv('name')) ?>">
      </div>
      <div class="field">
        <label class="lbl" for="b_email">Email</label>
        <input type="email" id="b_email" name="email" class="f-full" maxlength="190" required
               value="<?= e($dv('email')) ?>">
        <div class="hint">This is how we recognise you if you have used us before.</div>
      </div>
    </div>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="b_company">Business <span class="muted">(optional)</span></label>
        <input type="text" id="b_company" name="company" class="f-full" maxlength="160"
               value="<?= e($dv('company')) ?>">
      </div>
      <div class="field">
        <label class="lbl" for="b_phone">Phone <span class="muted">(optional)</span></label>
        <input type="text" id="b_phone" name="phone" class="f-full" maxlength="60"
               value="<?= e($dv('phone')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="b_from">Ideal start <span class="muted">(optional)</span></label>
        <input type="date" id="b_from" name="requested_from" class="f-full"
               min="<?= e(date('Y-m-d')) ?>">
        <div class="hint">Leave it blank for the earliest we can manage.</div>
      </div>
    </div>
    <div class="field">
      <label class="lbl" for="b_message">Anything we should know?</label>
      <textarea id="b_message" name="message" rows="4" class="f-full"><?= e($dv('message')) ?></textarea>
    </div>

    <?= cm_captcha_field() ?>
    <button class="btn" type="submit">Request this package</button>
    <p class="muted small mt-8">Nothing is charged here. We will confirm the
    dates and how you would like to pay.</p>
  </form>
</section>

<?php cm_public_foot(); ?>
