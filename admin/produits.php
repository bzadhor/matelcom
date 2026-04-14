<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('produits','read');
$db = getDB();
$pageTitle = 'Produits';
$activePage = 'produits';
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');

if (isset($_GET['supprimer']) && gh_has_page_permission('produits', 'write')) {
    verify_csrf_or_fail('delete_prod');
    $db->prepare("DELETE FROM produits WHERE id=?")->execute([(int)$_GET['supprimer']]);
    setFlash('success', 'Produit supprime.');
    header('Location: produits.php?page='.$page);
    exit;
}

if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE produits SET statut=NOT statut WHERE id=?")->execute([(int)$_GET['toggle']]);
    header('Location: produits.php?page='.$page);
    exit;
}

$whereSql = '';
$params = [];
if ($search !== '') {
    $whereSql = " WHERE nom LIKE ? OR modele LIKE ? OR marque LIKE ? OR slug LIKE ? OR categories LIKE ?";
    $like = '%'.$search.'%';
    $params = [$like, $like, $like, $like, $like];
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM produits".$whereSql);
$countStmt->execute($params);
$totalProduits = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProduits / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$s = $db->prepare("SELECT * FROM produits".$whereSql." ORDER BY ordre ASC, id DESC LIMIT ? OFFSET ?");
$paramIndex = 1;
foreach ($params as $value) {
    $s->bindValue($paramIndex, $value, PDO::PARAM_STR);
    $paramIndex++;
}
$s->bindValue($paramIndex, $perPage, PDO::PARAM_INT);
$s->bindValue($paramIndex + 1, $offset, PDO::PARAM_INT);
$s->execute();
$prods = $s->fetchAll();

require '_top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <p style="color:var(--muted);font-size:.85rem">
    <?= $totalProduits ?> produit(s)
    <?php if ($totalProduits > 0): ?>
      <span style="margin-left:8px">| Page <?= $page ?> / <?= $totalPages ?></span>
    <?php endif; ?>
  </p>
  <?php if (gh_has_page_permission('produits', 'write')): ?>
    <a href="produit_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau produit</a>
  <?php endif; ?>
</div>

<div class="card" style="padding:18px 20px">
  <form method="GET" id="productSearchForm">
    <div class="fg">
      <label>Recherche rapide</label>
      <input type="search" name="q" id="productSearchInput" value="<?= e($search) ?>" placeholder="Nom, modele, marque, categorie..." autocomplete="off"/>
      <small>La recherche se lance automatiquement pendant la saisie.</small>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>Image</th><th>Nom</th><th>Modele</th><th>Marque</th><th>Categorie</th><th>Dispo</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($prods): foreach ($prods as $pr):
      $img = imgUrl($pr['image'] ?? '');
      $di = dispoInfo($pr['disponibilite'] ?? 'en_stock');
    ?>
    <tr>
      <td><?php if ($img): ?><img src="<?= e($img) ?>" class="img-prev"/><?php else: ?><div class="img-ph"><i class="fas fa-image"></i></div><?php endif; ?></td>
      <td><strong><?= e($pr['nom']) ?></strong></td>
      <td><?= e($pr['modele'] ?? '') ?></td>
      <td><?= e($pr['marque'] ?? '') ?></td>
      <td><?= e($pr['categories'] ?? '') ?></td>
      <td><span class="badge" style="background:<?= $di['color'] ?>15;color:<?= $di['color'] ?>"><?= $di['label'] ?></span></td>
      <td>
        <a href="?toggle=<?= $pr['id'] ?>&page=<?= $page ?>&q=<?= urlencode($search) ?>">
          <label class="toggle"><input type="checkbox" <?= $pr['statut'] ? 'checked' : '' ?> onclick="window.location='?toggle=<?= $pr['id'] ?>&page=<?= $page ?>&q=<?= urlencode($search) ?>'"/><span class="toggle-slider"></span></label>
        </a>
      </td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="produit_form.php?id=<?= $pr['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
          <?php if (gh_has_page_permission('produits', 'write')): ?>
            <a href="?supprimer=<?= $pr['id'] ?>&page=<?= $page ?>&q=<?= urlencode($search) ?>&_csrf=<?= e(csrf_token('delete_prod')) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce produit ?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; else: ?>
    <tr>
      <td colspan="8" style="text-align:center;color:var(--muted);padding:28px">Aucun produit trouve.</td>
    </tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="pag">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>"><i class="fas fa-chevron-left"></i></a>
    <?php endif; ?>

    <?php
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    for ($i = $startPage; $i <= $endPage; $i++):
    ?>
      <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>"><i class="fas fa-chevron-right"></i></a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
var productSearchInput = document.getElementById('productSearchInput');
var productSearchForm = document.getElementById('productSearchForm');
var searchTimer = null;

if (productSearchInput && productSearchForm) {
  productSearchInput.addEventListener('input', function () {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () {
      productSearchForm.submit();
    }, 350);
  });
}
</script>

<?php require '_bottom.php'; ?>
