<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/mailer.php';
requireAdmin();
gh_require_permission('devis', 'read');
$db = getDB();
$pageTitle  = 'Devis reçus';
$activePage = 'devis';

// ── Changer statut ────────────────────────────────────────────
if (isset($_POST['changer_statut']) && gh_has_page_permission('devis', 'write')) {
    verify_csrf_or_fail('devis_statut');
    $did = (int)($_POST['devis_id'] ?? 0);
    $ns  = $_POST['nouveau_statut'] ?? '';
    $note = $_POST['note_admin'] ?? '';
    if (in_array($ns, ['nouveau', 'en_cours', 'traite', 'archive'])) {
        $db->prepare("UPDATE devis SET statut=?,note_admin=? WHERE id=?")->execute([$ns, $note, $did]);
        setFlash('success', 'Statut mis à jour.');
    }
    redirect(ADMIN_URL . '/devis.php?voir=' . $did);
}

// ── Supprimer devis ───────────────────────────────────────────
if (isset($_GET['supprimer']) && gh_has_page_permission('devis', 'write')) {
    $db->prepare("DELETE FROM devis WHERE id=?")->execute([(int)$_GET['supprimer']]);
    setFlash('success', 'Devis supprimé.');
    redirect(ADMIN_URL . '/devis.php');
}

// ── Supprimer un message ──────────────────────────────────────
if (isset($_GET['supprimer_msg']) && gh_has_page_permission('devis', 'write')) {
    $mid  = (int)$_GET['supprimer_msg'];
    $did  = (int)($_GET['devis_id'] ?? 0);
    $msg  = $db->prepare("SELECT * FROM devis_messages WHERE id=?")->execute([$mid]) ? $db->prepare("SELECT * FROM devis_messages WHERE id=?")->execute([$mid]) : null;
    // Récupérer le message pour éventuellement supprimer le fichier
    $s2 = $db->prepare("SELECT piece_jointe FROM devis_messages WHERE id=?");
    $s2->execute([$mid]);
    $row = $s2->fetch();
    if ($row && $row['piece_jointe']) {
        deleteUploadFile($row['piece_jointe']);
    }
    $db->prepare("DELETE FROM devis_messages WHERE id=?")->execute([$mid]);
    setFlash('success', 'Message supprimé.');
    redirect(ADMIN_URL . '/devis.php?voir=' . $did);
}

// ── Envoyer / enregistrer message ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_message']) && gh_has_page_permission('devis', 'write')) {
    verify_csrf_or_fail('devis_msg');
    $did       = (int)($_POST['devis_id'] ?? 0);
    $direction = $_POST['action_message'] === 'enregistrer_recu' ? 'recu' : 'envoye';
    $sujet     = limitString($_POST['sujet'] ?? '', 255);
    $corps     = limitString($_POST['corps'] ?? '', 5000);

    // Récupérer le devis
    $s = $db->prepare("SELECT * FROM devis WHERE id=?");
    $s->execute([$did]);
    $devisRow = $s->fetch();

    if ($devisRow && $corps) {
        // Gestion pièce jointe
        $pjPath = null; $pjNom = null; $pjStored = null;
        if (!empty($_FILES['piece_jointe']['name']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['piece_jointe'], 'devis_messages');
            if (isset($up['success'])) {
                $pjStored = $up['filename'];
                $pjNom    = $_FILES['piece_jointe']['name'];
                $pjPath   = UPLOAD_DIR . $pjStored;
            }
        }

        // Envoi email si direction = envoye
        $sendResult = ['ok' => true, 'error' => null];
        if ($direction === 'envoye') {
            $sendResult = sendMessageClient($devisRow, $sujet, $corps, $pjPath, $pjNom);
        }

        // Enregistrement en BDD dans tous les cas
        $db->prepare("INSERT INTO devis_messages (devis_id, direction, sujet, corps, expediteur, piece_jointe, piece_jointe_nom) VALUES (?,?,?,?,?,?,?)")
           ->execute([
               $did,
               $direction,
               $sujet,
               $corps,
               $direction === 'envoye' ? ($_SESSION['admin_user'] ?? 'Admin') : ($devisRow['nom_complet'] ?? ''),
               $pjStored,
               $pjNom,
           ]);

        if ($direction === 'envoye') {
            if ($sendResult['ok']) {
                // Passer en "en_cours" si encore "nouveau"
                if ($devisRow['statut'] === 'nouveau') {
                    $db->prepare("UPDATE devis SET statut='en_cours' WHERE id=?")->execute([$did]);
                }
                setFlash('success', '✅ Message envoyé à ' . ($devisRow['email'] ?? '') . ' et enregistré.');
            } else {
                setFlash('error', '⚠️ Message enregistré mais non envoyé : ' . $sendResult['error']);
            }
        } else {
            setFlash('success', '✅ Réponse du client enregistrée.');
        }
    }
    redirect(ADMIN_URL . '/devis.php?voir=' . $did);
}

