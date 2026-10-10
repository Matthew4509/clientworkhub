<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

















declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/invoices.php';







function cm_invoice_document(array $invoice, ?string $draftName = null): void
{
    $items    = cm_invoice_items((int) $invoice['id']);
    $payments = cm_invoice_payments((int) $invoice['id']);
    $tax      = cm_invoice_tax($invoice);
    $isDraft  = (string) $invoice['status'] === 'draft';
    $isPaid   = (string) $invoice['status'] === 'paid';
    $balance  = cm_invoice_balance_cents($invoice);
    $money    = static fn (int $c): string => cm_invoice_money($invoice, $c);



    $bizName = $isDraft
        ? (trim((string) cm_setting('business.legal_name', ''))
            ?: trim((string) cm_setting('public.business_name', '')))
        : (string) $invoice['biz_name'];
    $regLine = $isDraft
        ? cm_tax_registration_line()
        : trim((string) $invoice['tax_number_label'] . ' ' . (string) $invoice['tax_number']);
    ?>
<article class="doc-page">

  <header class="doc-head">
    <div class="doc-from">
      <h1><?= e($bizName !== '' ? $bizName : 'Your business') ?></h1>
      <?php $fromAddress = $isDraft ? '' : (string) $invoice['biz_address']; ?>
      <?php if ($fromAddress !== ''): ?><p class="doc-addr"><?= e($fromAddress) ?></p><?php endif; ?>
      <?php $fromContact = $isDraft ? '' : (string) $invoice['biz_contact']; ?>
      <?php if ($fromContact !== ''): ?><p class="doc-addr"><?= e($fromContact) ?></p><?php endif; ?>
      <?php if ($regLine !== ''): ?><p class="doc-addr doc-reg"><?= e($regLine) ?></p><?php endif; ?>
    </div>
    <div class="doc-meta">
      <?php 
 ?>
      <p class="doc-kind"><?= $isPaid ? 'RECEIPT' : 'INVOICE' ?></p>
      <?php if (!$isDraft): ?>
        <p class="doc-num mono"><?= e((string) $invoice['number']) ?></p>
        <dl class="doc-dates">
          <dt>Issued</dt><dd><?= e(date('j M Y', strtotime((string) $invoice['issued_on']))) ?></dd>
          <?php if (!$isPaid && $invoice['due_on']): ?>
            <dt>Due</dt><dd><?= e(date('j M Y', strtotime((string) $invoice['due_on']))) ?></dd>
          <?php endif; ?>
          <?php if ($isPaid && $payments): ?>
            <dt>Paid</dt><dd><?= e(date('j M Y', strtotime((string) end($payments)['paid_on']))) ?></dd>
          <?php endif; ?>
        </dl>
      <?php endif; ?>
    </div>
  </header>

  <section class="doc-to">
    <h2 class="doc-label">Billed to</h2>
    <p class="doc-name"><?= e($isDraft
        ? (string) ($draftName ?? 'Unknown client')
        : (string) $invoice['bill_to_name']) ?></p>
    <?php if (!$isDraft && (string) $invoice['bill_to_address'] !== ''): ?>
      <p class="doc-addr"><?= e((string) $invoice['bill_to_address']) ?></p>
    <?php endif; ?>
    <?php if (!$isDraft && (string) $invoice['bill_to_email'] !== ''): ?>
      <p class="doc-addr"><?= e((string) $invoice['bill_to_email']) ?></p>
    <?php endif; ?>
  </section>

  <table class="doc-lines">
    <thead>
      <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= e((string) $it['description']) ?><?php


            if (cm_tax_charges($tax) && (int) $it['taxable'] === 0): ?>
            <span class="doc-exempt">no <?= e($tax['name']) ?></span><?php endif; ?></td>
          <td class="num mono"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2), '0'), '.')) ?></td>
          <td class="num mono"><?= e($money((int) $it['unit_cents'])) ?></td>
          <td class="num mono"><?= e($money((int) $it['line_cents'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="4" class="doc-empty">Nothing on this invoice yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <table class="doc-totals">
    <?php if (cm_tax_charges($tax)): ?>
      <?php 



 ?>
      <tr><th>Subtotal</th><td class="num mono"><?= e($money((int) $invoice['subtotal_cents'])) ?></td></tr>
      <tr>
        <th><?= e($tax['name']) ?>
          <?php $rate = rtrim(rtrim(number_format((float) $tax['rate'], 2, '.', ''), '0'), '.'); ?>
          <span class="doc-rate"><?= e($rate) ?>%<?= $tax['mode'] === 'inclusive' ? ' included' : '' ?></span>
        </th>
        <td class="num mono"><?= e($money((int) $invoice['tax_cents'])) ?></td>
      </tr>
    <?php endif; ?>
    <tr class="doc-total">
      <th>Total<?php if ((string) $invoice['currency'] !== ''): ?>
        <span class="doc-rate"><?= e((string) $invoice['currency']) ?></span><?php endif; ?></th>
      <td class="num mono"><?= e($money((int) $invoice['total_cents'])) ?></td>
    </tr>
    <?php if ($payments): ?>
      <?php foreach ($payments as $p): ?>
        <tr class="doc-paid-line">
          <th><?= e(cm_payment_methods()[(string) $p['method']] ?? 'Payment') ?>
            <span class="doc-rate"><?= e(date('j M Y', strtotime((string) $p['paid_on']))) ?><?php
              if ((string) $p['reference'] !== ''): ?> &middot; <?= e((string) $p['reference']) ?><?php endif; ?></span>
          </th>
          <td class="num mono">&minus;<?= e($money((int) $p['amount_cents'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr class="doc-total">
        <th><?= $balance <= 0 ? 'Paid in full' : 'Still due' ?></th>
        <td class="num mono"><?= e($money(max(0, $balance))) ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <?php if ($isPaid): ?>
    <p class="doc-stamp">PAID</p>
  <?php endif; ?>

  <footer class="doc-foot">
    <?php $pay = $isDraft ? '' : (string) $invoice['pay_details']; ?>
    <?php if ($pay !== '' && !$isPaid): ?>
      <p class="doc-pay"><b>Pay by transfer</b><br><?= e($pay) ?></p>
    <?php endif; ?>
    <?php $foot = $isDraft ? trim((string) cm_setting('invoice.footer', '')) : (string) $invoice['footer']; ?>
    <?php if ($foot !== ''): ?><p class="doc-note"><?= e($foot) ?></p><?php endif; ?>
    <?php if ((string) ($invoice['notes'] ?? '') !== ''): ?>
      <p class="doc-note"><?= e((string) $invoice['notes']) ?></p>
    <?php endif; ?>
  </footer>

</article>
    <?php
}
