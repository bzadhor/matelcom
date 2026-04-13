<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle  = 'Tableau de bord';
$activePage = 'dashboard';

$nbProd  = $db->query("SELECT COUNT(*) FROM produits WHERE statut=1")->fetchColumn();
$nbCats  = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$nbDevis = $db->query("SELECT COUNT(*) FROM devis")->fetchColumn();
$nbNew   = $db->query("SELECT COUNT(*) FROM devis WHERE statut='nouveau'")->fetchColumn();
$derniers = $db->query("SELECT * FROM devis ORDER BY created_at DESC LIMIT 8")->fetchAll();

$totalVisites   = $db->query("SELECT COUNT(*) FROM visites")->fetchColumn();
$visitesAujourd = $db->query("SELECT COUNT(*) FROM visites WHERE DATE(created_at)=CURDATE()")->fetchColumn();

require '_top.php';
?>

<div class="stats-row">
  <div class="stat-card">
    <div class="ico" style="color:var(--red)"><i class="fas fa-boxes"></i></div>
    <div class="lbl">Produits actifs</div>
    <div class="val"><?= $nbProd ?></div>
    <div class="stat-bg"><i class="fas fa-boxes"></i></div>
  </div>
  <div class="stat-card">
    <div class="ico" style="color:#d97706"><i class="fas fa-file-invoice"></i></div>
    <div class="lbl">Demandes de devis</div>
    <div class="val"><?= $nbDevis ?></div>
    <div class="sub"><?= $nbNew ?> nouveau(x)</div>
    <div class="stat-bg"><i class="fas fa-file-invoice"></i></div>
  </div>
  <div class="stat-card">
    <div class="ico" style="color:#059669"><i class="fas fa-tags"></i></div>
    <div class="lbl">Catégories</div>
    <div class="val"><?= $nbCats ?></div>
    <div class="stat-bg"><i class="fas fa-tags"></i></div>
  </div>
  <div class="stat-card">
    <div class="ico" style="color:#6366f1"><i class="fas fa-eye"></i></div>
    <div class="lbl">Visites totales</div>
    <div class="val"><?= $totalVisites ?></div>
    <div class="sub"><?= $visitesAujourd ?> aujourd'hui</div>
    <div class="stat-bg"><i class="fas fa-eye"></i></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-clock" style="color:var(--red);margin-right:6px"></i> Dernières demandes de devis</h2>
    <a href="devis.php" class="btn btn-secondary btn-sm">Tout voir</a>
  </div>
  <table>
    <thead><tr><th>Nom</th><th>Email</th><th>Produit</th><th>Statut</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($derniers as $d):
      $si = statutDevisInfo($d['statut']);
    ?>
    <tr>
      <td><strong><?= e($d['nom_complet']) ?></strong><?php if($d['societe']): ?><br><small style="color:var(--muted)"><?= e($d['societe']) ?></small><?php endif; ?></td>
      <td><?= e($d['email']) ?></td>
      <td><?= e($d['produits_demandes']??'-') ?></td>
      <td><span class="badge bd-<?= str_replace('badge-','',$si['class']) ?>"><?= $si['label'] ?></span></td>
      <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
      <td><a href="devis.php?voir=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a></td>
    </tr>
    <?php endforeach; ?>
    <?php if(!$derniers): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:28px">Aucune demande pour le moment.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require '_bottom.php'; ?>
