<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





















declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/publicview.php';
require_once CM_LIB . '/invoicedoc.php';
require_once CM_LIB . '/invoicepdf.php';
require_once CM_LIB . '/payments.php';

cm_public_require_enabled();

$link = cm_link_resolve((string) ($_GET['k'] ?? ''));
if ($link === null) {
    cm_public_404('That link has expired or is no longer in use. '
                . 'Get in touch and we will send you a new one.');
}

$invoice = cm_invoice((int) ($_GET['i'] ?? 0));




if ($invoice === null
    || (int) $invoice['client_id'] !== (int) $link['client_id']
    || (string) $invoice['status'] === 'draft') {
    cm_public_404('That document is not available.');
}

$isPaid = (string) $invoice['status'] === 'paid';
$base   = cm_base_url();
$v      = '?v=' . rawurlencode(cm_asset_stamp());

cm_send_security_headers();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(($isPaid ? 'Receipt ' : 'Invoice ') . (string) $invoice['number']) ?></title>
<?php  ?>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($base) ?>/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($base) ?>/css/print.css<?= e($v) ?>">
</head>
<body class="doc">

<div class="doc-actions">
  <a class="doc-back" href="<?= e(cm_url('portal/my.php?k=' . rawurlencode((string) ($_GET['k'] ?? '')))) ?>">&larr; Back</a>
  <?php 


 ?>
  <button class="doc-pdf" type="button" data-invoice-pdf>Download PDF</button>
  <span class="doc-hint">Or print it with your browser&rsquo;s own print command.</span>
</div>

<?php 







      $payProblem = cm_pay_invoice_problem($invoice);
      $payProviders = $payProblem === '' ? cm_pay_providers() : [];
      $payLinks = [];
      foreach ($payProviders as $via => $pr) {
          $payLinks[] = ['label' => $pr['label'] . ' (' . $pr['via'] . ')',
                         'url' => cm_url('portal/pay.php?k=' . rawurlencode((string) ($_GET['k'] ?? '')) . '&i=' . (int) $invoice['id'] . '&via=' . $via)];
      }
?>
<?php if (isset($_GET['paid'])): ?>
  <p class="doc-banner doc-banner-ok">Thank you &mdash; your payment has been received<?= $isPaid ? ', and this invoice is paid in full' : '' ?>.</p>
<?php elseif (isset($_GET['unsettled'])): ?>
  <p class="doc-banner doc-banner-warn">We could not confirm that payment just now. If your card or PayPal account was
  charged, it will show here once the provider confirms it &mdash; nothing to do on your side. If it was not, you can try again below.</p>
<?php elseif (isset($_GET['cancelled'])): ?>
  <p class="doc-banner doc-banner-warn">The payment was cancelled before it went through. Nothing has been charged.</p>
<?php endif; ?>
<?php if ($payLinks): ?>
  <div class="doc-pay">
    <span class="doc-pay-l">Pay this invoice online:</span>
    <?php foreach ($payLinks as $pl): ?>
      <a class="doc-pay-btn" href="<?= e($pl['url']) ?>"><?= e($pl['label']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php cm_invoice_document($invoice); ?>

<script type="application/json" id="invoice-pdf-data"><?= json_encode(cm_invoice_pdf_data($invoice, null, $payLinks), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<script defer src="<?= e($base) ?>/js/jspdf.umd.min.js<?= e($v) ?>"></script>
<script defer src="<?= e($base) ?>/js/invoice-pdf.js<?= e($v) ?>"></script>

</body>
</html>
