<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0




































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }


function cm_link_expiry_choices(): array
{
    return [3 => 'Expire in 3 days', 7 => 'Expire in 7 days', 30 => 'Expire in 30 days'];
}


const CM_LINK_EXPIRY_DEFAULT_DAYS = 30;










function cm_link_expiry_days($raw, bool $allowNever = false): ?int
{
    $v = is_string($raw) || is_int($raw) ? trim((string) $raw) : '';
    if ($allowNever && ($v === '' || $v === '0' || strtolower($v) === 'never')) return null;
    $n = (int) $v;
    return isset(cm_link_expiry_choices()[$n]) ? $n : CM_LINK_EXPIRY_DEFAULT_DAYS;
}





function cm_link_expires_at(?int $days): ?string
{
    if ($days === null || $days <= 0) return null;
    return date('Y-m-d H:i:s', strtotime('today +' . $days . ' days 23:59:59'));
}


function cm_link_expired(?string $expiresAt): bool
{
    return $expiresAt !== null && $expiresAt !== '' && $expiresAt < cm_now();
}





function cm_link_expiry_label(?string $expiresAt): string
{
    if ($expiresAt === null || $expiresAt === '') return 'No expiry';
    $when = date('j M Y', (int) strtotime($expiresAt));
    return cm_link_expired($expiresAt) ? 'Expired ' . $when : 'Expires ' . $when;
}















function cm_link_expiry_radios(string $name = 'expiry_days', ?int $selected = -1,
                               bool $allowNever = false, string $neverLabel = 'No expiry'): string
{
    if ($selected === -1) $selected = CM_LINK_EXPIRY_DEFAULT_DAYS;
    $out = '<div class="expiry-choices" role="group" aria-label="When the link expires">';
    foreach (cm_link_expiry_choices() as $days => $label) {
        $out .= '<label class="check"><input type="radio" name="' . e($name) . '" value="' . (int) $days . '"'
              . ($selected === $days ? ' checked' : '') . '> ' . e($label) . '</label>';
    }
    if ($allowNever) {
        $out .= '<label class="check"><input type="radio" name="' . e($name) . '" value="0"'
              . ($selected === null ? ' checked' : '') . '> ' . e($neverLabel) . '</label>';
    }
    return $out . '</div>';
}
