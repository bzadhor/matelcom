<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
gh_require_permission('parametres','read');
$db = getDB();
$pageTitle  = 'Paramètres du site';
$activePage = 'parametres';

if ($_SERVER['REQUEST_METHOD']==='POST' && gh_has_page_permission('parametres','write')) {
    verify_csrf_or_fail('parametres_form');
    $fields = ['site_nom','site_slogan','couleur_principale','hero_titre','hero_sous_titre','hero_badge',
        'stat_1_valeur','stat_1_label','stat_2_valeur','stat_2_label','stat_3_valeur','stat_3_label',
        'about_titre','about_texte','about_annees',
        'contact_telephone','contact_whatsapp','contact_email','contact_adresse',
        'whatsapp_numero','whatsapp_message',
        'footer_texte','footer_facebook','footer_instagram','footer_linkedin','footer_whatsapp',
        'recaptcha_site_key','recaptcha_secret_key',
        'smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_from_name'];
    foreach ($fields as $f) {
        $val = $_POST[$f]??'';
        $db->prepare("INSERT INTO parametres(cle,valeur) VALUES(?,?) ON DUPLICATE KEY UPDATE valeur=?")->execute([$f,$val,$val]);
    }
    // Logo upload
    if (!empty($_FILES['logo']) && $_FILES['logo']['error']===UPLOAD_ERR_OK) {
        $up = uploadImage($_FILES['logo'],'logo');
        if (isset($up['success'])) {
            $db->prepare("INSERT INTO parametres(cle,valeur) VALUES('logo',?) ON DUPLICATE KEY UPDATE valeur=?")->execute([$up['filename'],$up['filename']]);
        }
    }
    setFlash('success','Paramètres enregistrés.');
    header('Location: parametres.php'); exit;
}