// ── Filtre + liste ────────────────────────────────────────────
$filtre = $_GET['statut'] ?? '';
$sql    = "SELECT * FROM devis";
$params = [];
if ($filtre && in_array($filtre, ['nouveau', 'en_cours', 'traite', 'archive'])) {
    $sql .= " WHERE statut=?"; $params[] = $filtre;
}
$sql .= " ORDER BY created_at DESC";
$devis = $db->prepare($sql);
$devis->execute($params);
$devis = $devis->fetchAll();

// ── Vue détail ────────────────────────────────────────────────
$voir = null;
if (isset($_GET['voir'])) {
    $s = $db->prepare("SELECT * FROM devis WHERE id=?");
    $s->execute([(int)$_GET['voir']]);
    $voir = $s->fetch();
}

// ── Historique messages ───────────────────────────────────────
$messages = [];
if ($voir) {
    $sm = $db->prepare("SELECT * FROM devis_messages WHERE devis_id=? ORDER BY created_at ASC");
    $sm->execute([$voir['id']]);
    $messages = $sm->fetchAll();
    // Marquer les messages reçus non lus comme lus
    $db->prepare("UPDATE devis_messages SET lu=1 WHERE devis_id=? AND direction='recu' AND lu=0")->execute([$voir['id']]);
}

// Comptage messages non lus (pour badge dans liste)
$unreadCounts = [];
if (!$voir) {
    $uc = $db->query("SELECT devis_id, COUNT(*) as cnt FROM devis_messages WHERE direction='recu' AND lu=0 GROUP BY devis_id")->fetchAll();
    foreach ($uc as $row) $unreadCounts[$row['devis_id']] = $row['cnt'];
}

// ── Corps email par défaut ────────────────────────────────────
$defaultCorps = '';
$defaultSujet = '';
if ($voir) {
    $siteName    = param('site_nom', 'MATELCOM');
    $clientNom   = $voir['nom_complet'] ?? '';
    $produits    = $voir['produits_demandes'] ?? '';
    $defaultSujet = 'Votre demande de devis — ' . $siteName;
    $defaultCorps = "Bonjour " . $clientNom . ",\n\n"
        . "Suite à votre demande de devis concernant : " . $produits . ",\n"
        . "nous avons le plaisir de vous transmettre notre proposition commerciale.\n\n"
        . "Vous trouverez notre offre en pièce jointe.\n\n"
        . "N'hésitez pas à nous contacter pour toute question.\n\n"
        . "Cordialement,\nL'équipe " . $siteName . "\n"
        . param('contact_telephone', '') . " | " . param('contact_email', '');
}

require '_top.php';
?>

