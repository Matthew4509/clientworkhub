<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/servicefinder.php';

cm_public_require_enabled();



if (!cm_finder_ready()) {
    cm_public_404('There is nothing to help you choose from just yet.');
}

$questions = cm_finder_questions();
$answered  = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$picked    = [];
$matches   = [];

if ($answered) {


    foreach ((array) ($_POST['answer'] ?? []) as $raw) {
        $id = (int) $raw;
        if ($id > 0) $picked[] = $id;
    }
    $matches = cm_finder_match($picked);
}

cm_public_head(cm_finder_heading(), cm_finder_intro());
?>

<section class="pub-hero">
  <h1><?= e(cm_finder_heading()) ?></h1>
  <p class="pub-intro"><?= e(cm_finder_intro()) ?></p>
</section>

<?php if (!$answered): ?>

  <section class="pub-section">
    <form method="post" action="<?= e(cm_url('portal/find.php')) ?>" class="stack">
      <?php foreach ($questions as $q): ?>
        <?php if (!$q['options']) continue; ?>
        <fieldset class="pub-q">
          <legend><?= e((string) $q['prompt']) ?></legend>
          <?php if (trim((string) $q['hint']) !== ''): ?>
            <p class="pub-qhint"><?= e((string) $q['hint']) ?></p>
          <?php endif; ?>
          <?php foreach ($q['options'] as $o): ?>
            <label class="pub-a">
              <input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="<?= (int) $o['id'] ?>">
              <span><?= e((string) $o['label']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php 
 ?>
          <label class="pub-a">
            <input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="0" checked>
            <span class="muted">I&rsquo;m not sure</span>
          </label>
        </fieldset>
      <?php endforeach; ?>

      <p class="pub-cta">
        <button class="btn" type="submit">Show me</button>
        <a class="btn btn-quiet" href="<?= e(cm_url('portal/')) ?>">Just show me everything</a>
      </p>
    </form>
  </section>

<?php else: ?>

  <?php

  $best = $matches ? array_slice($matches, 0, 1) : [];
  $also = $matches ? array_slice($matches, 1, 3) : [];
  ?>

  <section class="pub-section">
    <?php if ($best): ?>
      <?php $t = $best[0]['service']; $q = cm_public_quote($t); ?>
      <h2 class="pub-h2">This looks like the one</h2>
      <article class="pkg pkg-featured">
        <h3><?= e(cm_public_service_name($t)) ?></h3>
        <p class="pkg-price mono"><?= e(cm_public_price($q['cents'], $q['freq'])) ?></p>
        <?php if ($q['days'] > 0): ?>
          <p class="pkg-when"><?= e(cm_public_duration($q['days'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($t['public_blurb'] ?? '')) !== ''): ?>
          <p class="pkg-blurb"><?= e(trim((string) $t['public_blurb'])) ?></p>
        <?php endif; ?>
        <p><a class="btn" href="<?= e(cm_url('portal/service.php?id=' . (int) $t['id'])) ?>">Look at this one</a></p>
      </article>
    <?php else: ?>
      <h2 class="pub-h2">Here is everything we do</h2>
      <p class="pub-intro">Your answers did not point at one thing in particular, so
        nothing has been ruled out.</p>
    <?php endif; ?>

    <?php $rest = $best ? $also : array_map(static fn($t) => ['service' => $t], cm_public_services()); ?>
    <?php if ($rest): ?>
      <h2 class="pub-h2"><?= $best ? 'Also worth a look' : 'All services' ?></h2>
      <div class="pkg-grid">
        <?php foreach ($rest as $row): $t = $row['service']; $q = cm_public_quote($t); ?>
          <article class="pkg">
            <h3><?= e(cm_public_service_name($t)) ?></h3>
            <p class="pkg-price mono"><?= e(cm_public_price($q['cents'], $q['freq'])) ?></p>
            <?php if (trim((string) ($t['public_blurb'] ?? '')) !== ''): ?>
              <p class="pkg-blurb"><?= e(trim((string) $t['public_blurb'])) ?></p>
            <?php endif; ?>
            <p><a class="btn btn-quiet"
                  href="<?= e(cm_url('portal/service.php?id=' . (int) $t['id'])) ?>">Look at this one</a></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="pub-cta">
      <a class="btn btn-quiet" href="<?= e(cm_url('portal/find.php')) ?>">Start again</a>
      <a class="btn btn-quiet" href="<?= e(cm_url('portal/')) ?>">See everything</a>
    </p>
  </section>

<?php endif; ?>

<?php cm_public_foot(); ?>
