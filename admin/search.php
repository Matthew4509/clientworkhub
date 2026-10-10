<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0







declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/clients.php';
require_once CM_LIB . '/assets.php';
require_once CM_LIB . '/jobs.php';

$admin = cm_require_admin();

$term = trim((string) ($_GET['q'] ?? ''));

$clients = [];
$jobs    = [];
$assets  = [];
if ($term !== '') {
    $like = '%' . $term . '%';
    try {
        $clients = cm_client_search($term, 'all', 1, 25)['rows'];
        $jobs = cm_all(
            'SELECT j.*, c.first_name, c.last_name, c.company, c.email AS client_email
             FROM job j JOIN client c ON c.id = j.client_id
             WHERE j.name LIKE ? OR j.tags LIKE ? OR j.notes LIKE ? OR j.description LIKE ?
                OR CONCAT_WS(CHAR(32), c.first_name, c.last_name) LIKE ? OR c.company LIKE ?
             ORDER BY j.updated_at DESC LIMIT 25', [$like, $like, $like, $like, $like, $like]);

        $assets = cm_all(
            'SELECT a.id, a.name, a.type, a.url, a.client_id,
                    c.first_name, c.last_name, c.company, c.email AS client_email
             FROM client_asset a JOIN client c ON c.id = a.client_id
             WHERE a.name LIKE ? OR a.url LIKE ? OR a.handle LIKE ?
             ORDER BY a.updated_at DESC LIMIT 25', [$like, $like, $like]);
    } catch (Throwable $ex) {

    }
}

cm_head('Search', '', true);
cm_render_flashes();
cm_admin_nav('');
?>
<h1>Search</h1>

<div class="card">
  <form method="get" action="<?= e(cm_url('admin/search.php')) ?>">
    <div class="row mb-0">
      <label class="lbl" for="q">Clients, jobs and accounts</label>
      <input type="search" id="q" name="q" value="<?= e($term) ?>" autofocus
             placeholder="A name, a company, a tag, a job, an account…">
    </div>
    <div class="actions mt-10">
      <button class="btn btn-sm" type="submit">Search</button>
    </div>
  </form>
  <p class="hint">Sign-in details are never searched and can never appear
  here — they live encrypted behind each asset's own reveal button.</p>
</div>

<?php if ($term !== ''): ?>
  <div class="card">
    <h2>Clients — <?= count($clients) ?></h2>
    <?php if (!$clients): ?><p class="muted small">None match.</p>
    <?php else: ?>
      <ul class="tile-list">
        <?php 



 ?>
        <?php foreach ($clients as $c):
            $st = cm_client_statuses()[(string) $c['status']] ?? null;
            $rowVip = (int) ($c['vip'] ?? 0) === 1; ?>
          <li<?= $rowVip ? ' class="is-vip"' : '' ?>><a href="<?= e(cm_url('admin/client.php?id=' . (int) $c['id'])) ?>"><b><?= e(cm_client_display_name($c)) ?></b></a>
            <?php if ($rowVip && (string) $c['status'] === 'active'): ?>
              <span class="pill pill-vip">VIP</span>
            <?php elseif ($st): ?>
              <span class="pill <?= e($st['pill']) ?>"><?= e($st['label']) ?></span>
            <?php endif; ?>
            <?php if ((string) $c['company'] !== ''): ?><span class="muted small"><?= e((string) $c['company']) ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Jobs — <?= count($jobs) ?></h2>
    <?php if (!$jobs): ?><p class="muted small">None match.</p>
    <?php else: ?>
      <ul class="tile-list">
        <?php foreach ($jobs as $j): $jst = cm_job_statuses()[(string) $j['status']] ?? null; ?>
          <li><a href="<?= e(cm_url('admin/job.php?id=' . (int) $j['id'])) ?>"><b><?= e((string) $j['name']) ?></b></a>
            <?php if ($jst): ?><span class="pill <?= e($jst['pill']) ?>"><?= e($jst['label']) ?></span><?php endif; ?>
            <span class="muted small">for <?= e(cm_client_display_name(['id' => $j['client_id'], 'first_name' => $j['first_name'], 'last_name' => $j['last_name'], 'company' => $j['company'], 'email' => $j['client_email']])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Accounts and websites — <?= count($assets) ?></h2>
    <?php if (!$assets): ?><p class="muted small">None match.</p>
    <?php else: ?>
      <ul class="tile-list">
        <?php foreach ($assets as $a): ?>
          <li><a href="<?= e(cm_url('admin/asset.php?id=' . (int) $a['id'])) ?>"><b><?= e((string) $a['name']) ?></b></a>
            <span class="muted small"><?= e(cm_asset_types()[(string) $a['type']] ?? '') ?>
              — <?= e(cm_client_display_name(['id' => $a['client_id'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'company' => $a['company'], 'email' => $a['client_email']])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php cm_foot(); ?>
