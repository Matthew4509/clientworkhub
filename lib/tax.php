<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0





























declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }








function cm_tax_modes(): array
{
    return [
        'none' => [
            'label' => 'Not registered for tax',
            'blurb' => 'No tax line on anything. The right setting if you are under the '
                     . 'registration threshold — you should not be showing clients 0%.',
        ],
        'exclusive' => [
            'label' => 'Tax applied on top',
            'blurb' => 'Your prices are before tax. Tax is added to the invoice and the '
                     . 'client pays more than the number on your service list.',
        ],
        'inclusive' => [
            'label' => 'Show clients a price inclusive of tax',
            'blurb' => 'Your prices already contain the tax. The invoice shows how much of '
                     . 'the total was tax; the client pays the number on your service list.',
        ],
    ];
}


function cm_currencies(): array
{
    return [
        'AUD' => ['symbol' => '$',  'label' => 'Australian dollar (AUD)'],
        'NZD' => ['symbol' => '$',  'label' => 'New Zealand dollar (NZD)'],
        'USD' => ['symbol' => '$',  'label' => 'US dollar (USD)'],
        'GBP' => ['symbol' => '£',  'label' => 'Pound sterling (GBP)'],
        'EUR' => ['symbol' => '€',  'label' => 'Euro (EUR)'],
        'CAD' => ['symbol' => '$',  'label' => 'Canadian dollar (CAD)'],
    ];
}






function cm_currency_choices(): array
{
    $out = [];
    foreach (cm_currencies() as $code => $row) {
        $out[$code] = ['label' => $row['label'], 'blurb' => ''];
    }
    return $out;
}


function cm_currency(): array
{
    $all  = cm_currencies();
    $code = (string) cm_setting('invoice.currency', 'AUD');
    $row  = $all[$code] ?? $all['AUD'];
    return ['code' => isset($all[$code]) ? $code : 'AUD', 'symbol' => $row['symbol']];
}









function cm_tax_settings(): array
{
    $mode = (string) cm_setting('tax.mode', 'none');
    if (!isset(cm_tax_modes()[$mode])) $mode = 'none';

    $rate = (float) cm_setting('tax.rate', '0');
    if ($rate < 0) $rate = 0.0;

    return [
        'mode'         => $mode,
        'rate'         => $rate,
        'name'         => trim((string) cm_setting('tax.name', 'Tax')) ?: 'Tax',
        'number_label' => trim((string) cm_setting('tax.number_label', '')),
        'number'       => trim((string) cm_setting('tax.number', '')),
        'currency'     => cm_currency()['code'],
    ];
}


function cm_tax_charges(?array $tax = null): bool
{
    $tax = $tax ?? cm_tax_settings();
    return $tax['mode'] !== 'none' && $tax['rate'] > 0;
}




















function cm_tax_totals(int $sum, int $taxable, ?array $tax = null): array
{
    $tax = $tax ?? cm_tax_settings();

    if ($taxable > $sum) $taxable = $sum;
    if ($taxable < 0)    $taxable = 0;

    if (!cm_tax_charges($tax)) {
        return ['subtotal' => $sum, 'tax' => 0, 'total' => $sum];
    }

    $rate = (float) $tax['rate'];

    if ($tax['mode'] === 'exclusive') {
        $amount = (int) round($taxable * $rate / 100);
        return ['subtotal' => $sum, 'tax' => $amount, 'total' => $sum + $amount];
    }

    $amount = (int) round($taxable * $rate / (100 + $rate));
    return ['subtotal' => $sum - $amount, 'tax' => $amount, 'total' => $sum];
}










function cm_tax_price_note(?array $tax = null): string
{
    $tax = $tax ?? cm_tax_settings();
    if (!cm_tax_charges($tax)) return '';

    $rate = rtrim(rtrim(number_format($tax['rate'], 2, '.', ''), '0'), '.');

    return $tax['mode'] === 'exclusive'
        ? sprintf('Before %s. %s%% is added on the invoice.', $tax['name'], $rate)
        : sprintf('Includes %s. %s%% of this is %s.', $tax['name'], $rate, $tax['name']);
}






function cm_tax_registration_line(?array $tax = null): string
{
    $tax = $tax ?? cm_tax_settings();
    if ($tax['number'] === '') return '';
    return trim($tax['number_label'] . ' ' . $tax['number']);
}
