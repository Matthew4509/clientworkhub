<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0


































if (!defined('CM_ROOT')) { http_response_code(404); exit; }





require_once CM_LIB . '/publicsite.php';










function cm_finder_questions(): array
{
    $questions = cm_all('SELECT id, prompt, hint FROM service_finder_question
                          ORDER BY sort, id');
    if (!$questions) return [];

    $options = cm_all('SELECT id, question_id, label FROM service_finder_option
                        ORDER BY sort, id');
    $points = cm_all('SELECT option_id, template_id FROM service_finder_points');

    $byOption = [];
    foreach ($points as $p) {
        $byOption[(int) $p['option_id']][] = (int) $p['template_id'];
    }
    $byQuestion = [];
    foreach ($options as $o) {
        $o['id'] = (int) $o['id'];
        $o['templates'] = $byOption[$o['id']] ?? [];
        $byQuestion[(int) $o['question_id']][] = $o;
    }
    $out = [];
    foreach ($questions as $q) {
        $q['id'] = (int) $q['id'];
        $q['options'] = $byQuestion[$q['id']] ?? [];
        $out[] = $q;
    }
    return $out;
}








function cm_finder_ready(): bool
{











    try {
        $n = (int) (cm_val('SELECT COUNT(*)
                              FROM service_finder_points p
                              JOIN service_finder_option o ON o.id = p.option_id
                              JOIN job_template t ON t.id = p.template_id
                             WHERE t.published = 1') ?? 0);
    } catch (Throwable $ex) {
        error_log('service finder not readable: ' . $ex->getMessage());
        return false;
    }
    return $n > 0;
}











function cm_finder_match(array $optionIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
    if (!$ids) return [];

    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = cm_all("SELECT p.template_id, COUNT(*) AS score
                      FROM service_finder_points p
                      JOIN service_finder_option o ON o.id = p.option_id
                      JOIN job_template t ON t.id = p.template_id
                     WHERE p.option_id IN ($in) AND t.published = 1
                     GROUP BY p.template_id", $ids);
    if (!$rows) return [];

    $scores = [];
    foreach ($rows as $r) $scores[(int) $r['template_id']] = (int) $r['score'];

    $out = [];
    foreach (cm_public_services() as $svc) {
        $id = (int) $svc['id'];
        if (!isset($scores[$id])) continue;
        $out[] = ['service' => $svc, 'score' => $scores[$id]];
    }





    $pos = [];
    foreach ($out as $i => $row) $pos[(int) $row['service']['id']] = $i;
    usort($out, function ($a, $b) use ($pos) {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return $pos[(int) $a['service']['id']] <=> $pos[(int) $b['service']['id']];
    });
    return $out;
}


function cm_finder_heading(): string
{
    return cm_public_setting('finder_heading', 'Which service is right for me?');
}

function cm_finder_intro(): string
{


    return cm_public_setting('finder_intro',
        "Three quick questions and we'll point you to the right one.");
}