<style>
/* ── Conversation ── */
.conv-wrap { display: flex; flex-direction: column; gap: 14px; padding: 18px 0; max-height: 520px; overflow-y: auto; }
.msg-bubble { display: flex; gap: 10px; align-items: flex-start; max-width: 88%; }
.msg-bubble.envoye  { align-self: flex-end; flex-direction: row-reverse; }
.msg-bubble.recu    { align-self: flex-start; }
.msg-avatar {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: .82rem; font-weight: 700; color: white;
}
.msg-bubble.envoye  .msg-avatar { background: var(--red); }
.msg-bubble.recu    .msg-avatar { background: #455A64; }
.msg-content { max-width: 100%; }
.msg-meta { font-size: .7rem; color: var(--muted); margin-bottom: 4px; }
.msg-bubble.envoye .msg-meta { text-align: right; }
.msg-body {
  padding: 11px 15px; border-radius: 12px; font-size: .84rem; line-height: 1.6;
  white-space: pre-wrap; word-break: break-word;
}
.msg-bubble.envoye .msg-body { background: var(--red); color: white; border-bottom-right-radius: 4px; }
.msg-bubble.recu   .msg-body { background: #F5F5F5; color: var(--ink); border-bottom-left-radius: 4px; border: 1px solid var(--border); }
.msg-sujet { font-size: .72rem; font-weight: 700; margin-bottom: 4px; opacity: .8; }
.msg-pj { display: inline-flex; align-items: center; gap: 5px; margin-top: 7px; font-size: .75rem; padding: 4px 10px; border-radius: 6px; text-decoration: none; }
.msg-bubble.envoye .msg-pj { background: rgba(255,255,255,.2); color: white; }
.msg-bubble.recu   .msg-pj { background: #E0E0E0; color: #424242; }
.msg-del { font-size: .68rem; color: var(--muted); cursor: pointer; padding: 2px 6px; border-radius: 4px; border: none; background: none; opacity: .5; transition: opacity .2s; margin-top: 4px; }
.msg-del:hover { opacity: 1; color: #dc2626; }
.msg-bubble.envoye .msg-del { text-align: right; display: block; }

/* ── Composer ── */
.compose-box { background: white; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.compose-header { padding: 12px 18px; background: #fafafa; border-bottom: 1px solid var(--border); font-size: .84rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 8px; }
.compose-header i { color: var(--red); }
.compose-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 11px; }
.smtp-warning { padding: 10px 14px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; font-size: .8rem; color: #92400e; display: flex; align-items: center; gap: 8px; }
.tab-btns { display: flex; gap: 0; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 2px; }
.tab-btn { flex: 1; padding: 8px; font-size: .78rem; font-weight: 600; cursor: pointer; border: none; background: white; color: var(--muted); transition: all .2s; font-family: 'Inter', sans-serif; }
.tab-btn.active { background: var(--red); color: white; }
</style>

<?php if ($voir): ?>
<!-- ════ VUE DÉTAIL + MESSAGERIE ════ -->
<a href="devis.php" class="btn btn-secondary btn-sm" style="margin-bottom:18px"><i class="fas fa-arrow-left"></i> Retour</a>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:22px;margin-bottom:24px">
  <!-- Infos devis -->
  <div class="card" style="padding:28px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px">
      <i class="fas fa-file-invoice" style="color:var(--red);margin-right:6px"></i>
      Demande #<?= $voir['id'] ?>
    </h3>
    <table style="min-width:auto">
      <tr><td style="font-weight:600;width:140px;color:var(--muted)">Nom</td><td><strong><?= e($voir['nom_complet']) ?></strong></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Société</td><td><?= e($voir['societe'] ?? '—') ?></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Email</td><td><a href="mailto:<?= e($voir['email']) ?>" style="color:var(--red)"><?= e($voir['email']) ?></a></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Téléphone</td><td><?= e($voir['telephone'] ?? '—') ?></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Produits</td><td><?= e($voir['produits_demandes'] ?? '—') ?></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Quantité</td><td><?= e($voir['quantite'] ?? '—') ?></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Message</td><td><?= nl2br(e($voir['message'] ?? '—')) ?></td></tr>
      <tr><td style="font-weight:600;color:var(--muted)">Date</td><td><?= date('d/m/Y à H:i', strtotime($voir['created_at'])) ?></td></tr>
      <?php if ($voir['fichier']): ?>
      <tr><td style="font-weight:600;color:var(--muted)">Fichier</td>
        <td><a href="<?= UPLOAD_URL . e($voir['fichier']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Télécharger</a></td>
      </tr>
      <?php endif; ?>
    </table>
    <?php if (gh_has_page_permission('devis_generateur', 'read')): ?>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
      <a href="devis_generateur.php?from_devis=<?= $voir['id'] ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-file-invoice-dollar"></i> Générer un devis pour ce client
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Modifier statut -->
  <?php if (gh_has_page_permission('devis', 'write')): ?>
  <div class="card" style="padding:24px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px">
      <i class="fas fa-edit" style="color:var(--red);margin-right:6px"></i> Modifier le statut
    </h3>
    <form method="POST">
      <?= csrf_input('devis_statut') ?>
      <input type="hidden" name="changer_statut" value="1"/>
      <input type="hidden" name="devis_id" value="<?= $voir['id'] ?>"/>
      <div class="fg" style="margin-bottom:14px">
        <label>Statut</label>
        <select name="nouveau_statut">
          <?php foreach (['nouveau', 'en_cours', 'traite', 'archive'] as $st):
            $si = statutDevisInfo($st); ?>
            <option value="<?= $st ?>" <?= $voir['statut'] === $st ? 'selected' : '' ?>><?= $si['label'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg" style="margin-bottom:14px">
        <label>Note interne</label>
        <textarea name="note_admin" rows="4" placeholder="Notes visibles uniquement par l'équipe..."><?= e($voir['note_admin'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><i class="fas fa-save"></i> Enregistrer</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- ════ MESSAGERIE ════ -->
<div class="card">
  <div class="card-header" style="background:#fafafa">
    <h2 style="display:flex;align-items:center;gap:8px">
      <i class="fas fa-comments" style="color:var(--red)"></i>
      Messagerie client
      <?php $nbMsg = count($messages); ?>
      <span style="font-weight:400;font-size:.78rem;color:var(--muted)"><?= $nbMsg ?> message<?= $nbMsg > 1 ? 's' : '' ?></span>
    </h2>
    <?php if (!smtpIsConfigured()): ?>
      <a href="parametres.php#smtp" class="btn btn-warning btn-sm"><i class="fas fa-cog"></i> Configurer SMTP</a>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:1fr 420px;gap:0;min-height:400px">

    <!-- ── Historique conversation ── -->
    <div style="padding:20px;border-right:1px solid var(--border)">
      <?php if (empty($messages)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted)">
          <i class="fas fa-comments" style="font-size:2.5rem;opacity:.2;display:block;margin-bottom:12px"></i>
          <p style="font-size:.88rem">Aucun message pour l'instant.</p>
          <p style="font-size:.8rem;margin-top:4px">Utilisez le formulaire pour envoyer un message au client.</p>
        </div>
      <?php else: ?>
        <div class="conv-wrap" id="convWrap">
          <?php foreach ($messages as $msg): ?>
          <div class="msg-bubble <?= e($msg['direction']) ?>">
            <div class="msg-avatar">
              <?php if ($msg['direction'] === 'envoye'): ?>
                <i class="fas fa-user-shield"></i>
              <?php else: ?>
                <?= mb_strtoupper(mb_substr($voir['nom_complet'] ?? 'C', 0, 1)) ?>
              <?php endif; ?>
            </div>
            <div class="msg-content">
              <div class="msg-meta">
                <?php if ($msg['direction'] === 'envoye'): ?>
                  <strong style="color:var(--ink)"><?= e($msg['expediteur'] ?? 'Admin') ?></strong> ·
                <?php else: ?>
                  <strong style="color:var(--ink)"><?= e($voir['nom_complet']) ?></strong> ·
                <?php endif; ?>
                <?= date('d/m/Y à H:i', strtotime($msg['created_at'])) ?>
              </div>
              <div class="msg-body">
                <?php if ($msg['sujet']): ?>
                  <div class="msg-sujet"><i class="fas fa-envelope" style="font-size:.68rem"></i> <?= e($msg['sujet']) ?></div>
                <?php endif; ?>
                <?= nl2br(e($msg['corps'])) ?>
                <?php if ($msg['piece_jointe']): ?>
                  <a href="<?= UPLOAD_URL . e($msg['piece_jointe']) ?>" target="_blank" class="msg-pj">
                    <i class="fas fa-paperclip"></i> <?= e($msg['piece_jointe_nom'] ?: basename($msg['piece_jointe'])) ?>
                  </a>
                <?php endif; ?>
              </div>
              <?php if (gh_has_page_permission('devis', 'write')): ?>
              <form method="GET" style="display:inline">
                <input type="hidden" name="supprimer_msg" value="<?= $msg['id'] ?>"/>
                <input type="hidden" name="devis_id" value="<?= $voir['id'] ?>"/>
                <button type="submit" class="msg-del"
                  onclick="return confirm('Supprimer ce message ?')">
                  <i class="fas fa-trash"></i> Supprimer
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── Formulaire composer ── -->
    <div style="padding:20px">
      <?php if (!smtpIsConfigured()): ?>
      <div class="smtp-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <span>SMTP non configuré — les messages seront enregistrés mais <strong>non envoyés</strong> par email. <a href="parametres.php#smtp" style="color:#92400e;text-decoration:underline">Configurer</a></span>
      </div>
      <div style="height:12px"></div>
      <?php endif; ?>

      <?php if (gh_has_page_permission('devis', 'write')): ?>
      <!-- Tabs Envoyer / Enregistrer réponse -->
      <div class="tab-btns" style="margin-bottom:14px">
        <button type="button" class="tab-btn active" id="tabEnvoyer" onclick="switchTab('envoyer')">
          <i class="fas fa-paper-plane"></i> Envoyer au client
        </button>
        <button type="button" class="tab-btn" id="tabRecu" onclick="switchTab('recu')">
          <i class="fas fa-inbox"></i> Réponse reçue
        </button>
      </div>

      <form method="POST" enctype="multipart/form-data" id="msgForm">
        <?= csrf_input('devis_msg') ?>
        <input type="hidden" name="devis_id" value="<?= $voir['id'] ?>"/>
        <input type="hidden" name="action_message" id="actionMessage" value="envoyer"/>

        <!-- Destinataire (affiché en mode envoi) -->
        <div id="rowDestinataire" style="margin-bottom:11px;padding:9px 13px;background:#fafafa;border:1px solid var(--border);border-radius:8px;font-size:.82rem">
          <i class="fas fa-user" style="color:var(--muted);margin-right:6px"></i>
          <strong>À :</strong> <?= e($voir['nom_complet']) ?> &lt;<?= e($voir['email']) ?>&gt;
        </div>

        <div class="fg" style="margin-bottom:11px">
          <label>Sujet</label>
          <input type="text" name="sujet" id="inputSujet" value="<?= e($defaultSujet) ?>" placeholder="Objet du message"/>
        </div>

        <div class="fg" style="margin-bottom:11px">
          <label id="labelCorps">Message</label>
          <textarea name="corps" id="inputCorps" rows="9" placeholder="Rédigez votre message ici..."
            style="font-size:.84rem;line-height:1.6;resize:vertical"><?= e($defaultCorps) ?></textarea>
        </div>

        <!-- Pièce jointe (seulement en mode envoi) -->
        <div id="rowPJ" style="margin-bottom:14px">
          <label style="font-size:.78rem;font-weight:600;color:var(--ink);display:block;margin-bottom:5px">
            <i class="fas fa-paperclip" style="color:var(--muted)"></i> Pièce jointe
            <span style="font-weight:400;color:var(--muted)">(PDF, Word, image — max 10 Mo)</span>
          </label>
          <input type="file" name="piece_jointe" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.xls,.xlsx"
            style="font-size:.8rem;width:100%"/>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px">
          <button type="submit" class="btn btn-primary" id="btnEnvoyer" style="width:100%;justify-content:center;padding:11px">
            <i class="fas fa-paper-plane"></i> <span id="btnLabel">Envoyer le message</span>
          </button>
        </div>
      </form>
      <?php else: ?>
      <div class="alert alert-error" style="margin:0">Mode lecture seule.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var currentTab = 'envoyer';
function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tabEnvoyer').classList.toggle('active', tab === 'envoyer');
  document.getElementById('tabRecu').classList.toggle('active',    tab === 'recu');
  document.getElementById('actionMessage').value = tab === 'envoyer' ? 'envoyer' : 'enregistrer_recu';

  var isEnvoyer = tab === 'envoyer';
  document.getElementById('rowDestinataire').style.display = isEnvoyer ? '' : 'none';
  document.getElementById('rowPJ').style.display           = isEnvoyer ? '' : 'none';
  document.getElementById('labelCorps').textContent        = isEnvoyer ? 'Message' : 'Contenu de la réponse reçue';
  document.getElementById('btnLabel').textContent          = isEnvoyer ? 'Envoyer le message' : 'Enregistrer la réponse';
  document.getElementById('btnEnvoyer').style.background   = isEnvoyer ? '' : '#455A64';

  if (tab === 'recu') {
    document.getElementById('inputSujet').value = 'Réponse de <?= e(addslashes($voir['nom_complet'])) ?>';
    document.getElementById('inputCorps').value = '';
    document.getElementById('inputCorps').placeholder = 'Copiez ici la réponse reçue du client par email...';
  } else {
    document.getElementById('inputSujet').value = <?= json_encode($defaultSujet) ?>;
    document.getElementById('inputCorps').value = <?= json_encode($defaultCorps) ?>;
    document.getElementById('inputCorps').placeholder = 'Rédigez votre message ici...';
  }
}

// Auto-scroll conversation vers le bas
var cw = document.getElementById('convWrap');
if (cw) cw.scrollTop = cw.scrollHeight;
</script>

<?php else: ?>
<!-- ════ LISTE DES DEVIS ════ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php
    $counts = [];
    foreach (['nouveau','en_cours','traite','archive'] as $st) {
        $counts[$st] = 0;
    }
    foreach ($devis as $d) {
        if (isset($counts[$d['statut']])) $counts[$d['statut']]++;
    }
    ?>
    <a href="devis.php" class="btn <?= !$filtre ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Tous (<?= count($devis) ?>)</a>
    <?php foreach (['nouveau' => 'Nouveau', 'en_cours' => 'En cours', 'traite' => 'Traité', 'archive' => 'Archivé'] as $k => $v): ?>
      <a href="?statut=<?= $k ?>" class="btn <?= $filtre === $k ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <?= $v ?> (<?= $counts[$k] ?>)
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <table>
    <thead>
      <tr><th>Nom</th><th>Email / Tél</th><th>Produit</th><th>Statut</th><th>Messages</th><th>Date</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($devis as $d):
      $si = statutDevisInfo($d['statut']);
      $unread = $unreadCounts[$d['id']] ?? 0;
    ?>
    <tr>
      <td>
        <strong><?= e($d['nom_complet']) ?></strong>
        <?php if ($d['societe']): ?><br><small style="color:var(--muted)"><?= e($d['societe']) ?></small><?php endif; ?>
      </td>
      <td><?= e($d['email']) ?><br><small style="color:var(--muted)"><?= e($d['telephone'] ?? '') ?></small></td>
      <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($d['produits_demandes'] ?? '—') ?></td>
      <td><span class="badge bd-<?= str_replace('badge-', '', $si['class']) ?>"><?= $si['label'] ?></span></td>
      <td>
        <?php if ($unread > 0): ?>
          <span class="badge bd-danger"><?= $unread ?> nouveau<?= $unread > 1 ? 'x' : '' ?></span>
        <?php else: ?>
          <span style="font-size:.78rem;color:var(--muted)">—</span>
        <?php endif; ?>
      </td>
      <td style="white-space:nowrap;font-size:.8rem"><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="?voir=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
          <?php if (gh_has_page_permission('devis', 'write')): ?>
            <a href="?supprimer=<?= $d['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Supprimer ce devis ?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$devis): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">Aucun devis reçu.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require '_bottom.php'; ?>
