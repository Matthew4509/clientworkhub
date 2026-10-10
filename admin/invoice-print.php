<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/invoicedoc.php';

$admin = cm_require_admin();

$invoice = cm_invoice((int) ($_GET['id'] ?? 0));
if (!$invoice) { http_response_code(404); exit('Not found'); }

$isDraft = (string) $invoice['status'] === 'draft';
$isPaid  = (string) $invoice['status'] === 'paid';
$isVoid  = (string) $invoice['status'] === 'void';




$draftName = null;
if ($isDraft) {
    $c = cm_row('SELECT * FROM client WHERE id = ?', [(int) $invoice['client_id']]);
    $draftName = $c ? cm_client_display_name($c) : 'Unknown client';
}

cm_send_security_headers();
$base = cm_base_url();
$v    = '?v=' . rawurlencode(cm_asset_stamp());
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($isDraft ? 'Draft invoice' : (($isPaid ? 'Receipt ' : 'Invoice ') . (string) $invoice['number'])) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e($base) ?>/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($base) ?>/css/print.css<?= e($v) ?>">
</head>
<body class="doc">

<div class="doc-actions">
  <a class="doc-back" href="<?= e(cm_url('admin/invoice.php?id=' . (int) $invoice['id'])) ?>">&larr; Back to the invoice</a>
  <span class="doc-hint">Print, or save as PDF, with your browser&rsquo;s own print command.</span>
</div>

<?php if ($isDraft): ?>
  <p class="doc-banner">DRAFT &mdash; not issued, has no number, and the client cannot see it.</p>
<?php elseif ($isVoid): ?>
  <p class="doc-banner doc-banner-void">VOID &mdash; cancelled. It keeps its number so the sequence stays whole.</p>
<?php endif; ?>

<?php cm_invoice_document($invoice, $draftName); ?>

</body>
</html>
