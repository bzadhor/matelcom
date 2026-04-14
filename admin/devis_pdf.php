<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('devis_generateur', 'read');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(ADMIN_URL . '/devis_generateur.php'); }

$s = $db->prepare("SELECT * FROM devis_generes WHERE id=?");
$s->execute([$id]);
$dg = $s->fetch();
if (!$dg) { redirect(ADMIN_URL . '/devis_generateur.php'); }

$p      = allParams();
$lignes = json_decode($dg['lignes'] ?? '[]', true) ?: [];
$tva    = (float)$dg['tva'];
$remise = (float)$dg['remise_globale'];

$totalHT = 0;
foreach ($lignes as $l) $totalHT += ($l['prix'] ?? 0) * ($l['qte'] ?? 1) * (1 - ($l['remise'] ?? 0) / 100);
$totalHT_remise = $totalHT * (1 - $remise / 100);
$totalTVA       = $totalHT_remise * $tva / 100;
$totalTTC       = $totalHT_remise + $totalTVA;

// Logo base64
$logoBase64 = '';
$logoPath   = UPLOAD_DIR . ($p['logo'] ?? '');
if (!empty($p['logo']) && file_exists($logoPath)) {
    $ext        = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime       = $ext === 'png' ? 'image/png' : 'image/jpeg';
    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
}

// Signature base64
$sigBase64 = '';
$sigPath   = UPLOAD_DIR . 'signature.png';
if (file_exists($sigPath)) {
    $sigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($sigPath));
}

// Montant en lettres
function mc_nb($n) {
    if ($n == 0) return '';
    $u = [1=>'un',2=>'deux',3=>'trois',4=>'quatre',5=>'cinq',6=>'six',7=>'sept',8=>'huit',9=>'neuf',
          10=>'dix',11=>'onze',12=>'douze',13=>'treize',14=>'quatorze',15=>'quinze',16=>'seize',
          17=>'dix-sept',18=>'dix-huit',19=>'dix-neuf'];
    $d = [2=>'vingt',3=>'trente',4=>'quarante',5=>'cinquante',6=>'soixante',7=>'soixante',8=>'quatre-vingt',9=>'quatre-vingt'];
    if ($n < 20) return $u[$n];
    if ($n < 100) {
        $di = intdiv($n,10); $un = $n%10;
        if ($di==7||$di==9){ $un+=10; $di--; }
        $s = $d[$di];
        if ($di==8 && $un==0) $s .= 's';
        if ($un > 0) $s .= '-' . ($un==1 && $di!=8 ? 'et-' : '') . $u[$un];
        return $s;
    }
    if ($n < 1000) {
        $c = intdiv($n,100); $r = $n%100;
        $s = ($c==1 ? 'cent' : mc_nb($c).' cent');
        if ($r==0 && $c>1) $s .= 's';
        if ($r>0) $s .= ' '.mc_nb($r);
        return $s;
    }
    if ($n < 1000000) {
        $m = intdiv($n,1000); $r = $n%1000;
        $s = ($m==1 ? 'mille' : mc_nb($m).' mille');
        if ($r>0) $s .= ' '.mc_nb($r);
        return $s;
    }
    $m = intdiv($n,1000000); $r = $n%1000000;
    $s = mc_nb($m).' million'.($m>1?'s':'');
    if ($r>0) $s .= ' '.mc_nb($r);
    return $s;
}
function nombreEnLettres($n) {
    $n = (float)round($n, 2);
    $entier = (int)$n;
    $cents  = (int)round(($n - $entier) * 100);
    $r = $entier == 0 ? 'zéro' : mc_nb($entier);
    $r = ucfirst($r) . ' dirhams';
    if ($cents > 0) $r .= ' et ' . mc_nb($cents) . ' centimes';
    return $r;
}

$siteName = e($p['site_nom'] ?? 'MATELCOM');
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<title>Devis <?= e($dg['numero']) ?> — <?= $siteName ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11.5px; color: #222; background: #f0f0f0; }
.sheet { background: white; max-width: 794px; margin: 0 auto; position: relative; overflow: hidden; }
.page-inner { padding: 0 42px 100px 42px; }