$p = allParams();
require '_top.php';
?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_input('parametres_form') ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px">

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-globe" style="color:var(--red);margin-right:6px"></i> Général</h3>
      <div class="fg" style="margin-bottom:12px"><label>Nom du site</label><input type="text" name="site_nom" value="<?= e($p['site_nom']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Slogan</label><input type="text" name="site_slogan" value="<?= e($p['site_slogan']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Couleur principale</label><input type="color" name="couleur_principale" value="<?= e($p['couleur_principale']??'#D32F2F') ?>" style="height:38px"/></div>
      <div class="fg" style="margin-bottom:12px">
        <label>Logo</label>
        <?php $logo=imgUrl($p['logo']??''); if($logo): ?><img src="<?= e($logo) ?>" style="height:40px;display:block;margin-bottom:8px"/><?php endif; ?>
        <input type="file" name="logo" accept="image/*"/>
      </div>
    </div>

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-star" style="color:var(--red);margin-right:6px"></i> Hero (bannière)</h3>
      <div class="fg" style="margin-bottom:12px"><label>Badge</label><input type="text" name="hero_badge" value="<?= e($p['hero_badge']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Titre (HTML inline)</label><input type="text" name="hero_titre" value="<?= e($p['hero_titre']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Sous-titre</label><textarea name="hero_sous_titre"><?= e($p['hero_sous_titre']??'') ?></textarea></div>
      <div class="fgrid">
        <div class="fg"><label>Stat 1 valeur</label><input type="text" name="stat_1_valeur" value="<?= e($p['stat_1_valeur']??'') ?>"/></div>
        <div class="fg"><label>Stat 1 label</label><input type="text" name="stat_1_label" value="<?= e($p['stat_1_label']??'') ?>"/></div>
        <div class="fg"><label>Stat 2 valeur</label><input type="text" name="stat_2_valeur" value="<?= e($p['stat_2_valeur']??'') ?>"/></div>
        <div class="fg"><label>Stat 2 label</label><input type="text" name="stat_2_label" value="<?= e($p['stat_2_label']??'') ?>"/></div>
        <div class="fg"><label>Stat 3 valeur</label><input type="text" name="stat_3_valeur" value="<?= e($p['stat_3_valeur']??'') ?>"/></div>
        <div class="fg"><label>Stat 3 label</label><input type="text" name="stat_3_label" value="<?= e($p['stat_3_label']??'') ?>"/></div>
      </div>
    </div>

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-building" style="color:var(--red);margin-right:6px"></i> À propos</h3>
      <div class="fg" style="margin-bottom:12px"><label>Titre</label><input type="text" name="about_titre" value="<?= e($p['about_titre']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Texte</label><textarea name="about_texte"><?= e($p['about_texte']??'') ?></textarea></div>
      <div class="fg" style="margin-bottom:12px"><label>Années d'expérience</label><input type="text" name="about_annees" value="<?= e($p['about_annees']??'') ?>"/></div>
    </div>

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-phone" style="color:var(--red);margin-right:6px"></i> Contact</h3>
      <div class="fg" style="margin-bottom:12px"><label>Téléphone</label><input type="text" name="contact_telephone" value="<?= e($p['contact_telephone']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>WhatsApp</label><input type="text" name="contact_whatsapp" value="<?= e($p['contact_whatsapp']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Email</label><input type="text" name="contact_email" value="<?= e($p['contact_email']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Adresse</label><input type="text" name="contact_adresse" value="<?= e($p['contact_adresse']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>WhatsApp N° (sans +)</label><input type="text" name="whatsapp_numero" value="<?= e($p['whatsapp_numero']??'') ?>" placeholder="212600000000"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Message WhatsApp pré-rempli</label><input type="text" name="whatsapp_message" value="<?= e($p['whatsapp_message']??'') ?>"/></div>
    </div>

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-link" style="color:var(--red);margin-right:6px"></i> Footer & Réseaux</h3>
      <div class="fg" style="margin-bottom:12px"><label>Texte footer</label><textarea name="footer_texte"><?= e($p['footer_texte']??'') ?></textarea></div>
      <div class="fg" style="margin-bottom:12px"><label>Facebook URL</label><input type="text" name="footer_facebook" value="<?= e($p['footer_facebook']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Instagram URL</label><input type="text" name="footer_instagram" value="<?= e($p['footer_instagram']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>LinkedIn URL</label><input type="text" name="footer_linkedin" value="<?= e($p['footer_linkedin']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>WhatsApp URL</label><input type="text" name="footer_whatsapp" value="<?= e($p['footer_whatsapp']??'') ?>"/></div>
    </div>

    <div class="card" style="padding:24px">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:18px"><i class="fas fa-shield-alt" style="color:var(--red);margin-right:6px"></i> reCAPTCHA</h3>
      <div class="fg" style="margin-bottom:12px"><label>Site Key</label><input type="text" name="recaptcha_site_key" value="<?= e($p['recaptcha_site_key']??'') ?>"/></div>
      <div class="fg" style="margin-bottom:12px"><label>Secret Key</label><input type="text" name="recaptcha_secret_key" value="<?= e($p['recaptcha_secret_key']??'') ?>"/></div>
    </div>

    <!-- SMTP — ancre #smtp pour lien direct depuis devis.php -->
    <div class="card" style="padding:24px;grid-column:1/-1" id="smtp">
      <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:6px"><i class="fas fa-envelope" style="color:var(--red);margin-right:6px"></i> Configuration SMTP (envoi d'emails)</h3>
      <p style="font-size:.82rem;color:var(--muted);margin-bottom:18px">Ces paramètres sont utilisés pour envoyer les messages aux clients depuis la messagerie des devis.</p>
      <?php
        $smtpOk = !empty($p['smtp_username']) && !empty($p['smtp_password']);
        if ($smtpOk): ?>
        <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#d1fae5;border-radius:6px;font-size:.78rem;color:#065f46;margin-bottom:16px">
          <i class="fas fa-check-circle"></i> SMTP configuré — l'envoi d'emails est actif
        </div>
      <?php else: ?>
        <div style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:#fef3c7;border-radius:6px;font-size:.78rem;color:#92400e;margin-bottom:16px">
          <i class="fas fa-exclamation-triangle"></i> SMTP non configuré — les emails ne seront pas envoyés
        </div>
      <?php endif; ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
        <div class="fg"><label>Serveur SMTP (Host)</label><input type="text" name="smtp_host" value="<?= e($p['smtp_host']??'smtp.gmail.com') ?>" placeholder="smtp.gmail.com"/></div>
        <div class="fg"><label>Port</label><input type="number" name="smtp_port" value="<?= e($p['smtp_port']??'587') ?>" placeholder="587"/></div>
        <div class="fg">
          <label>Chiffrement</label>
          <select name="smtp_secure">
            <option value="tls" <?= ($p['smtp_secure']??'tls')==='tls'?'selected':'' ?>>TLS (recommandé — port 587)</option>
            <option value="ssl" <?= ($p['smtp_secure']??'')==='ssl'?'selected':'' ?>>SSL (port 465)</option>
          </select>
        </div>
        <div class="fg"><label>Adresse email expéditeur</label><input type="email" name="smtp_username" value="<?= e($p['smtp_username']??'') ?>" placeholder="contact@matelcom.ma"/></div>
        <div class="fg"><label>Mot de passe SMTP</label><input type="password" name="smtp_password" value="<?= e($p['smtp_password']??'') ?>" placeholder="••••••••" autocomplete="new-password"/></div>
        <div class="fg"><label>Nom affiché (expéditeur)</label><input type="text" name="smtp_from_name" value="<?= e($p['smtp_from_name']??($p['site_nom']??'MATELCOM')) ?>" placeholder="MATELCOM"/></div>
      </div>
      <p style="font-size:.75rem;color:var(--muted);margin-top:12px"><i class="fas fa-info-circle"></i> Pour Gmail : utilisez un <strong>mot de passe d'application</strong> (pas votre mot de passe habituel). Activez la validation en 2 étapes puis allez dans Compte Google → Sécurité → Mots de passe des applications.</p>
    </div>

  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:22px;padding:14px 32px;font-size:.95rem"><i class="fas fa-save"></i> Enregistrer les paramètres</button>
</form>

<?php require '_bottom.php'; ?>
