<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';

$admin = cm_require_admin();

$perPage = 50;
$page = max(1, (int) ($_GET['p'] ?? 1));
$total = 0;
$rows = [];
try {
    $total = (int) cm_val('SELECT COUNT(*) FROM audit_log');
    $rows  = cm_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . $perPage
                  . ' OFFSET ' . (($page - 1) * $perPage));
} catch (Throwable $ex) {

}
$pages = max(1, (int) ceil($total / $perPage));

cm_head('Audit log', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'audit', 'Audit log');
?>
<p class="sub">Every security-sensitive action, newest first —
<?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?>. Nothing on
this screen can be edited or deleted.</p>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted small">Nothing recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="small mono"><?= e(date('j M Y, g:ia', strtotime((string) $r['created_at']))) ?></td>
            <td class="small"><?= e((string) $r['actor_email']) ?></td>
            <td class="small mono"><?= e((string) $r['action']) ?></td>
            <td class="small"><?= e((string) $r['note']) ?>
              <?php if ($r['target_type'] !== '' && $r['target_id'] !== null): ?>
                <span class="muted mono small">(<?= e((string) $r['target_type']) ?> #<?= (int) $r['target_id'] ?>)</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <p class="muted small mt-12">
        Page <?= $page ?> of <?= $pages ?>
        <?php if ($page > 1): ?>
          · <a href="<?= e(cm_url('admin/audit.php?p=' . ($page - 1))) ?>">Newer</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
          · <a href="<?= e(cm_url('admin/audit.php?p=' . ($page + 1))) ?>">Older</a>
        <?php endif; ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php cm_foot(); ?>
