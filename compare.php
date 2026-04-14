<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')))));
$produits = getProduitsByIds($ids);
$p = allParams();

if (!$produits) {
    header('Location: index.php#produits');
    exit;
}

$specLabels = [];
foreach ($produits as $prod) {
    foreach (jdecode($prod['specifications']) as $spec) {
        $label = trim((string)($spec['label'] ?? ''));
        if ($label !== '' && !in_array($label, $specLabels, true)) {
            $specLabels[] = $label;
        }
    }
}

function compareSpecValue(array $product, string $wantedLabel): string {
    foreach (jdecode($product['specifications']) as $spec) {
        if (($spec['label'] ?? '') === $wantedLabel) {
            return (string)($spec['value'] ?? '');
        }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Comparateur Produits | <?= e($p['site_nom'] ?? 'MATELCOM') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--red:#D32F2F;--red-dark:#B71C1C;--red-pale:#FFEBEE;--ink:#1f2937;--muted:#6b7280;--line:#e5e7eb;--soft:#f8fafc;--white:#fff;--dark:#111827;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:linear-gradient(180deg,#f5f7fb 0%,#eef2f7 100%);color:var(--ink);}
.page{max-width:1400px;margin:0 auto;padding:42px 20px 56px;}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;flex-wrap:wrap;}
.back-link{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--red);font-weight:700;}
.top-actions{display:flex;gap:10px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:12px 18px;font-weight:700;text-decoration:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:.9rem;}
.btn-primary{background:var(--red);color:#fff;box-shadow:0 12px 26px rgba(211,47,47,.22);}
.btn-primary:hover{background:var(--red-dark);}
.btn-secondary{background:#fff;border:1px solid var(--line);color:var(--ink);}
.hero{background:radial-gradient(circle at top right,rgba(211,47,47,.16),transparent 36%),linear-gradient(135deg,#101827,#1f2937 60%,#293548 100%);border-radius:28px;padding:32px;box-shadow:0 24px 60px rgba(15,23,42,.16);color:#fff;margin-bottom:24px;}
.hero h1{font-family:'Poppins',sans-serif;font-size:clamp(2rem,4vw,3rem);font-weight:800;margin-bottom:10px;}
.hero p{color:rgba(255,255,255,.72);max-width:760px;line-height:1.7;}
.compare-meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;}
.meta-pill{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);padding:8px 12px;border-radius:999px;font-size:.8rem;}
.table-wrap{background:#fff;border:1px solid rgba(229,231,235,.9);border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.07);overflow:auto;}
.compare-table{width:100%;border-collapse:separate;border-spacing:0;min-width:980px;}
.compare-table th,.compare-table td{padding:18px 16px;border-bottom:1px solid var(--line);vertical-align:top;}
.compare-table thead th{position:sticky;top:0;background:rgba(255,255,255,.97);backdrop-filter:blur(12px);z-index:5;}
.compare-table thead th:first-child{min-width:220px;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);}
.compare-product-head{min-width:240px;}
.compare-card{display:flex;flex-direction:column;gap:14px;}
.compare-image{height:190px;background:var(--soft);border:1px solid var(--line);border-radius:18px;display:flex;align-items:center;justify-content:center;padding:14px;}
.compare-image img{max-width:100%;max-height:100%;object-fit:contain;}
.compare-brand{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:var(--red);font-weight:800;}
.compare-name{font-family:'Poppins',sans-serif;font-size:1rem;font-weight:800;color:var(--ink);}
.compare-model{font-size:.84rem;color:var(--muted);}
.compare-actions{display:flex;gap:10px;flex-wrap:wrap;}
.mini-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;text-decoration:none;font-size:.82rem;font-weight:700;}
.mini-btn.details{background:var(--red-pale);color:var(--red);}
.mini-btn.wa{background:#dcfce7;color:#166534;}
.row-label{font-size:.84rem;font-weight:800;color:var(--ink);background:#fbfcfd;}
.cell-value{font-size:.88rem;color:var(--ink);line-height:1.65;}
.muted{color:var(--muted);}
.tag-list{display:flex;flex-wrap:wrap;gap:6px;}
.tag{background:var(--red-pale);color:var(--red);padding:4px 10px;border-radius:999px;font-size:.72rem;font-weight:700;}
.dispo{display:inline-flex;padding:6px 12px;border-radius:999px;font-size:.76rem;font-weight:800;}
@media(max-width:700px){
  .page{padding:24px 12px 40px;}
  .hero{padding:24px 18px;}
}
</style>
</head>
<body>
<div class="page">
  <div class="topbar">
    <a href="index.php#produits" class="back-link"><i class="fas fa-arrow-left"></i> Retour au catalogue</a>
    <div class="top-actions">
      <a href="index.php#produits" class="btn btn-secondary"><i class="fas fa-plus"></i> Ajouter d'autres produits</a>
      <button type="button" class="btn btn-primary" id="clearCompareBtn"><i class="fas fa-trash"></i> Vider la comparaison</button>
    </div>
  </div>

  <section class="hero">
    <h1>Comparateur professionnel</h1>
    <p>Analysez plusieurs produits dans un seul tableau clair, elegant et orienté décision. Comparez les fiches techniques, la disponibilité, les tags et les informations essentielles sans perdre le contexte.</p>
    <div class="compare-meta">
      <span class="meta-pill"><i class="fas fa-layer-group"></i> <?= count($produits) ?> produit(s) compares</span>
      <span class="meta-pill"><i class="fas fa-table-columns"></i> Tableau de comparaison multi-produits</span>
      <span class="meta-pill"><i class="fas fa-bolt"></i> Vue rapide des differences techniques</span>
    </div>
  </section>

  <div class="table-wrap">
    <table class="compare-table">
      <thead>
        <tr>
          <th>Critere</th>
          <?php foreach ($produits as $prod): $img = imgUrl($prod['image'] ?? ''); ?>
            <th class="compare-product-head">
              <div class="compare-card">
                <div class="compare-image">
                  <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($prod['nom']) ?>"/><?php else: ?><i class="fas fa-box-open" style="font-size:3rem;color:#cbd5e1"></i><?php endif; ?>
                </div>
                <div>
                  <div class="compare-brand"><?= e($prod['marque'] ?? 'Produit') ?></div>
                  <div class="compare-name"><?= e($prod['nom']) ?></div>
                  <div class="compare-model"><?= e($prod['modele'] ?? '') ?></div>
                </div>
                <div class="compare-actions">
                  <a class="mini-btn details" href="produit.php?slug=<?= e($prod['slug'] ?? '') ?>"><i class="fas fa-eye"></i> Voir</a>
                  <a class="mini-btn wa" href="<?= e(whatsappLink($prod['nom'].' '.$prod['modele'])) ?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                </div>
              </div>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="row-label">Disponibilite</td>
          <?php foreach ($produits as $prod): $dispo = dispoInfo($prod['disponibilite'] ?? 'en_stock'); ?>
            <td class="cell-value"><span class="dispo" style="background:<?= e($dispo['color']) ?>15;color:<?= e($dispo['color']) ?>"><?= e($dispo['label']) ?></span></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="row-label">Categories</td>
          <?php foreach ($produits as $prod): ?>
            <td class="cell-value"><?= e($prod['categories'] ?? '') ?: '<span class="muted">-</span>' ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="row-label">Description courte</td>
          <?php foreach ($produits as $prod): ?>
            <td class="cell-value"><?= e($prod['description_courte'] ?? '') ?: '<span class="muted">-</span>' ?></td>
          <?php endforeach; ?>
        </tr>
        <tr>
          <td class="row-label">Tags</td>
          <?php foreach ($produits as $prod): $tags = jdecode($prod['tags']); ?>
            <td class="cell-value">
              <?php if ($tags): ?>
                <div class="tag-list">
                  <?php foreach ($tags as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php foreach ($specLabels as $label): ?>
          <tr>
            <td class="row-label"><?= e($label) ?></td>
            <?php foreach ($produits as $prod): $value = compareSpecValue($prod, $label); ?>
              <td class="cell-value"><?= $value !== '' ? e($value) : '<span class="muted">-</span>' ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.getElementById('clearCompareBtn').addEventListener('click', function () {
  localStorage.removeItem('matelcom_compare_products');
  window.location.href = 'index.php#produits';
});
</script>
</body>
</html>
