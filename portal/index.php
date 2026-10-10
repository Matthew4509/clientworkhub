<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/servicefinder.php';


require_once CM_LIB . '/captcha.php';















cm_public_require_enabled();












$arrival = cm_portal_arrival();
if ($arrival === 'none') {
    cm_public_404('You will need the link you were sent.');
}
if ($arrival === 'home') {



    $home = trim((string) cm_setting('public.home_url', ''));
    header('Location: ' . $home, true, 302);
    exit;
}

$featured = cm_public_services(true);
$all      = cm_public_services();



$basic = array_values(array_filter($all, static fn(array $t): bool => (int) $t['featured'] !== 1));



$anyService = ($featured !== [] || $basic !== []);

$intro = cm_public_setting('intro', '');
$sent  = isset($_GET['sent']);
$ref   = trim((string) ($_GET['ref'] ?? ''));


$draft = cm_public_draft_take();
$dv    = static fn(string $f): string => cm_public_draft_value($draft, $f);

cm_public_head(cm_public_business_name(), $intro);
?>

<?php 
 ?>
<?php if ($draft['error'] !== ''): ?>
  <div class="note note-error pub-flash">
    <b>That did not send.</b> <?= e($draft['error']) ?>
  </div>
<?php endif; ?>

<?php if ($sent): ?>
  <div class="note note-ok pub-flash">
    <b>Thank you — that has reached us.</b>
    <?php if ($ref !== '' && preg_match('/^[A-Z2-9]{8}$/', $ref)): ?>
      Your reference is <span class="mono"><?= e($ref) ?></span>.
    <?php endif; ?>
    We will be in touch shortly to confirm.
  </div>
<?php endif; ?>

<section class="pub-hero">
  <?php 



 ?>
  <?php if ($anyService): ?>
    <?php $svcCount = count($featured) + count($basic); ?>
    <p class="pub-eyebrow"><?= e((string) $svcCount) ?>
      service<?= $svcCount === 1 ? '' : 's' ?> &middot; enquire or book online</p>
  <?php endif; ?>
  <h1><?= e(cm_public_business_name()) ?></h1>
  <?php if ($intro !== ''): ?>
    <p class="pub-intro"><?= e($intro) ?></p>
  <?php endif; ?>
  <?php 











 ?>
  <p class="pub-cta">
    <?php if ($anyService): ?>
      <a class="btn" href="#services">See what we do</a>
    <?php endif; ?>
    <?php if (cm_finder_ready()): ?>
      <a class="btn btn-quiet" href="<?= e(cm_url('portal/find.php')) ?>"><?= e(cm_finder_heading()) ?></a>
    <?php endif; ?>
    <a class="btn<?= $anyService ? ' btn-quiet' : '' ?>" href="#contact">Get in touch</a>
  </p>
</section>

<?php if ($featured): ?>
  <section class="pub-section">
    <p class="pub-kicker">Popular</p>
    <h2 class="pub-h2">Where most people start</h2>
    <div class="pkg-grid">
      <?php foreach ($featured as $t): $q = cm_public_quote($t); ?>
        <article class="pkg pkg-featured">
          <h3><?= e(cm_public_service_name($t)) ?></h3>
          <p class="pkg-price mono"><?= e(cm_public_price($q['cents'], $q['freq'])) ?></p>
          <?php if ($q['days'] > 0): ?>
            <p class="pkg-when"><?= e(cm_public_duration($q['days'])) ?></p>
          <?php endif; ?>
          <?php if (trim((string) ($t['public_blurb'] ?? '')) !== ''): ?>
            <p class="pkg-blurb"><?= e(trim((string) $t['public_blurb'])) ?></p>
          <?php endif; ?>
          <?php if ($q['start'] !== ''): ?>
            <p class="pkg-start<?= $q['delayed'] || $q['extended'] ? ' pkg-start-late' : '' ?>">
              <?php if ($q['delayed'] || $q['extended']): ?>
                Next start <b><?= e(date('j M', strtotime($q['start']))) ?></b>
              <?php else: ?>
                Could start <b><?= e(date('j M', strtotime($q['start']))) ?></b>
              <?php endif; ?>
            </p>
          <?php endif; ?>
          <a class="btn" href="<?= e(cm_url('portal/service.php?id=' . (int) $t['id'])) ?>">Details &amp; book</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php 




 ?>
