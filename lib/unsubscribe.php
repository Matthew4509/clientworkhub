<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0

















































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }





require_once CM_LIB . '/settings.php';









function cm_unsub_token(int $clientId): string
{
    return substr(hash_hmac('sha256', 'cm-unsub-v1|' . $clientId, cm_app_key()), 0, 32);
}


function cm_unsub_verify(int $clientId, string $token): bool
{
    if ($clientId < 1 || !preg_match('/^[a-f0-9]{32}$/', $token)) return false;
    try {
        return hash_equals(cm_unsub_token($clientId), $token);
    } catch (Throwable $ex) {
        return false;
    }
}


function cm_unsub_url(int $clientId): string
{
    return cm_url('portal/unsubscribe.php?c=' . $clientId . '&t=' . cm_unsub_token($clientId));
}











function cm_unsub_footer(int $clientId): string
{
    if ($clientId < 1) return '';
    try {
        $url = cm_unsub_url($clientId);
    } catch (Throwable $ex) {
        error_log('unsubscribe: no application key — client mail refused: ' . $ex->getMessage());
        return '';
    }

    return "\n\n-- \n"
         . "Don't want emails from us? Unsubscribe here:\n"
         . "  " . $url . "\n\n"
         . "That stops every email we send you — work summaries and invoices\n"
         . "included — until you call us and ask us to turn them back on.\n";
}









function cm_client_is_unsubscribed(int|array $client): bool
{
    if (is_array($client)) {
        if (array_key_exists('unsubscribed_at', $client)) {
            return ($client['unsubscribed_at'] ?? null) !== null;
        }
        $client = (int) ($client['id'] ?? 0);
    }
    if ($client < 1) return false;

    try {
        return cm_val('SELECT unsubscribed_at FROM client WHERE id = ?', [$client]) !== null;
    } catch (Throwable $ex) {



        return false;
    }
}


function cm_unsub_state(int $clientId): ?array
{
    try {
        $r = cm_row('SELECT unsubscribed_at, unsubscribed_how, resubscribed_at, resubscribed_by
                       FROM client WHERE id = ?', [$clientId]);
    } catch (Throwable $ex) {
        return null;
    }
    if ($r === null) return null;
    return [
        'unsubscribed'     => ($r['unsubscribed_at'] ?? null) !== null,
        'unsubscribed_at'  => $r['unsubscribed_at'] ?? null,
        'how'              => (string) ($r['unsubscribed_how'] ?? ''),
        'resubscribed_at'  => $r['resubscribed_at'] ?? null,
        'resubscribed_by'  => (string) ($r['resubscribed_by'] ?? ''),
    ];
}










function cm_unsub_apply(int $clientId, string $how): bool
{
    if ($clientId < 1) return false;
    $how = mb_substr(trim($how), 0, 40);

    $n = cm_q('UPDATE client
                  SET unsubscribed_at = ?, unsubscribed_how = ?,
                      resubscribed_at = NULL, resubscribed_by = \'\', updated_at = ?
                WHERE id = ? AND unsubscribed_at IS NULL',
              [cm_now(), $how, cm_now(), $clientId])->rowCount();
    if ($n < 1) return false;

    cm_audit('client.unsubscribed', 'client', $clientId,
             'all email stopped (' . $how . ')');
    return true;
}









function cm_unsub_lift(int $clientId, string $actor): bool
{
    if ($clientId < 1) return false;

    $n = cm_q('UPDATE client
                  SET unsubscribed_at = NULL, resubscribed_at = ?,
                      resubscribed_by = ?, updated_at = ?
                WHERE id = ? AND unsubscribed_at IS NOT NULL',
              [cm_now(), mb_substr($actor, 0, 190), cm_now(), $clientId])->rowCount();
    if ($n < 1) return false;

    cm_audit('client.resubscribed', 'client', $clientId,
             'email turned back on by ' . $actor . ' — the client confirmed it');
    return true;
}
