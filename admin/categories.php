<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('categories','read');
$db = getDB();
$pageTitle = 'Categories';
$activePage = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && gh_has_page_permission('categories', 'write')) {
    verify_csrf_or_fail('cat_form');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nom = limitString($_POST['nom'] ?? '', 100);
        $slug = normalizeSlug($_POST['slug'] ?? $nom);
        $icone = limitString($_POST['icone'] ?? 'fas fa-folder', 50);
        $ordre = (int)($_POST['ordre'] ?? 0);
        if ($nom && $slug) {
            $db->prepare("INSERT INTO categories(nom,slug,icone,ordre) VALUES(?,?,?,?)")->execute([$nom, $slug, $icone, $ordre]);
            setFlash('success', 'Categorie ajoutee.');
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nom = limitString($_POST['nom'] ?? '', 100);
        $slug = normalizeSlug($_POST['slug'] ?? $nom);
        $icone = limitString($_POST['icone'] ?? 'fas fa-folder', 50);
        $ordre = (int)($_POST['ordre'] ?? 0);
        $db->prepare("UPDATE categories SET nom=?,slug=?,icone=?,ordre=? WHERE id=?")->execute([$nom, $slug, $icone, $ordre, $id]);
        setFlash('success', 'Categorie mise a jour.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM categories WHERE id=? AND slug!='all'")->execute([$id]);
        setFlash('success', 'Categorie supprimee.');
    }

    header('Location: categories.php');
    exit;
}

$cats = $db->query("SELECT * FROM categories ORDER BY ordre ASC, id ASC")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);
$editingCat = null;
foreach ($cats as $cat) {
    if ((int)$cat['id'] === $editId) {
        $editingCat = $cat;
        break;
    }
}

require '_top.php';
?>

<div style="display:grid;grid-template-columns:1.2fr .8fr;gap:22px">
  <div class="card">
    <div class="card-header"><h2>Categories existantes</h2></div>
    <table>
      <thead><tr><th>Icone</th><th>Nom</th><th>Slug</th><th>Ordre</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($cats as $cat): ?>
      <tr>
        <td><i class="<?= e($cat['icone'] ?? 'fas fa-folder') ?>" style="font-size:1.2rem;color:var(--red)"></i></td>
        <td><strong><?= e($cat['nom']) ?></strong></td>
        <td><code style="font-size:.78rem"><?= e($cat['slug']) ?></code></td>
        <td><?= (int)$cat['ordre'] ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if ($cat['slug'] !== 'all' && gh_has_page_permission('categories', 'write')): ?>
              <a href="?edit=<?= (int)$cat['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette categorie ?')">
                <?= csrf_input('cat_form') ?>
                <input type="hidden" name="action" value="delete"/>
                <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
              </form>
            <?php else: ?>
              <span style="color:var(--muted);font-size:.78rem">Systeme</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (gh_has_page_permission('categories', 'write')): ?>
  <div class="card" style="padding:24px">
    <?php if ($editingCat): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px">
        <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700"><i class="fas fa-edit" style="color:var(--red);margin-right:6px"></i> Modifier la categorie</h3>
        <a href="categories.php" class="btn btn-secondary btn-sm">Annuler</a>
      </div>
      <form method="POST">
        <?= csrf_input('cat_form') ?>
        <input type="hidden" name="action" value="edit"/>
        <input type="hidden" name="id" value="<?= (int)$editingCat['id'] ?>"/>
        <div class="fg" style="margin-bottom:14px"><label>Nom</label><input type="text" name="nom" value="<?= e($editingCat['nom']) ?>" required/></div>
        <div class="fg" style="margin-bottom:14px"><label>Slug</label><input type="text" name="slug" value="<?= e($editingCat['slug']) ?>"/></div>
        <div class="fg" style="margin-bottom:14px"><label>Icone Font Awesome</label><input type="text" name="icone" value="<?= e($editingCat['icone'] ?? 'fas fa-folder') ?>"/></div>
        <div class="fg" style="margin-bottom:14px"><label>Ordre</label><input type="number" name="ordre" value="<?= (int)$editingCat['ordre'] ?>" min="0"/></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Mettre a jour</button>
      </form>
    <?php else: ?>
      <h3 style="font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;margin-bottom:18px"><i class="fas fa-plus" style="color:var(--red);margin-right:6px"></i> Ajouter une categorie</h3>
      <form method="POST">
        <?= csrf_input('cat_form') ?>
        <input type="hidden" name="action" value="add"/>
        <div class="fg" style="margin-bottom:14px"><label>Nom</label><input type="text" name="nom" required placeholder="Ex: RAM"/></div>
        <div class="fg" style="margin-bottom:14px"><label>Slug</label><input type="text" name="slug" placeholder="Ex: ram"/></div>
        <div class="fg" style="margin-bottom:14px"><label>Icone Font Awesome</label><input type="text" name="icone" value="fas fa-folder" placeholder="fas fa-memory"/></div>
        <div class="fg" style="margin-bottom:14px"><label>Ordre</label><input type="number" name="ordre" value="0" min="0"/></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php require '_bottom.php'; ?>
