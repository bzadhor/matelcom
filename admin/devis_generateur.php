<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('devis_generateur', 'read');

$db         = getDB();
$pageTitle  = 'Générateur de devis';
$activePage = 'devis_generateur';
$canWrite   = gh_has_page_permission('devis_generateur', 'write');

// Charger produits actifs
$produits = $db->query("SELECT id,nom,modele,statut FROM produits ORDER BY ordre ASC")->fetchAll();

// Numéro auto
$lastNum = $db->query("SELECT MAX(CAST(SUBSTRING(numero,4) AS UNSIGNED)) FROM devis_generes WHERE numero LIKE 'DV%'")->fetchColumn();
$nextNum = 'DV' . str_pad(($lastNum ?? 0) + 1, 4, '0', STR_PAD_LEFT);

// ── Suppression ──────────────────────────────────────────────
if (isset($_GET['supprimer'])) {
    gh_require_permission('devis_generateur', 'write');
    $db->prepare("DELETE FROM devis_generes WHERE id=?")->execute([(int)$_GET['supprimer']]);
    setFlash('success', 'Devis supprimé avec succès.');
    redirect(ADMIN_URL . '/devis_generateur.php');
}

// ── Sauvegarde ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder'])) {
    gh_require_permission('devis_generateur', 'write');
    verify_csrf_or_fail('devis_gen');

    $data = [
        'numero'           => limitString($_POST['numero']           ?? $nextNum, 30),
        'client_nom'       => limitString($_POST['client_nom']       ?? '', 200),
        'client_societe'   => limitString($_POST['client_societe']   ?? '', 200),
        'client_adresse'   => limitString($_POST['client_adresse']   ?? '', 500),
        'client_telephone' => limitString($_POST['client_telephone'] ?? '', 50),
        'client_email'     => limitString($_POST['client_email']     ?? '', 150),
        'lignes'           => $_POST['lignes']          ?? '[]',
        'tva'              => (float)($_POST['tva']             ?? 20),
        'remise_globale'   => (float)($_POST['remise_globale']   ?? 0),
        'conditions'       => limitString($_POST['conditions']   ?? '', 1000),
        'notes'            => limitString($_POST['notes']        ?? '', 1000),
        'date_validite'    => $_POST['date_validite'] ?? null,
        'statut'           => 'brouillon',
    ];

    $editId = (int)($_POST['edit_id'] ?? 0);

    if ($editId) {
        $db->prepare("UPDATE devis_generes SET numero=?,client_nom=?,client_societe=?,client_adresse=?,client_telephone=?,client_email=?,lignes=?,tva=?,remise_globale=?,conditions=?,notes=?,date_validite=? WHERE id=?")
           ->execute([
               $data['numero'], $data['client_nom'], $data['client_societe'], $data['client_adresse'],
               $data['client_telephone'], $data['client_email'], $data['lignes'], $data['tva'],
               $data['remise_globale'], $data['conditions'], $data['notes'], $data['date_validite'],
               $editId
           ]);
        setFlash('success', '✅ Devis ' . $data['numero'] . ' mis à jour.');
        redirect(ADMIN_URL . '/devis_generateur.php?voir=' . $editId);
    }

    $db->prepare("INSERT INTO devis_generes(numero,client_nom,client_societe,client_adresse,client_telephone,client_email,lignes,tva,remise_globale,conditions,notes,date_validite,statut) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute(array_values($data));
    $newId = $db->lastInsertId();

    setFlash('success', '✅ Devis ' . $data['numero'] . ' sauvegardé.');
    redirect(ADMIN_URL . '/devis_generateur.php?voir=' . $newId);
}

// ── Mode modification ─────────────────────────────────────────
$devisEdit = null;
if (isset($_GET['modifier'])) {
    $s = $db->prepare("SELECT * FROM devis_generes WHERE id=?");
    $s->execute([(int)$_GET['modifier']]);
    $devisEdit = $s->fetch();
}

// ── Pré-remplir depuis devis reçu ────────────────────────────
$devisSource = null;
if (isset($_GET['from_devis'])) {
    $s = $db->prepare("SELECT * FROM devis WHERE id=?");
    $s->execute([(int)$_GET['from_devis']]);
    $devisSource = $s->fetch();
}

