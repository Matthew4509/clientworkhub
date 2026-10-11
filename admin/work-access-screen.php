<?php
// SPDX-License-Identifier: PolyForm-Shield-1.0.0
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
cm_home_hide();
require_once CM_LIB . '/view.php';
require_once CM_LIB . '/workaccess.php';
$admin = cm_require_admin();



cm_schema_gate('work_access', 'settings');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$screen = cm_row('SELECT * FROM work_access WHERE id = ?', [$id]);
if (!$screen) { http_response_code(404); exit('Screen not found.'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    cm_require_csrf();
    cm_work_access_display_save($id, $_POST, (string) $admin['email']);
    cm_flash('Screen display updated.');
    cm_redirect('admin/work-access-screen.php?id=' . $id);
}
cm_head('Customise work screen', '', true);
cm_render_flashes();
cm_admin_nav('settings');
cm_admin_subnav('settings', 'work_access', 'Work screens');
?>
<p class="sub"><a href="<?=e(cm_url('admin/work-access.php'))?>">&larr; Work screens</a></p>
<div class="card narrow-card">
  <h2><?=e((string) $screen['name'])?></h2>
  <p class="muted small">Choose exactly what this screen displays. Unchecked information is not sent to the shared page.</p>
  <form method="post">
    <?=cm_csrf_field()?><input type="hidden" name="id" value="<?=$id?>">
    <div class="form-2col"><div class="row"><label class="lbl">Feed</label><select name="screen_view"><?php foreach(cm_work_access_views() as $key=>$label):?><option value="<?=e($key)?>" <?=$screen['screen_view']===$key?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></div><div class="row"><label class="lbl">Items shown</label><input type="number" name="max_items" min="1" max="100" required value="<?=(int)($screen['max_items']??10)?>"></div></div>
    <div class="row"><label class="lbl">Public heading <span class="muted">optional</span></label><input name="public_heading" maxlength="120" value="<?=e((string)$screen['public_heading'])?>" placeholder="Now serving"></div>
    <div class="check-grid">
      <?php foreach(['auth_required'=>'Require sign-in','show_business_name'=>'Business name','show_client_name'=>'Client name','show_job_name'=>'Job name','show_estimate'=>'Estimated time','highlight_vip'=>'Highlight VIP'] as $key=>$label): ?>
        <label class="check"><input type="checkbox" name="<?=e($key)?>" value="1" <?=((int)$screen[$key])?'checked':''?>> <?=e($label)?></label>
      <?php endforeach; ?>
    </div>
    <p class="hint">With sign-in off, the URL is a public, read-only display. VIP highlighting is off by default so a public queue treats everyone consistently unless you deliberately choose otherwise.</p>
    <div class="actions mt-16"><button class="btn" type="submit">Save screen display</button></div>
  </form>
</div>
<?php cm_foot(); ?>
