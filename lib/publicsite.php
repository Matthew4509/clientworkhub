<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0



















































declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }

require_once CM_LIB . '/format.php';
require_once CM_LIB . '/capacity.php';




















function cm_portal_arrival_modes(): array
{
    return [
        'list' => [
            'label' => 'Show them the list of services',
            'blurb' => 'The portal front page lists everything you have published, and each one '
                     . 'can be enquired about — or paid for, once a payment method is set up. '
                     . 'This is the default and it is what your portal does today.',
        ],
        'home' => [
            'label' => 'Send them to my main website',
            'blurb' => 'For when your real site is somewhere else and this is only the billing '
                     . 'side of it. Anyone landing on the portal root goes straight to the '
                     . 'address below. Direct links to a service still work exactly as before.',
        ],
        'none' => [
            'label' => 'Show nothing at all',
            'blurb' => 'The portal front page answers "not found". Only people you have sent a '
                     . 'direct service link to can reach anything. The strictest of the three, '
                     . 'and the right one if the portal is private to your existing clients.',
        ],
    ];
}








function cm_portal_arrival(): string
{
    $mode = (string) cm_setting('public.no_link', 'list');
    if (!isset(cm_portal_arrival_modes()[$mode])) return 'list';
    if ($mode === 'home' && trim((string) cm_setting('public.home_url', '')) === '') return 'list';
    return $mode;
}








function cm_public_enabled(): bool
{


    if (cm_home()) return false;
    try { return (string) (cm_setting('public.enabled', '') ?? '') === '1'; }
    catch (Throwable $ex) { return false; }
}

function cm_public_business_name(): string
{
    $n = '';
    try { $n = trim((string) (cm_setting('public.business_name', '') ?? '')); }
    catch (Throwable $ex) { $n = ''; }
    if ($n === '') {
        try { $n = trim((string) (cm_setting('mail.from_name', '') ?? '')); }
        catch (Throwable $ex) { $n = ''; }
    }
    return $n !== '' ? $n : 'Our services';
}

function cm_public_setting(string $key, string $default = ''): string
{
    try { $v = trim((string) (cm_setting('public.' . $key, '') ?? '')); }
    catch (Throwable $ex) { return $default; }
    return $v !== '' ? $v : $default;
}




