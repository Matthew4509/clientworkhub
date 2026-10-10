<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0









































declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/unsubscribe.php';

$clientId = (int) ($_GET['c'] ?? $_POST['c'] ?? 0);
$token    = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$valid    = cm_unsub_verify($clientId, $token);




if ($valid) {
    try { $valid = cm_val('SELECT id FROM client WHERE id = ?', [$clientId]) !== null; }
    catch (Throwable $ex) { $valid = false; }
}

$done = false;
if ($valid && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();



    cm_unsub_apply($clientId, 'link');
    $done = true;
}

$state = $valid ? cm_unsub_state($clientId) : null;
$off   = $done || ($state !== null && $state['unsubscribed']);

if (!$valid) http_response_code(404);
cm_send_security_headers();

$base = cm_base_url();
$v    = '?v=' . rawurlencode(cm_asset_stamp());
$biz  = cm_mail_from_name();
?><!DOCTYPE html>
<html lang="en"<?= cm_theme_attr() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email preferences — <?= e($biz) ?></title>
<?php  ?>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($base) ?>/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($base) ?>/css/site.css<?= e($v) ?>">
</head>
<body class="pub-body">
<main class="wrap pub-main pub-narrow">

<?php if (!$valid): ?>

  <div class="pub-hero pub-hero-tight">
    <h1>This link is not valid</h1>
    <p class="pub-intro">It may have been broken across two lines by your email
    program, or it may simply be too old.</p>
  </div>
  <p class="muted">Reply to any email from <?= e($biz) ?> and ask us to stop —
  that works just as well, and somebody will read it.</p>

<?php elseif ($off): ?>

  <div class="pub-hero pub-hero-tight">
    <h1>Done — no more emails</h1>
  </div>
  <div class="note note-ok">
    <b>You will not receive any further emails from <?= e($biz) ?>.</b>
    That covers everything we send, including work summaries and invoices.
  </div>
  <p class="muted">If you did not mean to do this, or you want them back
  later, give us a call and we will turn them on again. It cannot be undone
  from this page on purpose — so that nobody but you can start them again.</p>

<?php else: ?>

  <div class="pub-hero pub-hero-tight">
    <h1>Stop emails from <?= e($biz) ?>?</h1>
    <p class="pub-intro">One click and we will not email you again.</p>
  </div>

  <?php 

 ?>
  <div class="note note-warn">
    <b>This stops every email we send you</b> — work summaries and invoices
    included, not just the occasional catch-up.
  </div>

  <form method="post">
    <?= cm_csrf_field() ?>
    <input type="hidden" name="c" value="<?= (int) $clientId ?>">
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <button class="btn" type="submit">Yes, stop all emails</button>
  </form>

  <p class="muted small mt-16">Changed your mind? Close this page — nothing
  happens until you press the button. To start emails again afterwards you
  would have to call us, so we know it is really you.</p>

<?php endif; ?>

</main>
</body>
</html>
