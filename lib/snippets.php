<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0












declare(strict_types=1);


if (!defined('CM_ROOT')) { http_response_code(404); exit; }














function cm_snippets(bool $includeArchived = false): array
{
    $where = $includeArchived ? '' : 'WHERE archived = 0';
    return cm_all("SELECT * FROM work_snippet $where ORDER BY archived, sort, id");
}

function cm_snippet(int $id): ?array
{
    return cm_row('SELECT * FROM work_snippet WHERE id = ?', [$id]);
}








function cm_snippets_for_job(int $jobId): array
{
    return cm_all(
        "SELECT s.* FROM work_snippet s
         WHERE s.archived = 0
           AND (
                 NOT EXISTS (SELECT 1 FROM job_template_snippet x WHERE x.snippet_id = s.id)
                 OR EXISTS (
                      SELECT 1
                        FROM job_template_snippet t
                        JOIN job j ON j.template_id = t.template_id
                       WHERE t.snippet_id = s.id AND j.id = ?
                    )
               )
         ORDER BY s.uses DESC, s.body",
        [$jobId]);
}


function cm_template_snippet_ids(int $templateId): array
{
    $rows = cm_all('SELECT snippet_id FROM job_template_snippet WHERE template_id = ?', [$templateId]);
    return array_map(static fn(array $r): int => (int) $r['snippet_id'], $rows);
}

function cm_snippet_add(string $body): int
{
    $body = trim($body);
    return cm_insert('INSERT INTO work_snippet (body, created_at, updated_at) VALUES (?, ?, ?)',
                     [$body, cm_now(), cm_now()]);
}

function cm_snippet_update(int $id, string $body): void
{
    cm_q('UPDATE work_snippet SET body = ?, updated_at = ? WHERE id = ?',
         [trim($body), cm_now(), $id]);
}








function cm_snippet_archive(int $id, bool $archived = true): void
{
    cm_q('UPDATE work_snippet SET archived = ?, updated_at = ? WHERE id = ?',
         [$archived ? 1 : 0, cm_now(), $id]);
}













function cm_snippet_renumber(): array
{
    $rows = cm_all('SELECT id, sort FROM work_snippet WHERE archived = 0 ORDER BY sort, id');
    $n = 0;
    foreach ($rows as $i => $r) {
        $want = ($i + 1) * 10;
        if ((int) $r['sort'] !== $want) {
            cm_q('UPDATE work_snippet SET sort = ? WHERE id = ?', [$want, (int) $r['id']]);
            $n++;
        }
        $rows[$i]['sort'] = $want;
    }
    return $rows;
}






function cm_snippet_move(int $id, string $dir): bool
{
    if ($dir !== 'up' && $dir !== 'down') return false;
    $rows = cm_snippet_renumber();

    $at = null;
    foreach ($rows as $i => $r) if ((int) $r['id'] === $id) { $at = $i; break; }
    if ($at === null) return false;

    $to = $dir === 'up' ? $at - 1 : $at + 1;
    if ($to < 0 || $to >= count($rows)) return false;




    cm_q('UPDATE work_snippet SET sort = ? WHERE id = ?', [(int) $rows[$to]['sort'], $id]);
    cm_q('UPDATE work_snippet SET sort = ? WHERE id = ?', [(int) $rows[$at]['sort'], (int) $rows[$to]['id']]);
    return true;
}


function cm_template_snippets_set(int $templateId, array $snippetIds): void
{
    cm_q('DELETE FROM job_template_snippet WHERE template_id = ?', [$templateId]);
    foreach (array_unique(array_map('intval', $snippetIds)) as $sid) {
        if ($sid > 0) {
            cm_q('INSERT IGNORE INTO job_template_snippet (template_id, snippet_id) VALUES (?, ?)',
                 [$templateId, $sid]);
        }
    }
}






function cm_snippets_count_uses(string $note): void
{
    $note = trim($note);
    if ($note === '') return;
    foreach (cm_snippets() as $s) {
        if ((string) $s['body'] !== '' && mb_stripos($note, (string) $s['body']) !== false) {
            cm_q('UPDATE work_snippet SET uses = uses + 1 WHERE id = ?', [(int) $s['id']]);
        }
    }
}










function cm_snippet_picker_html(int $jobId, string $targetId): string
{
    $snips = cm_snippets_for_job($jobId);
    if (!$snips) return '';

    $h = '<div class="snips" data-snip-target="' . e($targetId) . '">'
       . '<span class="snips-lbl">Snippets</span>';
    foreach ($snips as $s) {
        $h .= '<button type="button" class="snip" data-snip="' . e((string) $s['body']) . '">'
            . e((string) $s['body']) . '</button>';
    }
    $h .= '</div>';
    return $h;
}
