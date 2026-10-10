<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0














declare(strict_types=1);

if (!defined('CM_ROOT')) { http_response_code(404); exit; }








function cm_priority_factors(): array
{
    return [
        'overdue' => [
            'label' => 'Overdue',
            'blurb' => 'Past its date. Late work stays late until it is done.',
        ],
        'vip' => [
            'label' => 'VIP clients',
            'blurb' => 'Everything for a client marked VIP, ahead of everyone else.',
        ],
        'wrapup' => [
            'label' => 'Finished, not handed back',
            'blurb' => 'The tasks are done and the client has not been told. '
                     . 'Nothing chases you for this and they are waiting.',
        ],
        'priority' => [
            'label' => 'Job priority',
            'blurb' => 'The critical / high / normal / low set on the job itself. '
                     . 'This is how the queue already sorted before any of the rest '
                     . 'existed, so leaving it out would quietly lose behaviour.',
        ],
        'due' => [
            'label' => 'Due soonest',
            'blurb' => 'Then simply what is due first. Always last, because '
                     . 'something has to break the tie.',
        ],
    ];
}








function cm_priority_order(): array
{
    $all = array_keys(cm_priority_factors());
    $raw = trim((string) cm_setting('priority.order', ''));

    $out = [];
    foreach (explode(',', $raw) as $k) {
        $k = trim($k);
        if ($k !== '' && in_array($k, $all, true) && !in_array($k, $out, true)) $out[] = $k;
    }
    foreach ($all as $k) {
        if (!in_array($k, $out, true)) $out[] = $k;
    }
    return $out;
}









function cm_priority_score(array $row, string $factor, string $today): int
{
    switch ($factor) {
        case 'overdue':
            return ((string) ($row['scheduled_date'] ?? '') !== ''
                 && (string) $row['scheduled_date'] < $today) ? 0 : 1;
        case 'vip':
            return (int) ($row['client_vip'] ?? 0) === 1 ? 0 : 1;
        case 'wrapup':
            return (string) ($row['job_status'] ?? '') === 'wrapup' ? 0 : 1;
        case 'priority':
            $rank = ['critical' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
            return $rank[(string) ($row['priority'] ?? 'normal')] ?? 2;
        case 'due':


            $d = (string) ($row['scheduled_date'] ?? '');
            if ($d === '') return 2;
            return $d < $today ? 0 : ($d === $today ? 1 : 2);
        default:
            return 1;
    }
}








function cm_priority_sort(array $queue, ?array $order = null): array
{
    $order = $order ?? cm_priority_order();
    $today = date('Y-m-d');

    usort($queue, static function (array $a, array $b) use ($order, $today): int {
        foreach ($order as $factor) {
            $cmp = cm_priority_score($a, $factor, $today) <=> cm_priority_score($b, $factor, $today);
            if ($cmp !== 0) return $cmp;
        }
        $cmp = strcmp((string) ($a['scheduled_date'] ?? ''), (string) ($b['scheduled_date'] ?? ''));
        if ($cmp !== 0) return $cmp;
        return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
    });

    return $queue;
}


function cm_priority_sentence(?array $order = null): string
{
    $f = cm_priority_factors();
    $labels = [];
    foreach ($order ?? cm_priority_order() as $k) {
        if (isset($f[$k])) $labels[] = $f[$k]['label'];
    }
    return implode(', then ', $labels);
}