<?php if ($anyService): ?>
<section class="pub-section" id="services">
  <p class="pub-kicker"><?= $featured ? 'More' : 'Services' ?></p>
  <h2 class="pub-h2"><?= $featured ? 'Everything else we do' : 'What we do' ?></h2>
  <?php if (!$basic): ?>
    <p class="pub-lede">The packages above are what we offer at the moment &mdash;
    if you need something different, ask below and we will talk it through.</p>
  <?php else: ?>
    <?php 






 ?>
    <div class="svc-list">
      <?php foreach ($basic as $t): $q = cm_public_quote($t); ?>
        <div class="svc-row">
          <div class="svc-name-cell">
            <div class="svc-name"><?= e(cm_public_service_name($t)) ?></div>
            <?php if (trim((string) ($t['public_blurb'] ?? '')) !== ''): ?>
              <div class="svc-blurb"><?= e(trim((string) $t['public_blurb'])) ?></div>
            <?php endif; ?>
          </div>
          <div class="svc-when"><?= e(cm_public_duration($q['days'])) ?></div>
          <div class="svc-price"><?= e(cm_public_price($q['cents'], $q['freq'])) ?></div>
          <div class="svc-go">
            <a class="btn btn-quiet" href="<?= e(cm_url('portal/service.php?id=' . (int) $t['id'])) ?>">Details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php 



 ?>
<section class="pub-section" id="contact">
  <h2 class="pub-h2">Get in touch</h2>
  <?php 




 ?>
  <?php if ($anyService): ?>
    <p class="muted">Tell us what you need and when you need it. We will come
    back to you with a plan and a price.</p>
  <?php else: ?>
    <p class="muted">Tell us what you need and we will come back to you with a
    plan and a price. Every job here starts with a conversation.</p>
  <?php endif; ?>

  <?php 




 ?>
  <?php
    $cEmail = cm_public_setting('email', '');
    $cPhone = cm_public_setting('phone', '');
  ?>
  <div class="pub-contact">
    <?php if ($cEmail !== '' || $cPhone !== ''): ?>
      <div class="pub-contact-ways">
        <?php if ($cEmail !== ''): ?>
          <a class="pub-way" href="mailto:<?= e($cEmail) ?>">
            <span class="pub-way-k">Email</span>
            <span class="pub-way-v"><?= e($cEmail) ?></span>
          </a>
        <?php endif; ?>
        <?php if ($cPhone !== ''): ?>
          <a class="pub-way" href="tel:<?= e(preg_replace('/\s+/', '', $cPhone)) ?>">
            <span class="pub-way-k">Phone</span>
            <span class="pub-way-v"><?= e($cPhone) ?></span>
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div></div>
    <?php endif; ?>

    <div class="pub-form-card">
  <form method="post" action="<?= e(cm_url('portal/book.php')) ?>" class="pub-form">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="kind" value="enquiry">
    <?php 


 ?>
    <div class="hp-field" aria-hidden="true">
      <label for="company_website">Leave this empty</label>
      <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
    </div>

    <div class="form-row">
      <div class="field">
        <label class="lbl" for="c_name">Your name</label>
        <input type="text" id="c_name" name="name" class="f-full" maxlength="160" required
               value="<?= e($dv('name')) ?>">
      </div>
      <div class="field">
        <label class="lbl" for="c_email">Email</label>
        <input type="email" id="c_email" name="email" class="f-full" maxlength="190" required
               value="<?= e($dv('email')) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="field">
        <label class="lbl" for="c_company">Business <span class="muted">(optional)</span></label>
        <input type="text" id="c_company" name="company" class="f-full" maxlength="160"
               value="<?= e($dv('company')) ?>">
      </div>
      <div class="field">
        <label class="lbl" for="c_phone">Phone <span class="muted">(optional)</span></label>
        <input type="text" id="c_phone" name="phone" class="f-full" maxlength="60"
               value="<?= e($dv('phone')) ?>">
      </div>
    </div>
    <div class="field">
      <label class="lbl" for="c_message">What do you need?</label>
      <textarea id="c_message" name="message" rows="5" class="f-full" required><?= e($dv('message')) ?></textarea>
    </div>
    <?= cm_captcha_field() ?>
    <button class="btn" type="submit">Send it</button>
      </form>
    </div>
  </div>
</section>

<?php cm_public_foot(); ?>
