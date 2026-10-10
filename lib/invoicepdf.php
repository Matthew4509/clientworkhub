<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




















declare(strict_types=1);
if (!defined('CM_ROOT')) { http_response_code(404); exit; }
require_once CM_LIB . '/invoices.php';
require_once CM_LIB . '/branding.php';







function cm_invoice_pdf_data(array $invoice, ?string $draftName = null, array $payLinks = []): array
{
    $items    = cm_invoice_items((int) $invoice['id']);
    $payments = cm_invoice_payments((int) $invoice['id']);
    $tax      = cm_invoice_tax($invoice);
    $isDraft  = (string) $invoice['status'] === 'draft';
    $isPaid   = (string) $invoice['status'] === 'paid';
    $isVoid   = (string) $invoice['status'] === 'void';
    $balance  = cm_invoice_balance_cents($invoice);
    $money    = static fn (int $c): string => cm_invoice_money($invoice, $c);

    $bizName = $isDraft
        ? (trim((string) cm_setting('business.legal_name', '')) ?: cm_business_display_name())
        : ((string) $invoice['biz_name'] !== '' ? (string) $invoice['biz_name'] : cm_business_display_name());
    $bizAddress = $isDraft ? '' : (string) $invoice['biz_address'];
    $bizContact = $isDraft ? '' : (string) $invoice['biz_contact'];
    $regLine = $isDraft
        ? cm_tax_registration_line()
        : trim((string) $invoice['tax_number_label'] . ' ' . (string) $invoice['tax_number']);

    $logo = ''; $logoType = 'PNG'; $logoSize = null;
    $logoFile = cm_logo_file();
    if ($logoFile !== null && @filesize($logoFile) < 800000) {
        $raw = @file_get_contents($logoFile);
        if ($raw !== false) {
            $logo = 'data:' . cm_logo_mime() . ';base64,' . base64_encode($raw);
            $logoType = cm_logo_mime() === 'image/jpeg' ? 'JPEG' : 'PNG';
            $logoSize = cm_logo_size();
        }
    }


    if ($isVoid)        { $status = 'VOID'; }
    elseif ($isDraft)   { $status = 'DRAFT'; }
    elseif ($isPaid)    { $status = 'PAID'; }
    elseif ($invoice['due_on'] && (string) $invoice['due_on'] < date('Y-m-d')) { $status = 'OVERDUE'; }
    else                { $status = 'DUE'; }

    $rate = rtrim(rtrim(number_format((float) $tax['rate'], 2, '.', ''), '0'), '.');
    $totals = [];
    if (cm_tax_charges($tax)) {
        $totals[] = ['Subtotal' . ($tax['mode'] === 'inclusive' ? '' : ' (ex ' . $tax['name'] . ')'), $money((int) $invoice['subtotal_cents'])];
        $totals[] = [$tax['name'] . ' ' . $rate . '%' . ($tax['mode'] === 'inclusive' ? ' (included)' : ''), $money((int) $invoice['tax_cents'])];
    }
    $totals[] = ['Total' . (cm_tax_charges($tax) && $tax['mode'] !== 'inclusive' ? ' (inc ' . $tax['name'] . ')' : '')
                  . ((string) $invoice['currency'] !== '' ? ' ' . (string) $invoice['currency'] : ''),
                 $money((int) $invoice['total_cents']), 'total'];
    foreach ($payments as $p) {
        $totals[] = [(cm_payment_methods()[(string) $p['method']] ?? 'Payment') . ' · '
                     . date('j M Y', strtotime((string) $p['paid_on']))
                     . ((string) $p['reference'] !== '' ? ' · ' . (string) $p['reference'] : ''),
                     '-' . $money((int) $p['amount_cents'])];
    }
    if ($payments || $isPaid) {
        $totals[] = [$balance <= 0 ? 'Total paid' : 'Amount due', $money($balance <= 0 ? (int) $invoice['total_cents'] : $balance), $balance <= 0 ? 'paid' : 'due'];
    }

    $facts = [];
    if (!$isDraft) $facts[] = [($isPaid ? 'Receipt' : 'Invoice') . ' no.', (string) $invoice['number']];
    $facts[] = ['Date', $isDraft ? date('j M Y') : date('j M Y', strtotime((string) $invoice['issued_on']))];
    if (!$isDraft && !$isPaid && $invoice['due_on']) $facts[] = ['Due date', date('j M Y', strtotime((string) $invoice['due_on']))];
    if ($isPaid && $payments) $facts[] = ['Paid', date('j M Y', strtotime((string) end($payments)['paid_on']))];

    $billTo = array_values(array_filter([
        $isDraft ? (string) ($draftName ?? '') : (string) $invoice['bill_to_name'],
        $isDraft ? '' : (string) $invoice['bill_to_address'],
        $isDraft ? '' : (string) $invoice['bill_to_email'],
    ], static fn($l) => trim($l) !== ''));

    return [
        'business' => [
            'name' => $bizName, 'address' => $bizAddress, 'contact' => $bizContact, 'reg' => $regLine,
            'logo' => $logo, 'logoType' => $logoType, 'logoW' => $logoSize['w'] ?? 0, 'logoH' => $logoSize['h'] ?? 0,
        ],
        'doc' => [
            'title'    => $isPaid ? 'RECEIPT' : 'INVOICE',
            'status'   => $status,
            'facts'    => $facts,
            'headline' => $isVoid ? ['Void', ''] : ($isDraft ? ['Total', $money((int) $invoice['total_cents'])]
                          : ($balance <= 0 ? ['Total paid', $money((int) $invoice['total_cents'])] : ['Amount due', $money($balance)])),
            'billTo'   => $billTo,
            'items'    => array_map(static fn(array $it): array => [
                'description' => (string) $it['description'] . (cm_tax_charges($tax) && (int) $it['taxable'] === 0 ? ' (no ' . $tax['name'] . ')' : ''),
                'qty'  => rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ''), '0'), '.'),
                'unit' => $money((int) $it['unit_cents']),
                'amount' => $money((int) $it['line_cents']),
            ], $items),
            'totals'   => $totals,
            'payDetails' => $isPaid || $isVoid ? '' : (string) $invoice['pay_details'],
            'payLinks' => $payLinks,
            'notes'    => (string) $invoice['notes'],
            'footer'   => (string) $invoice['footer'],
            'filename' => ($isPaid ? 'receipt-' : 'invoice-') . preg_replace('/[^a-z0-9]+/i', '-', (string) ($invoice['number'] ?: 'draft')) . '.pdf',
        ],
    ];
}
