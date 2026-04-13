<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('menu','read');
$db = getDB();
$pageTitle  = 'Menu du site';
$activePage = 'menu';

if ($_SERVER['REQUEST_METHOD']==='POST' && gh_has_page_permission('menu','write')) {
    verify_csrf_or_fail('menu_form');
    $action = $_POST['action']??'';
    if ($action==='add') {
        $label = limitString($_POST['label']??'',100);
        $url = limitString($_POST['url']??'',255);
        $target = safeTarget($_POST['target']??'_self');
        $ordre = (int)($_POST['ordre']??0);
        if ($label && $url)
            $db->prepare("INSERT INTO menu_items(label,url,target,ordre) VALUES(?,?,?,?)")->execute([$label,$url,$target,$ordre]);
        setFlash('success','Élément ajouté.');
    } elseif ($action==='delete') {
        $db->prepare("DELETE FROM menu_items WHERE id=?")->execute([(int)($_POST['id']??0)]);
        setFlash('success','Élément supprimé.');
    } elseif ($action==='toggle') {
        $db->prepare("UPDATE menu_items SET actif=NOT actif WHERE id=?")->execute([(int)($_POST['id']??0)]);
    }
    header('Location: menu.php'); exit;
}

$items = $db->query("SELECT * FROM menu_items ORDER BY ordre ASC")->fetchAll();
require '_top.php';
?>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:22px">
  <div class="card">
    <div class="card-header"><h2>Éléments du menu</h2></div>
    <table>
      <thead><tr><th>Label</th><th>URL</th><th>Ordre</th><th>Actif</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><strong><?= e($item['label']) ?></strong></td>
        <td><code style="font-size:.78rem"><?= e($item['url']) ?></code></td>
        <td><?= $item['ordre'] ?></td>
        <td><span class="badge <?= $item['actif']?'bd-success':'bd-secondary' ?>"><?= $item['actif']?'Oui':'Non' ?></span></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
            <?= csrf_input('menu_form') ?>
            <input type="hidden" name="action" value="delete"/><input type="hidden" name="id" value="<?= $item['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if(gh_has_page_permission('menu','write')): ?>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-plus" style="color:var(--red);margin-right:6px"></i> Ajouter</h3>
    <form method="POST">
      <?= csrf_input('menu_form') ?>
      <input type="hidden" name="action" value="add"/>
      <div class="fg" style="margin-bottom:12px"><label>Label</label><input type="text" name="label" required placeholder="Accueil"/></div>
      <div class="fg" style="margin-bottom:12px"><label>URL</label><input type="text" name="url" required placeholder="index.php ou #section"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Cible</label>
        <select name="target"><option value="_self">Même fenêtre</option><option value="_blank">Nouvel onglet</option></select>
      </div>
      <div class="fg" style="margin-bottom:12px"><label>Ordre</label><input type="number" name="ordre" value="0"/></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require '_bottom.php'; ?>
