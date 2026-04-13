<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('devis','read');
$db = getDB();
$pageTitle  = 'Devis reçus';
$activePage = 'devis';

// Changer statut
if (isset($_POST['changer_statut']) && gh_has_page_permission('devis','write')) {
    verify_csrf_or_fail('devis_statut');
    $did = (int)($_POST['devis_id']??0);
    $ns = $_POST['nouveau_statut']??'';
    $note = $_POST['note_admin']??'';
    if (in_array($ns,['nouveau','en_cours','traite','archive'])) {
        $db->prepare("UPDATE devis SET statut=?,note_admin=? WHERE id=?")->execute([$ns,$note,$did]);
        setFlash('success','Statut mis à jour.');
    }
    header('Location: devis.php'); exit;
}

// Supprimer
if (isset($_GET['supprimer']) && gh_has_page_permission('devis','write')) {
    $db->prepare("DELETE FROM devis WHERE id=?")->execute([(int)$_GET['supprimer']]);
    setFlash('success','Devis supprimé.');
    header('Location: devis.php'); exit;
}

// Filtre
$filtre = $_GET['statut']??'';
$sql = "SELECT * FROM devis";
$params = [];
if ($filtre && in_array($filtre,['nouveau','en_cours','traite','archive'])) {
    $sql .= " WHERE statut=?"; $params[] = $filtre;
}
$sql .= " ORDER BY created_at DESC";
$devis = $db->prepare($sql); $devis->execute($params); $devis = $devis->fetchAll();

// Voir détail
$voir = null;
if (isset($_GET['voir'])) {
    $s = $db->prepare("SELECT * FROM devis WHERE id=?"); $s->execute([(int)$_GET['voir']]); $voir = $s->fetch();
}

require '_top.php';
?>

<?php if ($voir): ?>
<a href="devis.php" class="btn btn-secondary btn-sm" style="margin-bottom:18px"><i class="fas fa-arrow-left"></i> Retour</a>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:22px">
  <div class="card" style="padding:28px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-file-invoice" style="color:var(--red);margin-right:6px"></i> Demande #<?= $voir['id'] ?></h3>
    <table style="min-width:auto">
      <tr><td style="font-weight:600;width:160px">Nom</td><td><?= e($voir['nom_complet']) ?></td></tr>
      <tr><td style="font-weight:600">Société</td><td><?= e($voir['societe']??'-') ?></td></tr>
      <tr><td style="font-weight:600">Email</td><td><a href="mailto:<?= e($voir['email']) ?>"><?= e($voir['email']) ?></a></td></tr>
      <tr><td style="font-weight:600">Téléphone</td><td><?= e($voir['telephone']??'-') ?></td></tr>
      <tr><td style="font-weight:600">Produits</td><td><?= e($voir['produits_demandes']??'-') ?></td></tr>
      <tr><td style="font-weight:600">Quantité</td><td><?= e($voir['quantite']??'-') ?></td></tr>
      <tr><td style="font-weight:600">Message</td><td><?= nl2br(e($voir['message']??'-')) ?></td></tr>
      <tr><td style="font-weight:600">Date</td><td><?= date('d/m/Y à H:i', strtotime($voir['created_at'])) ?></td></tr>
      <tr><td style="font-weight:600">IP</td><td><?= e($voir['ip_address']??'') ?></td></tr>
      <?php if($voir['fichier']): ?>
      <tr><td style="font-weight:600">Fichier</td><td><a href="<?= UPLOAD_URL.e($voir['fichier']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Télécharger</a></td></tr>
      <?php endif; ?>
    </table>
  </div>
  <?php if(gh_has_page_permission('devis','write')): ?>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-edit" style="color:var(--red);margin-right:6px"></i> Modifier le statut</h3>
    <form method="POST">
      <?= csrf_input('devis_statut') ?>
      <input type="hidden" name="changer_statut" value="1"/>
      <input type="hidden" name="devis_id" value="<?= $voir['id'] ?>"/>
      <div class="fg" style="margin-bottom:14px"><label>Statut</label>
        <select name="nouveau_statut">
          <?php foreach(['nouveau','en_cours','traite','archive'] as $st): $si=statutDevisInfo($st); ?>
            <option value="<?= $st ?>" <?= $voir['statut']===$st?'selected':'' ?>><?= $si['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg" style="margin-bottom:14px"><label>Note admin</label><textarea name="note_admin"><?= e($voir['note_admin']??'') ?></textarea></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="devis.php" class="btn <?= !$filtre?'btn-primary':'btn-secondary' ?> btn-sm">Tous (<?= count($devis) ?>)</a>
    <?php foreach(['nouveau'=>'Nouveau','en_cours'=>'En cours','traite'=>'Traité','archive'=>'Archivé'] as $k=>$v): ?>
      <a href="?statut=<?= $k ?>" class="btn <?= $filtre===$k?'btn-primary':'btn-secondary' ?> btn-sm"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <table>
    <thead><tr><th>Nom</th><th>Email / Tél</th><th>Produit</th><th>Statut</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($devis as $d):
      $si = statutDevisInfo($d['statut']);
    ?>
    <tr>
      <td><strong><?= e($d['nom_complet']) ?></strong><?php if($d['societe']): ?><br><small style="color:var(--muted)"><?= e($d['societe']) ?></small><?php endif; ?></td>
      <td><?= e($d['email']) ?><br><small><?= e($d['telephone']??'') ?></small></td>
      <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($d['produits_demandes']??'-') ?></td>
      <td><span class="badge bd-<?= str_replace('badge-','',$si['class']) ?>"><?= $si['label'] ?></span></td>
      <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="?voir=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
          <?php if(gh_has_page_permission('devis','write')): ?>
            <a href="?supprimer=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(!$devis): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:28px">Aucun devis.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require '_bottom.php'; ?>
