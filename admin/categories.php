<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('categories','read');
$db = getDB();
$pageTitle  = 'Catégories';
$activePage = 'categories';

if ($_SERVER['REQUEST_METHOD']==='POST' && gh_has_page_permission('categories','write')) {
    verify_csrf_or_fail('cat_form');
    $action = $_POST['action']??'';
    if ($action==='add') {
        $nom = limitString($_POST['nom']??'',100);
        $slug = normalizeSlug($_POST['slug']??$nom);
        $icone = limitString($_POST['icone']??'fas fa-folder',50);
        $ordre = (int)($_POST['ordre']??0);
        if ($nom && $slug) {
            $db->prepare("INSERT INTO categories(nom,slug,icone,ordre) VALUES(?,?,?,?)")->execute([$nom,$slug,$icone,$ordre]);
            setFlash('success','Catégorie ajoutée.');
        }
    } elseif ($action==='edit') {
        $id = (int)($_POST['id']??0);
        $nom = limitString($_POST['nom']??'',100);
        $slug = normalizeSlug($_POST['slug']??$nom);
        $icone = limitString($_POST['icone']??'fas fa-folder',50);
        $ordre = (int)($_POST['ordre']??0);
        $db->prepare("UPDATE categories SET nom=?,slug=?,icone=?,ordre=? WHERE id=?")->execute([$nom,$slug,$icone,$ordre,$id]);
        setFlash('success','Catégorie mise à jour.');
    } elseif ($action==='delete') {
        $id = (int)($_POST['id']??0);
        $db->prepare("DELETE FROM categories WHERE id=? AND slug!='all'")->execute([$id]);
        setFlash('success','Catégorie supprimée.');
    }
    header('Location: categories.php'); exit;
}

$cats = $db->query("SELECT * FROM categories ORDER BY ordre ASC")->fetchAll();
require '_top.php';
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px">
  <div class="card">
    <div class="card-header"><h2>Catégories existantes</h2></div>
    <table>
      <thead><tr><th>Icône</th><th>Nom</th><th>Slug</th><th>Ordre</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($cats as $cat): ?>
      <tr>
        <td><i class="<?= e($cat['icone']??'fas fa-folder') ?>" style="font-size:1.2rem;color:var(--red)"></i></td>
        <td><strong><?= e($cat['nom']) ?></strong></td>
        <td><code style="font-size:.78rem"><?= e($cat['slug']) ?></code></td>
        <td><?= $cat['ordre'] ?></td>
        <td>
          <?php if ($cat['slug']!=='all' && gh_has_page_permission('categories','write')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ?')">
            <?= csrf_input('cat_form') ?>
            <input type="hidden" name="action" value="delete"/>
            <input type="hidden" name="id" value="<?= $cat['id'] ?>"/>
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if(gh_has_page_permission('categories','write')): ?>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-plus" style="color:var(--red);margin-right:6px"></i> Ajouter une catégorie</h3>
    <form method="POST">
      <?= csrf_input('cat_form') ?>
      <input type="hidden" name="action" value="add"/>
      <div class="fg" style="margin-bottom:14px"><label>Nom</label><input type="text" name="nom" required placeholder="Ex: RAM"/></div>
      <div class="fg" style="margin-bottom:14px"><label>Slug</label><input type="text" name="slug" placeholder="Ex: ram"/></div>
      <div class="fg" style="margin-bottom:14px"><label>Icône Font Awesome</label><input type="text" name="icone" value="fas fa-folder" placeholder="fas fa-memory"/></div>
      <div class="fg" style="margin-bottom:14px"><label>Ordre</label><input type="number" name="ordre" value="0" min="0"/></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require '_bottom.php'; ?>
