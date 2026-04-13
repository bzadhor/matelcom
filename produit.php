<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/tracker.php';
trackVisite();

$slug = $_GET['slug'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$prod = $slug ? getProduitBySlug($slug) : ($id ? getProduit($id) : null);
if (!$prod) { header('Location: index.php#produits'); exit; }

$p     = allParams();
$specs = jdecode($prod['specifications']);
$tags  = jdecode($prod['tags']);
$imgSrc = imgUrl($prod['image']??'');
$dispo = dispoInfo($prod['disponibilite']??'en_stock');
$gallery = jdecode($prod['images_galerie']);

// Produits similaires
$similaires = getDB()->prepare("SELECT * FROM produits WHERE statut=1 AND id!=? AND categories LIKE ? ORDER BY RAND() LIMIT 4");
$similaires->execute([$prod['id'], '%'.($prod['categories']??'').'%']);
$similaires = $similaires->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($prod['nom']) ?> - <?= e($prod['modele']??'') ?> | <?= e($p['site_nom']??'MATELCOM') ?></title>
<meta name="description" content="<?= e($prod['description_courte']??$prod['nom']) ?>"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--red:#D32F2F;--red-dark:#B71C1C;--red-light:#EF5350;--red-pale:#FFEBEE;--white:#FFF;--gray-50:#FAFAFA;--gray-100:#F5F5F5;--gray-200:#EEE;--gray-300:#E0E0E0;--gray-600:#757575;--gray-800:#424242;--gray-900:#212121;--dark:#1A1A2E;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;color:var(--gray-900);background:var(--gray-50);}
nav{position:fixed;top:0;left:0;right:0;z-index:1000;background:rgba(255,255,255,.97);backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:space-between;padding:0 5%;height:72px;border-bottom:1px solid var(--gray-200);}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo-text{font-family:'Poppins',sans-serif;font-weight:800;font-size:1.5rem;color:var(--gray-900);}
.nav-logo-text span{color:var(--red);}
.nav-back{display:flex;align-items:center;gap:8px;color:var(--gray-600);text-decoration:none;font-size:.88rem;font-weight:500;transition:color .2s;}
.nav-back:hover{color:var(--red);}
.produit-page{max-width:1200px;margin:0 auto;padding:100px 5% 60px;}
.breadcrumb{display:flex;gap:8px;font-size:.82rem;color:var(--gray-600);margin-bottom:28px;flex-wrap:wrap;}
.breadcrumb a{color:var(--red);text-decoration:none;}.breadcrumb a:hover{text-decoration:underline;}
.produit-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;margin-bottom:60px;}
.produit-image{background:white;border-radius:18px;border:1px solid var(--gray-200);padding:32px;display:flex;align-items:center;justify-content:center;min-height:380px;position:relative;}
.produit-image img{max-width:100%;max-height:360px;object-fit:contain;}
.produit-image .badge{position:absolute;top:16px;right:16px;color:white;padding:6px 14px;border-radius:50px;font-size:.78rem;font-weight:700;}
.produit-info h1{font-family:'Poppins',sans-serif;font-size:1.8rem;font-weight:800;color:var(--gray-900);margin-bottom:4px;}
.produit-info .marque{color:var(--red);font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.produit-info .modele{color:var(--gray-600);font-size:1rem;margin-bottom:16px;}
.produit-info .dispo{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:50px;font-size:.82rem;font-weight:600;margin-bottom:20px;}
.produit-info .desc{color:var(--gray-600);font-size:.92rem;line-height:1.75;margin-bottom:28px;}
.produit-tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:28px;}
.produit-tags span{background:var(--red-pale);color:var(--red);padding:4px 12px;border-radius:50px;font-size:.78rem;font-weight:600;}
.produit-cta{display:flex;gap:12px;flex-wrap:wrap;}
.cta-devis{background:var(--red);color:white;padding:14px 32px;border-radius:10px;font-weight:700;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;box-shadow:0 4px 16px rgba(211,47,47,.3);}
.cta-devis:hover{background:var(--red-dark);transform:translateY(-2px);}
.cta-wa{background:#25D366;color:white;padding:14px 32px;border-radius:10px;font-weight:700;font-size:.95rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:all .2s;}
.cta-wa:hover{background:#1ea952;transform:translateY(-2px);}
/* Specs */
.specs-section{margin-bottom:48px;}
.specs-section h2{font-family:'Poppins',sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:20px;color:var(--gray-900);}
.specs-table{width:100%;background:white;border-radius:14px;overflow:hidden;border:1px solid var(--gray-200);}
.specs-table tr:nth-child(even){background:var(--gray-50);}
.specs-table td{padding:14px 20px;font-size:.88rem;border-bottom:1px solid var(--gray-200);}
.specs-table td:first-child{font-weight:600;color:var(--gray-800);width:200px;}
.specs-table td:last-child{color:var(--gray-600);}
.specs-table tr:last-child td{border-bottom:none;}
/* Description longue */
.desc-long{background:white;border-radius:14px;border:1px solid var(--gray-200);padding:32px;margin-bottom:48px;}
.desc-long h2{font-family:'Poppins',sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:16px;}
.desc-long p{color:var(--gray-600);font-size:.9rem;line-height:1.8;margin-bottom:12px;}
/* Similaires */
.similaires h2{font-family:'Poppins',sans-serif;font-size:1.3rem;font-weight:800;margin-bottom:20px;}
.sim-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;}
.sim-card{background:white;border-radius:14px;overflow:hidden;border:1px solid var(--gray-200);transition:all .3s;text-decoration:none;color:inherit;}
.sim-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.08);}
.sim-img{height:140px;display:flex;align-items:center;justify-content:center;background:var(--gray-100);padding:12px;}
.sim-img img{max-height:100%;object-fit:contain;}
.sim-body{padding:16px;}
.sim-body h3{font-family:'Poppins',sans-serif;font-size:.88rem;font-weight:700;margin-bottom:2px;}
.sim-body p{font-size:.78rem;color:var(--gray-600);}
footer{background:var(--gray-900);color:rgba(255,255,255,.5);padding:30px 5%;text-align:center;font-size:.82rem;margin-top:40px;}
footer a{color:var(--red-light);text-decoration:none;}
@media(max-width:768px){.produit-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<nav>
  <a href="index.php" class="nav-logo"><span class="nav-logo-text">MATEL<span>COM</span></span></a>
  <a href="index.php#produits" class="nav-back"><i class="fas fa-arrow-left"></i> Retour aux produits</a>
</nav>

<div class="produit-page">
  <div class="breadcrumb">
    <a href="index.php">Accueil</a> <span>/</span>
    <a href="index.php#produits">Produits</a> <span>/</span>
    <span><?= e($prod['nom']) ?></span>
  </div>

  <div class="produit-grid">
    <div class="produit-image">
      <?php if($imgSrc): ?><img src="<?= e($imgSrc) ?>" alt="<?= e($prod['nom']) ?>"/>
      <?php else: ?><i class="fas fa-box-open" style="font-size:5rem;color:var(--gray-300)"></i><?php endif; ?>
      <?php if ($prod['badge_texte']): ?>
        <span class="badge" style="background:<?= e($prod['badge_couleur']??'#D32F2F') ?>"><?= e($prod['badge_texte']) ?></span>
      <?php endif; ?>
    </div>
    <div class="produit-info">
      <div class="marque"><?= e($prod['marque']??'') ?></div>
      <h1><?= e($prod['nom']) ?></h1>
      <div class="modele"><?= e($prod['modele']??'') ?></div>
      <div class="dispo" style="background:<?= $dispo['color'] ?>15;color:<?= $dispo['color'] ?>"><i class="fas fa-circle" style="font-size:.5rem"></i> <?= $dispo['label'] ?></div>
      <p class="desc"><?= e($prod['description_courte']??'') ?></p>
      <div class="produit-tags">
        <?php foreach ($tags as $t): ?><span><?= e($t) ?></span><?php endforeach; ?>
      </div>
      <div class="produit-cta">
        <a href="index.php#contact" class="cta-devis"><i class="fas fa-file-invoice"></i> Demander un devis</a>
        <a href="<?= e(whatsappLink($prod['nom'].' '.$prod['modele'])) ?>" target="_blank" rel="noopener" class="cta-wa"><i class="fab fa-whatsapp"></i> WhatsApp</a>
      </div>
    </div>
  </div>

  <?php if ($specs): ?>
  <div class="specs-section">
    <h2><i class="fas fa-list-alt" style="color:var(--red);margin-right:8px"></i> Caractéristiques techniques</h2>
    <table class="specs-table">
      <?php foreach ($specs as $spec): ?>
      <tr><td><?= e($spec['label']??'') ?></td><td><?= e($spec['value']??'') ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($prod['description']): ?>
  <div class="desc-long">
    <h2><i class="fas fa-info-circle" style="color:var(--red);margin-right:8px"></i> Description détaillée</h2>
    <?php foreach (explode("\n", $prod['description']) as $para): $para=trim($para); if($para): ?>
      <p><?= e($para) ?></p>
    <?php endif; endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($similaires): ?>
  <div class="similaires">
    <h2><i class="fas fa-th-large" style="color:var(--red);margin-right:8px"></i> Produits similaires</h2>
    <div class="sim-grid">
      <?php foreach ($similaires as $sim): ?>
      <a href="produit.php?slug=<?= e($sim['slug']??'') ?>" class="sim-card">
        <div class="sim-img">
          <?php $si=imgUrl($sim['image']??''); if($si): ?><img src="<?= e($si) ?>" alt="<?= e($sim['nom']) ?>"/>
          <?php else: ?><i class="fas fa-box-open" style="font-size:2rem;color:var(--gray-400)"></i><?php endif; ?>
        </div>
        <div class="sim-body">
          <h3><?= e($sim['nom']) ?></h3>
          <p><?= e($sim['modele']??'') ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer>
  <p>© <?= date('Y') ?> <?= e($p['site_nom']??'MATELCOM') ?>. Tous droits réservés. <a href="index.php">Retour à l'accueil</a></p>
</footer>
</body>
</html>
