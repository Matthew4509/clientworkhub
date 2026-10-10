<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




















declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


function cm_money(int $cents): string
{







    $symbol = function_exists('cm_currency') ? cm_currency()['symbol'] : '$';
    return cm_money_format($symbol, $cents);
}













function cm_bytes_label(int $bytes): string
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return number_format($bytes / 1024, 0) . ' KB';
    return $bytes . ' bytes';
}


function cm_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $n    = (int) $value;
    return match ($unit) {
        'g'     => $n * 1073741824,
        'm'     => $n * 1048576,
        'k'     => $n * 1024,
        default => $n,
    };
}







function cm_upload_cap(): int
{
    $caps = array_filter([cm_ini_bytes((string) ini_get('upload_max_filesize')),
                          cm_ini_bytes((string) ini_get('post_max_size'))], static fn(int $n): bool => $n > 0);
    return $caps ? (int) min($caps) : 0;
}







function cm_post_too_large(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || $_POST || $_FILES) return false;
    $sent = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $cap  = cm_ini_bytes((string) ini_get('post_max_size'));
    return $sent > 0 && $cap > 0 && $sent > $cap;
}
















function cm_money_format(string $symbol, int $cents): string
{
    $abs   = abs($cents);
    $shown = $symbol . number_format($abs / 100, $abs % 100 === 0 ? 0 : 2);
    return $cents < 0 ? '-' . $shown : $shown;
}




























const CM_TEXT_MAX_BYTES = 60000;

function cm_text_fit(string $value): string
{
    return strlen($value) <= CM_TEXT_MAX_BYTES
        ? $value
        : mb_strcut($value, 0, CM_TEXT_MAX_BYTES, 'UTF-8');
}


function cm_minutes_label(int $minutes): string
{
    if ($minutes < 60) return $minutes . 'm';
    return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
}

















function cm_web_address(string $raw): array
{
    $url = trim($raw);
    if ($url === '') return ['url' => '', 'error' => ''];
    if (preg_match('#^https?://#i', $url)) {
        return ['url' => $url, 'error' => ''];
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
        return ['url' => '', 'error' => 'That is not a web address — it must be http:// or https://.'];
    }
    if (preg_match('#^[^\s/?\#]+\.[^\s/?\#]{2,}([/?\#]\S*)?$#u', $url)) {
        return ['url' => 'https://' . $url, 'error' => ''];
    }
    return ['url' => '', 'error' => 'That does not look like a web address — "example.com" or "https://example.com".'];
}














function cm_safe_href(string $url): string
{
    $url = trim($url);
    return preg_match('#^(https?://|mailto:|tel:|sms:)#i', $url) ? $url : 'about:blank';
}

function cm_billing_frequencies(): array
{
    return [
        'oneoff'  => 'One-off',
        'weekly'  => 'Weekly',
        'monthly' => 'Monthly',
        'yearly'  => 'Yearly',
    ];
}