// ── Vue détail ───────────────────────────────────────────────
$devisVoir = null;
if (isset($_GET['voir'])) {
    $s = $db->prepare("SELECT * FROM devis_generes WHERE id=?");
    $s->execute([(int)$_GET['voir']]);
    $devisVoir = $s->fetch();
}

// ── Liste avec recherche & pagination ────────────────────────
$pp      = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$q       = trim($_GET['q'] ?? '');
$where   = '1=1';
$params  = [];
if ($q) {
    $where  = "(numero LIKE ? OR client_nom LIKE ? OR client_societe LIKE ? OR client_email LIKE ? OR DATE_FORMAT(created_at,'%d/%m/%Y') LIKE ?)";
    $like   = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like];
}
$totalStmt = $db->prepare("SELECT COUNT(*) FROM devis_generes WHERE $where");
$totalStmt->execute($params);
$total      = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($total / $pp);
$listeStmt  = $db->prepare("SELECT * FROM devis_generes WHERE $where ORDER BY created_at DESC LIMIT $pp OFFSET " . (($page - 1) * $pp));
$listeStmt->execute($params);
$liste = $listeStmt->fetchAll();

require '_top.php';
?>

<!-- Select2 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js"></script>

<style>
/* ── Select2 ── */
.select2-container{width:100%!important;}
.select2-container--default .select2-selection--single{height:42px;border:1.5px solid var(--border);border-radius:8px;font-family:'Inter',sans-serif;}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:40px;padding-left:12px;color:var(--ink);font-size:.85rem;}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:40px;}
.select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--red);box-shadow:0 0 0 3px rgba(211,47,47,.07);}
.select2-dropdown{border:1.5px solid var(--red);border-radius:8px;font-family:'Inter',sans-serif;font-size:.85rem;box-shadow:0 8px 24px rgba(0,0,0,.1);}
.select2-container--default .select2-results__option--highlighted{background:var(--red)!important;}
.select2-search--dropdown .select2-search__field{border:1.5px solid var(--border);border-radius:6px;padding:7px 10px;font-family:'Inter',sans-serif;font-size:.84rem;outline:none;}
.badge-inactif{font-size:.67rem;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:4px;margin-left:6px;vertical-align:middle;}