function cm_public_services(bool $featuredOnly = false): array
{
    $where = $featuredOnly ? ' AND featured = 1' : '';
    return cm_all("SELECT * FROM job_template
                    WHERE published = 1{$where}
                    ORDER BY featured DESC, public_sort, name");
}
























function cm_public_draft_keep(array $fields, string $error): void
{
    $_SESSION['cm_public_draft'] = ['fields' => $fields, 'error' => $error, 'at' => time()];
}






function cm_public_draft_take(): array
{
    $d = $_SESSION['cm_public_draft'] ?? null;
    unset($_SESSION['cm_public_draft']);
    if (!is_array($d) || !isset($d['fields'], $d['error'])) {
        return ['fields' => [], 'error' => ''];
    }


    if (time() - (int) ($d['at'] ?? 0) > 600) return ['fields' => [], 'error' => ''];
    return ['fields' => (array) $d['fields'], 'error' => (string) $d['error']];
}


function cm_public_draft_value(array $draft, string $field): string
{
    return (string) ($draft['fields'][$field] ?? '');
}
























function cm_public_has_services(): bool
{
    static $has = null;
    if ($has !== null) return $has;
    return $has = ((int) cm_val('SELECT COUNT(*) FROM job_template WHERE published = 1') > 0);
}

function cm_public_service(int $id): ?array
{
    return cm_row('SELECT * FROM job_template WHERE id = ? AND published = 1', [$id]);
}




function cm_public_service_name(array $t): string
{
    return (string) $t['name'];
}





function cm_service_per_day_minutes(array $t): int
{




    require_once CM_LIB . '/services.php';
    return cm_service_time_breakdown($t)['per_day'];
}








function cm_public_quote(array $t, ?string $from = null): array
{
    $days   = max(1, (int) ($t['duration_days'] ?? 1));
    $perDay = cm_service_per_day_minutes($t);
    $verdict = cm_capacity_accepts($perDay, $days, $from);
    $plan = $verdict['plan'];

    return [
        'available'   => $verdict['accepted'],
        'days'        => $days,
        'per_day'     => $perDay,
        'start'       => $plan['start'] ?? '',
        'end'         => $plan['end'] ?? '',
        'delayed'     => (bool) ($plan['delayed'] ?? false),
        'extended'    => (bool) ($plan['extended'] ?? false),
        'note'        => (string) ($plan['reason'] ?? ''),
        'cents'       => (int) ($t['amount_cents'] ?? 0),
        'freq'        => (string) ($t['billing_freq'] ?? 'oneoff'),
    ];
}










function cm_link_issue(int $clientId): string
{
    cm_q('UPDATE client_link SET revoked_at = ? WHERE client_id = ? AND revoked_at IS NULL',
         [cm_now(), $clientId]);
    $token = bin2hex(random_bytes(32));



    cm_insert('INSERT INTO client_link (client_id, token_hash, token_enc, created_at) VALUES (?, ?, ?, ?)',
              [$clientId, cm_link_hash($token), cm_encrypt($token), cm_now()]);
    return $token;
}












function cm_link_hash(string $token): string
{
    return hash('sha256', $token);
}








function cm_token_clear(?array $row): string
{
    $enc = (string) ($row['token_enc'] ?? '');
    if ($enc === '') return '';
    $clear = cm_decrypt($enc);
    return is_string($clear) ? $clear : '';
}


function cm_link_for(int $clientId): string
{
    $row = cm_row('SELECT token_enc FROM client_link WHERE client_id = ? AND revoked_at IS NULL
                   ORDER BY id DESC LIMIT 1', [$clientId]);
    if ($row !== null) {
        $clear = cm_decrypt((string) ($row['token_enc'] ?? ''));



        if ($clear !== '' && $clear !== null) return $clear;
    }
    return cm_link_issue($clientId);
}









function cm_link_resolve(?string $token): ?array
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;



    return cm_row("SELECT l.* FROM client_link l JOIN client c ON c.id = l.client_id
                    WHERE l.token_hash = ? AND l.revoked_at IS NULL AND c.status <> 'inactive'",
                  [cm_link_hash($token)]);
}


function cm_link_touch(array $link): void
{
    cm_q('UPDATE client_link SET uses = uses + 1, last_used_at = ? WHERE id = ?',
         [cm_now(), (int) $link['id']]);
}


function cm_link_url(string $token): string
{
    return cm_url('portal/my.php?k=' . $token);
}










function cm_link_client_view(array $link): ?array
{
    $c = cm_row('SELECT id, first_name, last_name, company FROM client WHERE id = ?',
                [(int) $link['client_id']]);
    if ($c === null) return null;

    $first = trim((string) $c['first_name']);
    if ($first === '') $first = trim((string) $c['company']);

    return [
        'id'    => (int) $c['id'],
        'greet' => $first !== '' ? $first : 'there',
    ];
}









function cm_link_history(int $clientId, int $limit = 10): array
{
    return cm_all(
        "SELECT j.id, j.name, j.amount_cents, j.billing_freq, j.start_date, j.end_date,
                j.status, j.template_id,
                t.published AS tpl_published, t.public_name, t.duration_days,
                t.amount_cents AS tpl_cents, t.billing_freq AS tpl_freq
           FROM job j
      LEFT JOIN job_template t ON t.id = j.template_id
          WHERE j.client_id = ? AND j.status IN ('active', 'completed')
          ORDER BY COALESCE(j.completed_at, j.start_date) DESC, j.id DESC
          LIMIT " . (int) $limit, [$clientId]);
}




function cm_booking_reference(): string
{

    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = '';
        for ($i = 0; $i < 8; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $taken = cm_val('SELECT id FROM booking WHERE reference = ?', [$ref]);
    } while ($taken !== null && $taken !== false);
    return $ref;
}

















function cm_booking_identify(?array $link, string $email): array
{
    $email = strtolower(trim($email));

    if ($link !== null) {
        $known = cm_row('SELECT id, email FROM client WHERE id = ?', [(int) $link['client_id']]);
        if ($known !== null) {
            if ($email !== '' && strtolower(trim((string) $known['email'])) === $email) {
                return ['client_id' => (int) $known['id'], 'confidence' => 'certain',
                        'note' => 'Their link and their email both match.'];
            }
            return ['client_id' => (int) $known['id'], 'confidence' => 'likely',
                    'note' => 'Came in on their link, but typed a different email address — worth a look.'];
        }
    }

    if ($email !== '') {
        $byEmail = cm_row("SELECT id FROM client WHERE LOWER(email) = ? AND status <> 'inactive'
                           ORDER BY id LIMIT 1", [$email]);
        if ($byEmail !== null) {
            return ['client_id' => (int) $byEmail['id'], 'confidence' => 'likely',
                    'note' => 'Matched on email address alone — no link.'];
        }
    }

    return ['client_id' => null, 'confidence' => 'new',
            'note' => 'Nobody on file. Create the client from the booking if it is real.'];
}















function cm_booking_name_parts(string $whole): array
{
    $whole = trim($whole);
    $parts = preg_split('/\s+/', $whole, 2) ?: [$whole];
    return ['first' => (string) ($parts[0] ?? ''), 'last' => (string) ($parts[1] ?? '')];
}











function cm_booking_client_diffs(array $b, array $client): array
{
    $n = cm_booking_name_parts((string) ($b['name'] ?? ''));
    $rows = [
        ['key' => 'first_name', 'label' => 'First name',
         'booking' => $n['first'],                        'client' => (string) ($client['first_name'] ?? '')],
        ['key' => 'last_name',  'label' => 'Last name',
         'booking' => $n['last'],                         'client' => (string) ($client['last_name'] ?? '')],
        ['key' => 'company',    'label' => 'Business',
         'booking' => trim((string) ($b['company'] ?? '')), 'client' => (string) ($client['company'] ?? '')],
        ['key' => 'email',      'label' => 'Email',
         'booking' => trim((string) ($b['email'] ?? '')),   'client' => (string) ($client['email'] ?? '')],
        ['key' => 'phone',      'label' => 'Phone',
         'booking' => trim((string) ($b['phone'] ?? '')),   'client' => (string) ($client['phone'] ?? '')],
    ];
    foreach ($rows as $i => $r) {
        $rows[$i]['differs'] = $r['booking'] !== ''
            && mb_strtolower(trim($r['booking'])) !== mb_strtolower(trim($r['client']));
    }
    return $rows;
}


function cm_booking_diff_count(array $b, array $client): int
{
    $n = 0;
    foreach (cm_booking_client_diffs($b, $client) as $r) {
        if ($r['differs']) $n++;
    }
    return $n;
}






function cm_booking_needs_identity_check(array $b): bool
{
    if (($b['client_id'] ?? null) === null) return false;
    if ((int) ($b['identity_checked'] ?? 0) === 1) return false;
    $client = cm_row('SELECT * FROM client WHERE id = ?', [(int) $b['client_id']]);
    if ($client === null) return false;
    return cm_booking_diff_count($b, $client) > 0;
}








function cm_booking_rival_clients(array $b, int $excludeId = 0): array
{
    $company = trim((string) ($b['company'] ?? ''));
    $whole   = trim((string) ($b['name'] ?? ''));
    if ($company === '' && $whole === '') return [];
    $sql = "SELECT id, first_name, last_name, company, email FROM client
             WHERE status <> 'inactive' AND id <> ?";
    $params = [$excludeId];
    $or = [];
    if ($company !== '') { $or[] = 'LOWER(company) = ?'; $params[] = mb_strtolower($company); }
    if ($whole !== '')   {
        $or[] = "LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = ?";
        $params[] = mb_strtolower($whole);
    }



    $sql .= ' AND (' . implode(' OR ', $or) . ') ORDER BY id LIMIT 5';
    return cm_all($sql, $params);
}


function cm_booking_create(array $fields): string
{
    $ref = cm_booking_reference();
    cm_insert(
        'INSERT INTO booking (reference, template_id, client_id, link_id, name, email, phone,
                              company, message, quoted_cents, quoted_freq, requested_from,
                              planned_start, planned_end, plan_note, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$ref,
         $fields['template_id'] ?? null,
         $fields['client_id'] ?? null,
         $fields['link_id'] ?? null,
         mb_substr((string) ($fields['name'] ?? ''), 0, 160),
         mb_substr((string) ($fields['email'] ?? ''), 0, 190),
         mb_substr((string) ($fields['phone'] ?? ''), 0, 60),
         mb_substr((string) ($fields['company'] ?? ''), 0, 160),



         cm_text_fit((string) ($fields['message'] ?? '')),
         (int) ($fields['quoted_cents'] ?? 0),
         (string) ($fields['quoted_freq'] ?? 'oneoff'),
         $fields['requested_from'] ?? null,
         $fields['planned_start'] ?? null,
         $fields['planned_end'] ?? null,
         mb_substr((string) ($fields['plan_note'] ?? ''), 0, 255),
         'new', cm_now(), cm_now()]);
    return $ref;
}


function cm_feedback_create(?int $clientId, ?int $jobId, ?int $linkId,
                            string $sentiment, string $wants): int
{
    $sentiment = in_array($sentiment, ['happy', 'issue', 'unsaid'], true) ? $sentiment : 'unsaid';
    return cm_insert(
        'INSERT INTO client_feedback (client_id, job_id, link_id, sentiment, wants, created_at)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$clientId, $jobId, $linkId, $sentiment, mb_substr($wants, 0, 4000), cm_now()]);
}











function cm_public_spam_check(string $scope): string
{
    if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
        return 'refused';
    }
    $who = cm_client_ip() . '|' . $scope;
    if (cm_rate_exceeded('public_' . $scope, $who, 8, 60)) {
        return 'That is a lot of requests in an hour. Try again shortly, or email us directly.';
    }
    cm_rate_record('public_' . $scope, $who);
    return '';
}
