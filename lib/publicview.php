<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }




require_once CM_LIB . '/format.php';




require_once CM_LIB . '/migrate.php';
require_once CM_LIB . '/publicsite.php';

require_once CM_LIB . '/branding.php';














function cm_opened_ping_marker(): string
{
    return '<div hidden data-opened-ping data-csrf="' . e(cm_csrf_token()) . '"></div>';
}


function cm_is_opened_ping(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['opened_ping'] ?? '') === '1';
}

function cm_opened_ping_done(): never
{
    http_response_code(204);
    header('Cache-Control: no-store');
    exit;
}

function cm_public_head(string $title, string $description = ''): void
{














    cm_send_security_headers();

    $base = cm_base_url();
    $v = '?v=' . rawurlencode(cm_asset_stamp());
    $biz = cm_public_business_name();
    ?><!DOCTYPE html>
<html lang="en"<?= cm_theme_attr() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php 
 ?>
<title><?= $title === $biz ? e($biz) : e($title) . ' — ' . e($biz) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e($base) ?>/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="<?= e(cm_theme_bar()) ?>">
<link rel="stylesheet" href="<?= e($base) ?>/css/site.css<?= e($v) ?>">
<?php 
 ?>
<script defer src="<?= e($base) ?>/js/onboard.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/opened-ping.js<?= e($v) ?>"></script>
</head>
<body class="pub-body">
<header class="pub-head">
  <div class="wrap pub-nav">
    <?php 



 ?>
    <?php $logo = cm_logo_url(); ?>
    <?php 







 ?>
    <a class="pub-brand" href="<?= e(cm_portal_url()) ?>">
      <?php if ($logo !== ''): ?>
        <img class="pub-logo" src="<?= e($logo) ?>" alt="<?= e($biz) ?>">
      <?php else: ?>
        <?= e($biz) ?>
      <?php endif; ?>
    </a>
    <?php 



 ?>
    <?php 


 ?>
    <nav class="pub-links">
      <?php if (cm_public_has_services()): ?>
        <a href="<?= e(cm_portal_url()) ?>#services">Services</a>
      <?php endif; ?>
      <a class="pub-nav-cta" href="<?= e(cm_portal_url()) ?>#contact">Get in touch</a>
    </nav>
  </div>
</header>
<main class="wrap pub-main">
<?php
}

function cm_public_foot(): void
{
    $biz = cm_public_business_name();
    $email = cm_public_setting('email', '');
    $phone = cm_public_setting('phone', '');
    ?>
</main>
<?php 


 ?>
<footer class="pub-foot">
  <div class="wrap pub-foot-grid">
    <span class="pub-foot-name"><?= e($biz) ?></span>
    <?php if ($email !== '' || $phone !== ''): ?>
      <span class="pub-foot-contact">
        <?php if ($email !== ''): ?>
          <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
          <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </div>
</footer>
</body>
</html>
<?php
}







function cm_public_404(string $message = ''): never
{
    http_response_code(404);
    cm_public_head('Not found');
    ?>
    <div class="pub-narrow">
      <h1>Nothing here</h1>
      <p class="muted"><?= e($message !== '' ? $message : 'That page has moved or never existed.') ?></p>
      <p><a class="btn" href="<?= e(cm_portal_url()) ?>">Back to the start</a></p>
    </div>
    <?php
    cm_public_foot();
    exit;
}


function cm_public_require_enabled(): void
{
    if (!cm_public_enabled()) {



        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');









        cm_send_security_headers();
        exit("Not found\n");
    }
}


function cm_public_price(int $cents, string $freq): string
{
    if ($cents <= 0) return 'Price on request';
    $money = cm_money($cents);
    return match ($freq) {
        'weekly'  => $money . ' a week',
        'monthly' => $money . ' a month',
        'yearly'  => $money . ' a year',
        default   => $money,
    };
}


function cm_public_duration(int $days): string
{
    if ($days <= 0) return '';
    return $days === 1 ? 'one working day' : $days . ' working days';
}