/* ── Sections formulaire ── */
.dg-section{background:white;border-radius:12px;border:1px solid var(--border);margin-bottom:18px;overflow:hidden;}
.dg-sh{padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#fafafa;}
.dg-sh h3{font-size:.88rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;font-family:'Poppins',sans-serif;}
.dg-sh h3 i{color:var(--red);}
.dg-body{padding:20px;}
.dg-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

/* ── Lignes produits ── */
.ligne-row{display:grid;grid-template-columns:2fr 80px 120px 90px 40px;gap:7px;align-items:center;margin-bottom:7px;}
.ligne-row input{padding:8px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:.82rem;font-family:'Inter',sans-serif;outline:none;transition:border-color .2s;width:100%;}
.ligne-row input:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(211,47,47,.06);}
.ligne-del{background:none;border:none;color:#ef4444;cursor:pointer;font-size:.9rem;padding:4px 6px;border-radius:6px;transition:background .2s;}
.ligne-del:hover{background:#fee2e2;}

/* ── Récap totaux ── */
.total-box{background:#fafafa;border:1px solid var(--border);border-radius:10px;padding:14px 18px;}
.total-row{display:flex;justify-content:space-between;font-size:.85rem;padding:5px 0;border-bottom:1px solid #f0f0f0;}
.total-row:last-child{border-bottom:none;font-weight:700;font-size:.95rem;color:var(--red);}

/* ── Badges statut ── */
.st-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.st-brouillon{background:#f1f5f9;color:#64748b;}
.st-envoye{background:#e0f2fe;color:#0369a1;}
.st-accepte{background:#d1fae5;color:#059669;}
.st-refuse{background:#fee2e2;color:#dc2626;}

/* ── Vue devis ── */
.dv-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.dv-block-label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.dv-block-value{font-size:.87rem;line-height:1.8;color:var(--ink);}

/* ── Bouton envoyer ── */
.btn-envoyer{width:100%;justify-content:center;padding:12px;font-size:.92rem;margin-top:8px;background:linear-gradient(135deg,#059669,#22c55e);color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:opacity .2s;}
.btn-envoyer:hover{opacity:.9;}

@media(max-width:768px){.dg-grid{grid-template-columns:1fr!important;}.ligne-row{grid-template-columns:1fr 60px 90px 70px 36px!important;}}
</style>

<?php if ($devisVoir): ?>
<!-- ════ VUE DÉTAIL DEVIS ════ -->
<div style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap">
  <a href="devis_generateur.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
  <a href="devis_pdf.php?id=<?= $devisVoir['id'] ?>" target="_blank" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Télécharger PDF</a>
  <?php if ($canWrite): ?>
    <a href="?modifier=<?= $devisVoir['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Modifier</a>
  <?php endif; ?>
</div>

<div class="dg-section">
  <div class="dg-sh">
    <h3><i class="fas fa-file-invoice-dollar"></i> Devis <?= e($devisVoir['numero']) ?></h3>
    <span class="st-badge st-<?= e($devisVoir['statut']) ?>"><?= ucfirst(e($devisVoir['statut'])) ?></span>
  </div>
  <div class="dg-body">
    <div class="dv-meta-grid">
      <div>
        <div class="dv-block-label">Client</div>
        <div class="dv-block-value">
          <strong><?= e($devisVoir['client_nom']) ?></strong><br/>
          <?php if ($devisVoir['client_societe']): ?><?= e($devisVoir['client_societe']) ?><br/><?php endif; ?>
          <?php if ($devisVoir['client_adresse']): ?><?= nl2br(e($devisVoir['client_adresse'])) ?><br/><?php endif; ?>
          <?php if ($devisVoir['client_telephone']): ?><i class="fas fa-phone" style="color:var(--muted);font-size:.75rem"></i> <?= e($devisVoir['client_telephone']) ?><br/><?php endif; ?>
          <?php if ($devisVoir['client_email']): ?><i class="fas fa-envelope" style="color:var(--muted);font-size:.75rem"></i> <?= e($devisVoir['client_email']) ?><?php endif; ?>
        </div>
      </div>
      <div>
        <div class="dv-block-label">Informations</div>
        <div class="dv-block-value">
          <strong>N°</strong> <?= e($devisVoir['numero']) ?><br/>
          <strong>Date :</strong> <?= date('d/m/Y', strtotime($devisVoir['created_at'])) ?><br/>
          <?php if ($devisVoir['date_validite']): ?><strong>Valide jusqu'au :</strong> <?= date('d/m/Y', strtotime($devisVoir['date_validite'])) ?><br/><?php endif; ?>
          <?php if ($devisVoir['conditions']): ?><strong>Conditions :</strong> <?= e($devisVoir['conditions']) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <?php
      $lignes  = json_decode($devisVoir['lignes'] ?? '[]', true);
      $tva     = (float)$devisVoir['tva'];
      $remise  = (float)$devisVoir['remise_globale'];
      $totalHT = 0;
      foreach ($lignes as $l) $totalHT += ($l['prix'] ?? 0) * ($l['qte'] ?? 1) * (1 - ($l['remise'] ?? 0) / 100);
      $totalHT_r = $totalHT * (1 - $remise / 100);
      $totalTVA  = $totalHT_r * $tva / 100;
      $totalTTC  = $totalHT_r + $totalTVA;
    ?>
    <?php if (!empty($lignes)): ?>
    <table style="margin-top:16px">
      <thead><tr><th>Désignation</th><th>Qté</th><th>Prix unitaire HT</th><th>Remise</th><th style="text-align:right">Total HT</th></tr></thead>
      <tbody>
        <?php foreach ($lignes as $l): ?>
        <tr>
          <td>
            <strong><?= e($l['nom'] ?? '') ?></strong>
            <?php if (!empty($l['ref'])): ?><br><small style="color:var(--muted)"><?= e($l['ref']) ?></small><?php endif; ?>
          </td>
          <td><?= (int)($l['qte'] ?? 1) ?></td>
          <td><?= number_format((float)($l['prix'] ?? 0), 2, ',', ' ') ?> MAD</td>
          <td><?= (int)($l['remise'] ?? 0) ?> %</td>
          <td style="text-align:right;font-weight:600"><?= number_format(($l['prix'] ?? 0) * ($l['qte'] ?? 1) * (1 - ($l['remise'] ?? 0) / 100), 2, ',', ' ') ?> MAD</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <div class="total-box" style="min-width:270px">
        <div class="total-row"><span>Total HT</span><span><?= number_format($totalHT, 2, ',', ' ') ?> MAD</span></div>
        <?php if ($remise > 0): ?><div class="total-row"><span>Remise (<?= $remise ?>%)</span><span>- <?= number_format($totalHT - $totalHT_r, 2, ',', ' ') ?> MAD</span></div><?php endif; ?>
        <div class="total-row"><span>TVA (<?= $tva ?>%)</span><span><?= number_format($totalTVA, 2, ',', ' ') ?> MAD</span></div>
        <div class="total-row"><span>Total TTC</span><span><?= number_format($totalTTC, 2, ',', ' ') ?> MAD</span></div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($devisVoir['notes']): ?>
      <div style="margin-top:14px;padding:11px 14px;background:#fafafa;border-radius:8px;font-size:.84rem;color:var(--muted)">
        <strong>Notes :</strong> <?= nl2br(e($devisVoir['notes'])) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ════ FORMULAIRE NOUVEAU / MODIFIER ════ -->
<?php if ($devisEdit): ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:11px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
  <i class="fas fa-edit" style="color:#f59e0b"></i>
  <span style="font-size:.84rem;color:#92400e">Modification du devis <strong><?= e($devisEdit['numero']) ?></strong></span>
  <a href="devis_generateur.php" style="margin-left:auto;font-size:.8rem;color:#92400e;text-decoration:none">✕ Annuler</a>
</div>
<?php elseif ($devisSource): ?>
<div style="background:#e0f2fe;border:1px solid #38bdf8;border-radius:10px;padding:11px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
  <i class="fas fa-user-check" style="color:#0284c7"></i>
  <span style="font-size:.84rem;color:#0c4a6e">Pré-rempli depuis la demande de <strong><?= e($devisSource['nom_complet']) ?></strong></span>
</div>
<?php endif; ?>

<?php if ($canWrite): ?><form method="POST"><?php endif; ?>
  <?= csrf_input('devis_gen') ?>
  <?php if ($devisEdit): ?><input type="hidden" name="edit_id" value="<?= $devisEdit['id'] ?>"/><?php endif; ?>
  <?php if ($devisSource): ?><input type="hidden" name="from_devis_id" value="<?= $devisSource['id'] ?>"/><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

    <!-- ── Colonne principale ── -->
    <div>

      <!-- Infos client -->
      <div class="dg-section">
        <div class="dg-sh"><h3><i class="fas fa-user"></i>Informations client</h3></div>
        <div class="dg-body">
          <div class="dg-grid" style="margin-bottom:14px">
            <div class="fg">
              <label>Nom &amp; Prénom *</label>
              <input type="text" name="client_nom" value="<?= $devisEdit ? e($devisEdit['client_nom']) : ($devisSource ? e($devisSource['nom_complet']) : '') ?>" placeholder="Mohamed Alami" required/>
            </div>
            <div class="fg">
              <label>Société / Établissement</label>
              <input type="text" name="client_societe" value="<?= $devisEdit ? e($devisEdit['client_societe']) : ($devisSource ? e($devisSource['societe'] ?? '') : '') ?>" placeholder="Entreprise, Administration..."/>
            </div>
            <div class="fg">
              <label>Téléphone</label>
              <input type="tel" name="client_telephone" value="<?= $devisEdit ? e($devisEdit['client_telephone']) : ($devisSource ? e($devisSource['telephone'] ?? '') : '') ?>" placeholder="+212 6XX XXX XXX"/>
            </div>
            <div class="fg">
              <label>Email</label>
              <input type="email" name="client_email" value="<?= $devisEdit ? e($devisEdit['client_email']) : ($devisSource ? e($devisSource['email'] ?? '') : '') ?>" placeholder="client@exemple.com"/>
            </div>
          </div>
          <div class="fg">
            <label>Adresse complète</label>
            <textarea name="client_adresse" rows="2" placeholder="Adresse, Ville..."><?= $devisEdit ? e($devisEdit['client_adresse']) : '' ?></textarea>
          </div>
        </div>
      </div>

      <!-- Lignes produits -->
      <div class="dg-section">
        <div class="dg-sh">
          <h3><i class="fas fa-boxes"></i>Produits &amp; Services</h3>
          <span style="font-size:.74rem;color:var(--muted)"><?php if($canWrite): ?>Saisissez manuellement ou choisissez depuis le catalogue<?php endif; ?></span>
        </div>
        <div class="dg-body">
          <!-- En-têtes colonnes -->
          <div style="display:grid;grid-template-columns:2fr 75px 115px 85px 38px;gap:7px;margin-bottom:5px">
            <div style="font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase;padding-left:2px">Désignation</div>
            <div style="font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase">Qté</div>
            <div style="font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase">Prix HT (MAD)</div>
            <div style="font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase">Remise %</div>
            <div></div>
          </div>

          <!-- Lignes existantes -->
          <div id="lignesContainer"></div>
          <input type="hidden" name="lignes" id="lignesJson" value="[]"/>

          <?php if ($canWrite): ?>
          <!-- ── Mini-formulaire ajout manuel ── -->
          <div style="margin-top:14px;border-top:1px dashed var(--border);padding-top:14px">
            <div style="font-size:.75rem;font-weight:700;color:var(--ink);margin-bottom:8px;display:flex;align-items:center;gap:6px">
              <i class="fas fa-plus-circle" style="color:var(--red)"></i> Ajouter une ligne
            </div>
            <div style="display:grid;grid-template-columns:2fr 75px 115px 85px auto;gap:7px;align-items:end">
              <div class="fg" style="margin:0">
                <label style="font-size:.7rem">Désignation <span style="color:var(--muted);font-weight:400">(libre)</span></label>
                <input type="text" id="newNom" placeholder="Ex : RAM DDR4 16Go, Câble HDMI..." autocomplete="off"/>
              </div>
              <div class="fg" style="margin:0">
                <label style="font-size:.7rem">Qté</label>
                <input type="number" id="newQte" value="1" min="1"/>
              </div>
              <div class="fg" style="margin:0">
                <label style="font-size:.7rem">Prix HT (MAD)</label>
                <input type="number" id="newPrix" placeholder="0.00" min="0" step="0.01"/>
              </div>
              <div class="fg" style="margin:0">
                <label style="font-size:.7rem">Remise %</label>
                <input type="number" id="newRemise" value="0" min="0" max="100"/>
              </div>
              <button type="button" class="btn btn-primary" style="padding:10px 14px;white-space:nowrap" onclick="ajouterManuel()">
                <i class="fas fa-plus"></i> Ajouter
              </button>
            </div>
          </div>

          <!-- ── Depuis le catalogue ── -->
          <div style="margin-top:12px;padding:11px 14px;background:#fafafa;border:1px solid var(--border);border-radius:9px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <span style="font-size:.75rem;font-weight:600;color:var(--muted);white-space:nowrap"><i class="fas fa-search" style="margin-right:4px"></i>Depuis le catalogue :</span>
            <div style="flex:1;min-width:200px">
              <select id="selectProduit">
                <option value="">— Rechercher un produit existant —</option>
                <?php foreach ($produits as $pr): ?>
                  <option value="<?= e($pr['id']) ?>"
                    data-nom="<?= e($pr['modele'] ?: $pr['nom']) ?>"
                    data-ref="<?= e($pr['modele'] ?? '') ?>"
                    data-subtext="<?= $pr['statut'] ? 'Actif' : 'Inactif' ?>">
                    <?= e($pr['modele'] ?? $pr['nom']) ?> — <?= e($pr['nom']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Récapitulatif -->
      <div class="dg-section">
        <div class="dg-sh"><h3><i class="fas fa-calculator"></i>Récapitulatif</h3></div>
        <div class="dg-body">
          <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
            <div style="flex:1;min-width:180px">
              <div class="fg" style="margin-bottom:12px">
                <label>TVA (%)</label>
                <input type="number" name="tva" id="inputTva" value="<?= $devisEdit ? $devisEdit['tva'] : 20 ?>" min="0" max="100" step="0.1" oninput="calculerTotal()"/>
              </div>
              <div class="fg">
                <label>Remise globale (%)</label>
                <input type="number" name="remise_globale" id="inputRemise" value="<?= $devisEdit ? $devisEdit['remise_globale'] : 0 ?>" min="0" max="100" step="0.1" oninput="calculerTotal()"/>
              </div>
            </div>
            <div class="total-box" style="flex:1;min-width:230px">
              <div class="total-row"><span>Total HT</span><span id="totalHT">0,00 MAD</span></div>
              <div class="total-row"><span>Remise</span><span id="totalRemise">0,00 MAD</span></div>
              <div class="total-row"><span id="labelTVA">TVA (20%)</span><span id="totalTVA">0,00 MAD</span></div>
              <div class="total-row"><span>Total TTC</span><span id="totalTTC">0,00 MAD</span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes & Conditions -->
      <div class="dg-section">
        <div class="dg-sh"><h3><i class="fas fa-sticky-note"></i>Notes &amp; Conditions</h3></div>
        <div class="dg-body" style="display:flex;flex-direction:column;gap:12px">
          <div class="fg">
            <label>Conditions de paiement</label>
            <input type="text" name="conditions" value="<?= $devisEdit ? e($devisEdit['conditions']) : 'Virement bancaire – 30 jours' ?>" placeholder="Ex: Paiement à 30 jours..."/>
          </div>
          <div class="fg">
            <label>Notes / Remarques</label>
            <textarea name="notes" rows="3" placeholder="Remarques supplémentaires..."><?= $devisEdit ? e($devisEdit['notes']) : '' ?></textarea>
          </div>
        </div>
      </div>

    </div><!-- fin colonne principale -->

    <!-- ── Colonne droite ── -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <div class="dg-section">
        <div class="dg-sh"><h3><i class="fas fa-info-circle"></i>Informations devis</h3></div>
        <div class="dg-body" style="display:flex;flex-direction:column;gap:12px">
          <div class="fg">
            <label>Numéro de devis</label>
            <input type="text" name="numero" value="<?= $devisEdit ? e($devisEdit['numero']) : e($nextNum) ?>" required/>
          </div>
          <div class="fg">
            <label>Date de validité</label>
            <input type="date" name="date_validite" value="<?= $devisEdit ? e($devisEdit['date_validite']) : date('Y-m-d', strtotime('+30 days')) ?>"/>
          </div>
        </div>
      </div>

      <!-- Signature -->
      <div class="dg-section">
        <div class="dg-sh"><h3><i class="fas fa-signature"></i>Signature</h3></div>
        <div class="dg-body" style="text-align:center">
          <?php
            $sigPath = UPLOAD_DIR . 'signature.png';
            $sigUrl  = UPLOAD_URL . 'signature.png';
          ?>
          <?php if (file_exists($sigPath)): ?>
            <img src="<?= e($sigUrl) ?>" alt="Signature" style="max-height:70px;max-width:170px;border:1px solid var(--border);border-radius:8px;padding:6px"/>
            <div style="font-size:.72rem;color:var(--muted);margin-top:6px">Signature incluse dans le PDF</div>
          <?php else: ?>
            <div style="font-size:.78rem;color:var(--muted);padding:10px;background:#fafafa;border-radius:8px">
              <i class="fas fa-info-circle" style="color:var(--red)"></i> Uploadez <strong>signature.png</strong> dans <code>uploads/</code> pour l'inclure.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($canWrite): ?>
      <button type="submit" name="sauvegarder" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:.92rem">
        <i class="fas fa-save"></i> <?= $devisEdit ? 'Mettre à jour' : 'Sauvegarder le devis' ?>
      </button>
      <?php else: ?>
      <div class="alert alert-error" style="margin:0">Mode lecture seule.</div>
      <?php endif; ?>

    </div><!-- fin colonne droite -->
  </div>
<?php if ($canWrite): ?></form><?php endif; ?>
<?php endif; ?>

<!-- ════ LISTE DES DEVIS GÉNÉRÉS ════ -->
<div class="card" style="margin-top:26px">
  <div class="card-header">
    <h2><i class="fas fa-list" style="color:var(--red);margin-right:8px"></i>Devis générés
      <span style="color:var(--muted);font-weight:400;font-size:.8rem;margin-left:6px"><?= $total ?> résultat<?= $total > 1 ? 's' : '' ?></span>
    </h2>
    <div style="display:flex;gap:8px;align-items:center">
      <form method="GET" style="display:flex;gap:6px;align-items:center">
        <div style="position:relative">
          <i class="fas fa-search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.76rem;pointer-events:none"></i>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="N° devis, client..."
                 style="padding:7px 12px 7px 28px;border:1.5px solid var(--border);border-radius:8px;font-family:'Inter',sans-serif;font-size:.82rem;outline:none;width:220px"/>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        <?php if ($q): ?><a href="devis_generateur.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
      </form>
      <?php if ($canWrite): ?>
        <a href="devis_generateur.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau</a>
      <?php endif; ?>
    </div>
  </div>
  <table>
    <thead>
      <tr>
        <th>N°</th><th>Client</th><th>Validité</th><th>Statut</th><th>Créé le</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($liste as $dg): ?>
      <tr>
        <td><strong><?= e($dg['numero']) ?></strong></td>
        <td>
          <?= e($dg['client_nom']) ?>
          <?php if ($dg['client_societe']): ?><br><small style="color:var(--muted)"><?= e($dg['client_societe']) ?></small><?php endif; ?>
        </td>
        <td style="font-size:.8rem"><?= $dg['date_validite'] ? date('d/m/Y', strtotime($dg['date_validite'])) : '—' ?></td>
        <td><span class="st-badge st-<?= e($dg['statut']) ?>"><?= ucfirst(e($dg['statut'])) ?></span></td>
        <td style="font-size:.78rem"><?= date('d/m/Y', strtotime($dg['created_at'])) ?></td>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <a href="?voir=<?= $dg['id'] ?>" class="btn btn-secondary btn-sm" title="Voir"><i class="fas fa-eye"></i></a>
            <?php if ($canWrite): ?>
              <a href="?modifier=<?= $dg['id'] ?>" class="btn btn-warning btn-sm" title="Modifier"><i class="fas fa-edit"></i></a>
            <?php endif; ?>
            <a href="devis_pdf.php?id=<?= $dg['id'] ?>" target="_blank" class="btn btn-danger btn-sm" title="PDF"><i class="fas fa-file-pdf"></i></a>
            <?php if ($canWrite): ?>
              <button type="button" class="btn btn-danger btn-sm" title="Supprimer"
                onclick="if(confirm('Supprimer le devis <?= e($dg['numero']) ?> de <?= e(addslashes($dg['client_nom'])) ?> ?')) window.location.href='?supprimer=<?= $dg['id'] ?>'">
                <i class="fas fa-trash"></i>
              </button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($liste)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--muted)">
          <?= $q ? 'Aucun résultat pour "' . e($q) . '"' : 'Aucun devis généré pour l\'instant.' ?>
        </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($totalPages > 1): ?>
  <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
    <span style="font-size:.78rem;color:var(--muted)"><?= $total ?> devis — page <?= $page ?>/<?= $totalPages ?></span>
    <div class="pag" style="margin:0">
      <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>">‹ Préc.</a><?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($q) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>">Suiv. ›</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
var lignes = [];

function syncLignes() {
  var rows = document.querySelectorAll('#lignesContainer .ligne-row');
  rows.forEach(function(row, i) {
    var inputs = row.querySelectorAll('input');
    if (lignes[i] !== undefined) {
      lignes[i].nom    = inputs[0].value;
      lignes[i].qte    = parseFloat(inputs[1].value) || 1;
      lignes[i].prix   = parseFloat(inputs[2].value) || 0;
      lignes[i].remise = parseFloat(inputs[3].value) || 0;
    }
  });
}

function ajouterLigne(nom, ref, qte, prix, remise) {
  syncLignes();
  lignes.push({ nom: nom || '', ref: ref || '', qte: qte || 1, prix: prix || 0, remise: remise || 0 });
  renderLignes();
}

function ajouterManuel() {
  var nom    = (document.getElementById('newNom').value || '').trim();
  var qte    = parseFloat(document.getElementById('newQte').value)    || 1;
  var prix   = parseFloat(document.getElementById('newPrix').value)   || 0;
  var remise = parseFloat(document.getElementById('newRemise').value) || 0;
  if (!nom) { document.getElementById('newNom').focus(); return; }
  ajouterLigne(nom, '', qte, prix, remise);
  // Réinitialiser le mini-form
  document.getElementById('newNom').value    = '';
  document.getElementById('newQte').value    = '1';
  document.getElementById('newPrix').value   = '';
  document.getElementById('newRemise').value = '0';
  document.getElementById('newNom').focus();
}

// Permettre Entrée sur le champ désignation pour ajouter
document.addEventListener('DOMContentLoaded', function() {
  var nomInput = document.getElementById('newNom');
  if (nomInput) {
    nomInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); ajouterManuel(); }
    });
  }
});

function ajouterDepuisProduit(val) {
  if (!val) return;
  var opt = document.querySelector('#selectProduit option[value="' + val + '"]');
  if (!opt) return;
  ajouterLigne(opt.dataset.nom, opt.dataset.ref, 1, 0, 0);
}

function supprimerLigne(idx) {
  syncLignes();
  lignes.splice(idx, 1);
  renderLignes();
}

function renderLignes() {
  var container = document.getElementById('lignesContainer');
  if (!container) return;
  if (lignes.length === 0) {
    container.innerHTML = '<p style="color:var(--muted);font-size:.82rem;padding:8px 0">Cliquez sur "Ajouter" ou sélectionnez un produit du catalogue.</p>';
    majJson(); calculerTotal(); return;
  }
  container.innerHTML = '';
  lignes.forEach(function(l, i) {
    var row = document.createElement('div');
    row.className = 'ligne-row';
    row.dataset.idx = i;
    row.innerHTML =
      '<input type="text" data-field="nom" placeholder="Désignation" value="' + esc(l.nom) + '"/>' +
      '<input type="number" data-field="qte" min="1" value="' + l.qte + '"/>' +
      '<input type="number" data-field="prix" min="0" step="0.01" placeholder="0.00" value="' + (l.prix > 0 ? l.prix : '') + '"/>' +
      '<input type="number" data-field="remise" min="0" max="100" value="' + (l.remise || 0) + '"/>' +
      '<button type="button" class="ligne-del" onclick="supprimerLigne(' + i + ')"><i class="fas fa-trash"></i></button>';
    container.appendChild(row);
  });
  majJson(); calculerTotal();
}

function majJson() {
  var el = document.getElementById('lignesJson');
  if (el) el.value = JSON.stringify(lignes);
}

function esc(s) { return (s || '').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

function fmt(n) {
  return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' MAD';
}

function calculerTotal() {
  var tva    = parseFloat((document.getElementById('inputTva') || {}).value) || 0;
  var remise = parseFloat((document.getElementById('inputRemise') || {}).value) || 0;
  var ht = 0;
  lignes.forEach(function(l) { ht += (l.prix || 0) * (l.qte || 1) * (1 - (l.remise || 0) / 100); });
  var htR  = ht * (1 - remise / 100);
  var tvaM = htR * tva / 100;
  var ttc  = htR + tvaM;
  var el;
  if ((el = document.getElementById('totalHT')))     el.textContent = fmt(ht);
  if ((el = document.getElementById('totalRemise'))) el.textContent = '- ' + fmt(ht - htR);
  if ((el = document.getElementById('totalTVA')))    el.textContent = fmt(tvaM);
  if ((el = document.getElementById('totalTTC')))    el.textContent = fmt(ttc);
  if ((el = document.getElementById('labelTVA')))    el.textContent = 'TVA (' + tva + '%)';
}

// Init lignes si modification
<?php if ($devisEdit && $devisEdit['lignes']): ?>
lignes = <?= $devisEdit['lignes'] ?>;
<?php endif; ?>
renderLignes();

// Listener sur les inputs de lignes
document.getElementById('lignesContainer').addEventListener('input', function(e) {
  var row = e.target.closest('.ligne-row');
  if (!row) return;
  var i = parseInt(row.dataset.idx);
  var field = e.target.dataset.field;
  if (!lignes[i] || !field) return;
  if (field === 'nom') lignes[i].nom = e.target.value;
  else lignes[i][field] = parseFloat(e.target.value) || 0;
  majJson();
  calculerTotal();
});

// Select2
$(document).ready(function() {
  $('#selectProduit').select2({
    placeholder: '— Rechercher un produit —',
    allowClear: true,
    width: '100%',
    language: {
      searching: function() { return 'Recherche...'; },
      noResults: function() { return 'Aucun produit trouvé'; }
    },
    templateResult: function(opt) {
      if (!opt.id) return opt.text;
      var sub = $(opt.element).data('subtext') || '';
      var badge = sub === 'Inactif' ? '<span class="badge-inactif">Inactif</span>' : '';
      return $('<span>' + opt.text + badge + '</span>');
    }
  });
  $('#selectProduit').on('select2:select', function(e) {
    ajouterDepuisProduit(e.params.data.id);
    $(this).val(null).trigger('change');
  });
});
</script>

<?php require '_bottom.php'; ?>