/* ── Bandeau rouge en haut ── */
.top-band {
  height: 7px;
  background: linear-gradient(90deg, #B71C1C, #D32F2F, #EF5350);
}

/* ── En-tête principal ── */
.main-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 28px 0 22px;
  border-bottom: 1px solid #EEEEEE;
  margin-bottom: 26px;
}
.logo-block img { height: 48px; display: block; }
.logo-block .name { font-size: 20px; font-weight: 800; color: #1A1A2E; letter-spacing: .5px; }
.logo-block .tagline { font-size: 9.5px; color: #9E9E9E; margin-top: 3px; letter-spacing: .3px; }

.devis-badge {
  text-align: right;
}
.devis-badge .label {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: #BDBDBD;
  display: block;
}
.devis-badge .numero {
  font-size: 26px;
  font-weight: 900;
  color: #D32F2F;
  line-height: 1.1;
  letter-spacing: -1px;
}
.devis-badge .dates {
  font-size: 10px;
  color: #757575;
  margin-top: 5px;
  line-height: 1.8;
}
.devis-badge .dates strong { color: #424242; }

/* ── Bloc infos : émetteur + client côte à côte ── */
.parties-row {
  display: flex;
  gap: 0;
  margin-bottom: 24px;
  border: 1px solid #E0E0E0;
  border-radius: 10px;
  overflow: hidden;
}
.partie-block {
  flex: 1;
  padding: 16px 20px;
}
.partie-block.emetteur {
  background: #FAFAFA;
  border-right: 1px solid #E0E0E0;
}
.partie-block.client {
  background: white;
}
.partie-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 8px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 3px 9px;
  border-radius: 20px;
  margin-bottom: 10px;
}
.partie-tag.t-emetteur { background: #1A1A2E; color: white; }
.partie-tag.t-client   { background: #D32F2F; color: white; }
.partie-nom { font-size: 13.5px; font-weight: 700; color: #1A1A2E; margin-bottom: 5px; }
.partie-info { font-size: 10.5px; color: #616161; line-height: 1.8; }

/* ── Barre méta (N°, date, validité, paiement) ── */
.meta-bar {
  display: flex;
  gap: 0;
  margin-bottom: 24px;
  background: #1A1A2E;
  border-radius: 8px;
  overflow: hidden;
}
.meta-cell {
  flex: 1;
  padding: 10px 16px;
  border-right: 1px solid rgba(255,255,255,.08);
}
.meta-cell:last-child { border-right: none; }
.meta-cell .mc-label { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,.45); margin-bottom: 4px; }
.meta-cell .mc-val   { font-size: 12px; font-weight: 700; color: white; }

/* ── Tableau produits ── */
.section-title {
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: #D32F2F;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 7px;
}
.section-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #EEEEEE;
}
table.produits { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
table.produits thead tr th {
  padding: 9px 12px;
  font-size: 8.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: #757575;
  background: #F5F5F5;
  border-bottom: 2px solid #D32F2F;
  text-align: left;
}
table.produits thead tr th:last-child { text-align: right; }
table.produits tbody td {
  padding: 10px 12px;
  font-size: 11px;
  color: #333;
  border-bottom: 1px solid #F5F5F5;
  vertical-align: middle;
}
table.produits tbody td:last-child { text-align: right; font-weight: 600; color: #1A1A2E; }
table.produits tbody tr:last-child td { border-bottom: 2px solid #EEEEEE; }
table.produits td .prod-ref { font-size: 9px; color: #BDBDBD; margin-top: 2px; }
table.produits tbody tr:hover td { background: #FAFAFA; }

/* ── Zone totaux ── */
.totaux-zone { display: flex; justify-content: flex-end; margin-bottom: 20px; }
.totaux-inner { width: 260px; }
.total-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  font-size: 11px;
  border-bottom: 1px solid #F5F5F5;
  color: #424242;
}
.total-line:last-child {
  border-bottom: none;
  border-top: 2px solid #D32F2F;
  margin-top: 4px;
  padding-top: 10px;
  font-size: 14px;
  font-weight: 800;
  color: #D32F2F;
}
.total-line .tl-label { font-size: 10.5px; color: #757575; }
.total-line .tl-val { font-weight: 600; }

/* ── Montant en lettres ── */
.montant-lettres {
  margin-bottom: 20px;
  padding: 10px 15px;
  border-left: 3px solid #D32F2F;
  background: #FFF8F8;
  border-radius: 0 7px 7px 0;
}
.montant-lettres .ml-label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #D32F2F; margin-bottom: 3px; }
.montant-lettres .ml-val   { font-size: 11px; font-style: italic; color: #1A1A2E; font-weight: 600; }

/* ── Notes / conditions ── */
.notes-block {
  margin-bottom: 22px;
  background: #FAFAFA;
  border: 1px solid #EEEEEE;
  border-radius: 8px;
  padding: 13px 16px;
}
.notes-block .nb-title { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #9E9E9E; margin-bottom: 5px; }
.notes-block p { font-size: 10.5px; color: #424242; line-height: 1.7; }

/* ── Signature + acceptation ── */
.sign-row {
  display: flex;
  gap: 16px;
  margin-bottom: 30px;
  align-items: flex-start;
}
.sign-box {
  flex: 1;
  border: 1px solid #E0E0E0;
  border-radius: 8px;
  padding: 14px 16px;
  min-height: 90px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.sign-box .sb-title { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .7px; color: #9E9E9E; margin-bottom: 8px; }
.sign-box.sb-company { background: #FAFAFA; }
.sign-box.sb-client  { background: white; border: 1.5px dashed #E0E0E0; }
.sign-box .sb-name   { font-size: 11px; font-weight: 700; color: #1A1A2E; }
.sign-box .sb-sub    { font-size: 9px; color: #9E9E9E; margin-top: 2px; }
.accept-line { height: 1px; background: #E0E0E0; margin-top: 20px; }
.accept-label { font-size: 8.5px; color: #BDBDBD; text-align: center; margin-top: 6px; }

/* ── Pied de page ── */
.pdf-footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  border-top: 3px solid #D32F2F;
  background: white;
  padding: 10px 42px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pf-left  { font-size: 9.5px; color: #9E9E9E; line-height: 1.7; }
.pf-left strong { color: #424242; font-size: 10px; }
.pf-right { font-size: 9px; color: #BDBDBD; text-align: right; }
.pf-right .page-num { font-size: 10px; font-weight: 700; color: #D32F2F; }

/* ── Barre d'action (non imprimée) ── */
.action-bar {
  background: #1A1A2E;
  padding: 11px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
}
.btn-dl {
  background: #D32F2F;
  color: white;
  border: none;
  padding: 10px 22px;
  border-radius: 7px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 7px;
  transition: background .2s;
}
.btn-dl:hover { background: #B71C1C; }
.btn-print {
  background: rgba(255,255,255,.1);
  color: white;
  border: 1px solid rgba(255,255,255,.2);
  padding: 10px 18px;
  border-radius: 7px;
  font-weight: 600;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 7px;
}
.back-link { color: rgba(255,255,255,.45); font-size: 11.5px; text-decoration: none; padding: 10px; }
.back-link:hover { color: white; }

@media print {
  body { background: white; }
  .action-bar { display: none !important; }
  .sheet { box-shadow: none; }
  .pdf-footer { position: fixed; bottom: 0; left: 0; right: 0; }
  .page-inner { padding-bottom: 80px; }
  @page { margin: 0; size: A4; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<!-- ── Barre d'action ── -->
<div class="action-bar no-print">
  <button class="btn-dl" onclick="telechargerPDF()" id="btnDl">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Télécharger PDF
  </button>
  <button class="btn-print" onclick="window.print()">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
    Imprimer
  </button>
  <a href="devis_generateur.php?voir=<?= $id ?>" class="back-link">← Retour</a>
</div>

<!-- ── Document ── -->
<div class="sheet">
  <div class="top-band"></div>
  <div class="page-inner">

    <!-- EN-TÊTE -->
    <div class="main-header">
      <div class="logo-block">
        <?php if ($logoBase64): ?>
          <img src="<?= $logoBase64 ?>" alt="<?= $siteName ?>"/>
        <?php else: ?>
          <div class="name"><?= $siteName ?></div>
          <div class="tagline">Vente &amp; Distribution de Matériel Informatique</div>
        <?php endif; ?>
      </div>
      <div class="devis-badge">
        <span class="label">Document commercial</span>
        <div class="numero"><?= e($dg['numero']) ?></div>
        <div class="dates">
          <strong>Émis le</strong> <?= date('d/m/Y', strtotime($dg['created_at'])) ?><br/>
          <?php if ($dg['date_validite']): ?>
            <strong>Valable jusqu'au</strong> <?= date('d/m/Y', strtotime($dg['date_validite'])) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ÉMETTEUR + CLIENT -->
    <div class="parties-row">
      <div class="partie-block emetteur">
        <div class="partie-tag t-emetteur">&#9679; Émetteur</div>
        <div class="partie-nom"><?= $siteName ?></div>
        <div class="partie-info">
          Vente &amp; Distribution de Matériel Informatique<br/>
          <?php if ($p['contact_adresse'] ?? ''): ?><?= nl2br(e($p['contact_adresse'])) ?><br/><?php endif; ?>
          <?php if ($p['contact_telephone'] ?? ''): ?><?= e($p['contact_telephone']) ?><br/><?php endif; ?>
          <?php if ($p['contact_email'] ?? ''): ?><?= e($p['contact_email']) ?><?php endif; ?>
        </div>
      </div>
      <div class="partie-block client">
        <div class="partie-tag t-client">&#9679; Destinataire</div>
        <div class="partie-nom"><?= e($dg['client_nom']) ?></div>
        <div class="partie-info">
          <?php if ($dg['client_societe']): ?><?= e($dg['client_societe']) ?><br/><?php endif; ?>
          <?php if ($dg['client_adresse']): ?><?= nl2br(e($dg['client_adresse'])) ?><br/><?php endif; ?>
          <?php if ($dg['client_telephone']): ?><?= e($dg['client_telephone']) ?><br/><?php endif; ?>
          <?php if ($dg['client_email']): ?><?= e($dg['client_email']) ?><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- BARRE MÉTA -->
    <div class="meta-bar">
      <div class="meta-cell">
        <div class="mc-label">Référence</div>
        <div class="mc-val"><?= e($dg['numero']) ?></div>
      </div>
      <div class="meta-cell">
        <div class="mc-label">Date d'émission</div>
        <div class="mc-val"><?= date('d/m/Y', strtotime($dg['created_at'])) ?></div>
      </div>
      <?php if ($dg['date_validite']): ?>
      <div class="meta-cell">
        <div class="mc-label">Date de validité</div>
        <div class="mc-val"><?= date('d/m/Y', strtotime($dg['date_validite'])) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($dg['conditions']): ?>
      <div class="meta-cell">
        <div class="mc-label">Modalités de paiement</div>
        <div class="mc-val"><?= e($dg['conditions']) ?></div>
      </div>
      <?php endif; ?>
      <div class="meta-cell">
        <div class="mc-label">TVA applicable</div>
        <div class="mc-val"><?= $tva ?> %</div>
      </div>
    </div>

    <!-- TABLEAU PRODUITS -->
    <?php if (!empty($lignes)): ?>
    <div class="section-title">Détail des prestations</div>
    <table class="produits">
      <thead>
        <tr>
          <th>Désignation</th>
          <th style="width:50px;text-align:center">Qté</th>
          <th style="width:110px;text-align:right">P.U. HT</th>
          <th style="width:65px;text-align:center">Remise</th>
          <th style="width:115px">Montant HT</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lignes as $idx => $l):
          $ligneHT = ($l['prix'] ?? 0) * ($l['qte'] ?? 1) * (1 - ($l['remise'] ?? 0) / 100);
        ?>
        <tr>
          <td>
            <strong><?= e($l['nom'] ?? '') ?></strong>
            <?php if (!empty($l['ref'])): ?><div class="prod-ref"><?= e($l['ref']) ?></div><?php endif; ?>
          </td>
          <td style="text-align:center;color:#555"><?= (int)($l['qte'] ?? 1) ?></td>
          <td style="text-align:right;color:#555"><?= number_format((float)($l['prix'] ?? 0), 2, ',', ' ') ?> MAD</td>
          <td style="text-align:center;color:<?= ($l['remise'] ?? 0) > 0 ? '#D32F2F' : '#BDBDBD' ?>">
            <?= (int)($l['remise'] ?? 0) > 0 ? '-'.(int)($l['remise']).'%' : '—' ?>
          </td>
          <td><?= number_format($ligneHT, 2, ',', ' ') ?> MAD</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- TOTAUX -->
    <div class="totaux-zone">
      <div class="totaux-inner">
        <div class="total-line">
          <span class="tl-label">Sous-total HT</span>
          <span class="tl-val"><?= number_format($totalHT, 2, ',', ' ') ?> MAD</span>
        </div>
        <?php if ($remise > 0): ?>
        <div class="total-line">
          <span class="tl-label">Remise globale (<?= $remise ?>%)</span>
          <span class="tl-val" style="color:#D32F2F">- <?= number_format($totalHT - $totalHT_remise, 2, ',', ' ') ?> MAD</span>
        </div>
        <div class="total-line">
          <span class="tl-label">Total HT après remise</span>
          <span class="tl-val"><?= number_format($totalHT_remise, 2, ',', ' ') ?> MAD</span>
        </div>
        <?php endif; ?>
        <div class="total-line">
          <span class="tl-label">TVA (<?= $tva ?>%)</span>
          <span class="tl-val"><?= number_format($totalTVA, 2, ',', ' ') ?> MAD</span>
        </div>
        <div class="total-line">
          <span>Net à payer TTC</span>
          <span><?= number_format($totalTTC, 2, ',', ' ') ?> MAD</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- MONTANT EN LETTRES -->
    <div class="montant-lettres">
      <div class="ml-label">Arrêté à la somme de</div>
      <div class="ml-val"><?= nombreEnLettres($totalTTC) ?></div>
    </div>

    <!-- NOTES -->
    <?php if ($dg['notes'] || $dg['conditions']): ?>
    <div class="notes-block">
      <?php if ($dg['conditions']): ?>
        <div class="nb-title">Conditions de paiement</div>
        <p style="margin-bottom:<?= $dg['notes'] ? '10px' : '0' ?>"><?= e($dg['conditions']) ?></p>
      <?php endif; ?>
      <?php if ($dg['notes']): ?>
        <div class="nb-title">Observations</div>
        <p><?= nl2br(e($dg['notes'])) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- SIGNATURE -->
    <div class="section-title">Validation</div>
    <div class="sign-row">
      <div class="sign-box sb-company">
        <div>
          <div class="sb-title">Signature &amp; Cachet <?= $siteName ?></div>
          <?php if ($sigBase64): ?>
            <img src="<?= $sigBase64 ?>" alt="Signature" style="max-height:56px;max-width:160px;display:block;margin-top:6px"/>
          <?php else: ?>
            <div style="height:50px;border-bottom:1px solid #E0E0E0;margin:10px 0 4px"></div>
          <?php endif; ?>
        </div>
        <div>
          <div class="sb-name"><?= $siteName ?></div>
          <div class="sb-sub">Vente &amp; Distribution Matériel Informatique</div>
        </div>
      </div>
      <div class="sign-box sb-client">
        <div class="sb-title">Bon pour accord — Signature client</div>
        <div style="flex:1;min-height:40px"></div>
        <div class="accept-line"></div>
        <div class="accept-label">Date et signature précédées de la mention "Lu et approuvé"</div>
      </div>
    </div>

  </div><!-- .page-inner -->

  <!-- PIED DE PAGE -->
  <div class="pdf-footer">
    <div class="pf-left">
      <strong><?= $siteName ?></strong> — Vente &amp; Distribution de Matériel Informatique<br/>
      <?php if ($p['contact_telephone'] ?? ''): ?>Tél : <?= e($p['contact_telephone']) ?><?php endif; ?>
      <?php if ($p['contact_email'] ?? ''): ?> &nbsp;·&nbsp; <?= e($p['contact_email']) ?><?php endif; ?>
      <?php if ($p['contact_adresse'] ?? ''): ?> &nbsp;·&nbsp; <?= e($p['contact_adresse']) ?><?php endif; ?>
    </div>
    <div class="pf-right">
      Devis N° <?= e($dg['numero']) ?><br/>
      <span class="page-num">MATELCOM</span>
    </div>
  </div>

</div><!-- .sheet -->

<script>
function telechargerPDF() {
  var btn = document.getElementById('btnDl');
  btn.innerHTML = '⏳ Génération en cours...';
  btn.disabled = true;
  html2pdf().set({
    margin: 0,
    filename: 'Devis_<?= e($dg['numero']) ?>_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $dg['client_nom']) ?>.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, logging: false },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }).from(document.querySelector('.sheet')).save().then(function() {
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Télécharger PDF';
    btn.disabled = false;
  });
}
</script>
</body>
</html>
<?php
$html = ob_get_clean();
echo $html;
