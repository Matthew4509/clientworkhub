<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


























declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/schedule.php';
require_once CM_LIB . '/capacity.php';



require_once CM_LIB . '/publicsite.php';

cm_require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$start   = trim((string) ($_GET['start'] ?? ''));
$daysIn  = trim((string) ($_GET['days'] ?? ''));
$perIn   = trim((string) ($_GET['perday'] ?? ''));
$tplId   = (int) ($_GET['template'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d');



$tpl = $tplId > 0 ? cm_row('SELECT * FROM job_template WHERE id = ?', [$tplId]) : null;

$days = $daysIn !== '' && ctype_digit($daysIn) ? (int) $daysIn : 0;
if ($days < 1 && $tpl !== null) $days = (int) ($tpl['duration_days'] ?? 0);
if ($days > 365) $days = 365;

$perDay = $perIn !== '' && ctype_digit($perIn) ? (int) $perIn : 0;
if ($perDay < 1 && $tpl !== null) $perDay = cm_service_per_day_minutes($tpl);
if ($perDay > 1440) $perDay = 1440;

if ($days < 1) {


    echo json_encode(['ok' => false]);
    exit;
}

$label = static fn (string $d): string => date('j M Y', strtotime($d));

$plain   = date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'));
$verdict = cm_capacity_accepts($perDay, $days, $start);
$plan    = $verdict['plan'];
$nominal = (string) ($plan['nominal_end'] ?? '');
$loaded  = (string) ($plan['end'] ?? '');

echo json_encode([
    'ok'            => true,
    'days'          => $days,
    'plain'         => $plain,
    'plain_label'   => $label($plain),
    'nominal'       => $nominal,
    'nominal_label' => $nominal !== '' ? $label($nominal) : '',
    'loaded'        => $loaded,
    'loaded_label'  => $loaded !== '' ? $label($loaded) : '',



    'pushed'        => $loaded !== '' && $loaded !== $nominal,
    'unplannable'   => !$plan['ok'],
    'note'          => (string) ($plan['reason'] ?? ''),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
