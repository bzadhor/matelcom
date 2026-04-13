<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('produits','read');
$db = getDB();
$pageTitle  = 'Produits';
$activePage = 'produits';

// Suppression
if (isset($_GET['supprimer']) && gh_has_page_permission('produits','write')) {
    verify_csrf_or_fail('delete_prod');
    $db->prepare("DELETE FROM produits WHERE id=?")->execute([(int)$_GET['supprimer']]);
    setFlash('success','Produit supprimé.');
    header('Location: produits.php'); exit;
}

// Toggle statut
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE produits SET statut=NOT statut WHERE id=?")->execute([(int)$_GET['toggle']]);
    header('Location: produits.php'); exit;
}

$produits = $db->query("SELECT p.*, c.nom as cat_nom FROM produits p LEFT JOIN categories c ON FIND_IN_SET(c.slug, REPLACE(p.categories,' ',',')) ORDER BY p.ordre ASC")->fetchAll();
// Déduplicate
$seen=[]; $prods=[];
foreach($produits as $pr){ if(!isset($seen[$pr['id']])){$seen[$pr['id']]=1;$prods[]=$pr;}}

require '_top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <p style="color:var(--muted);font-size:.85rem"><?= count($prods) ?> produit(s)</p>
  <?php if(gh_has_page_permission('produits','write')): ?>
    <a href="produit_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau produit</a>
  <?php endif; ?>
</div>

<div class="card">
  <table>
    <thead><tr><th>Image</th><th>Nom</th><th>Modèle</th><th>Marque</th><th>Catégorie</th><th>Dispo</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($prods as $pr):
      $img = imgUrl($pr['image']??'');
      $di = dispoInfo($pr['disponibilite']??'en_stock');
    ?>
    <tr>
      <td><?php if($img): ?><img src="<?= e($img) ?>" class="img-prev"/><?php else: ?><div class="img-ph"><i class="fas fa-image"></i></div><?php endif; ?></td>
      <td><strong><?= e($pr['nom']) ?></strong></td>
      <td><?= e($pr['modele']??'') ?></td>
      <td><?= e($pr['marque']??'') ?></td>
      <td><?= e($pr['categories']??'') ?></td>
      <td><span class="badge" style="background:<?= $di['color'] ?>15;color:<?= $di['color'] ?>"><?= $di['label'] ?></span></td>
      <td>
        <a href="?toggle=<?= $pr['id'] ?>">
          <label class="toggle"><input type="checkbox" <?= $pr['statut']?'checked':'' ?> onclick="window.location='?toggle=<?= $pr['id'] ?>'"/><span class="toggle-slider"></span></label>
        </a>
      </td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="produit_form.php?id=<?= $pr['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
          <?php if(gh_has_page_permission('produits','write')): ?>
            <a href="?supprimer=<?= $pr['id'] ?>&_csrf=<?= e(csrf_token('delete_prod')) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce produit ?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require '_bottom.php'; ?>
