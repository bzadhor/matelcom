<?php
require_once __DIR__ . '/../libs/PHPMailer.php';
require_once __DIR__ . '/../libs/SMTP.php';
require_once __DIR__ . '/../libs/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Construit un mailer configuré depuis les paramètres DB (ou valeurs par défaut).
 */
function getMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = param('smtp_host',     'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = param('smtp_username', '');
    $mail->Password   = param('smtp_password', '');
    $mail->Port       = (int) param('smtp_port', 587);
    $mail->CharSet    = 'UTF-8';
    $secure = strtolower(param('smtp_secure', 'tls'));
    $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMIME : PHPMailer::ENCRYPTION_STARTTLS;
    return $mail;
}

/**
 * Vérifie que le SMTP est configuré dans les paramètres.
 */
function smtpIsConfigured() {
    return !empty(param('smtp_username')) && !empty(param('smtp_password'));
}

/**
 * Notifie l'admin d'une nouvelle demande de devis.
 */
function sendDevisAdmin($data) {
    if (!smtpIsConfigured()) return false;
    try {
        $mail = getMailer();
        $from = param('smtp_username', '');
        $mail->setFrom($from, param('smtp_from_name', 'MATELCOM'));
        $mail->addAddress(param('contact_email', $from));
        $mail->isHTML(true);
        $mail->Subject = 'Nouvelle demande de devis — ' . ($data['nom'] ?? 'Client');
        $mail->Body = '<h2>Nouvelle demande de devis</h2>'
            . '<p><strong>Nom :</strong> '      . htmlspecialchars($data['nom']      ?? '') . '</p>'
            . '<p><strong>Société :</strong> '  . htmlspecialchars($data['societe']  ?? '-') . '</p>'
            . '<p><strong>Tél :</strong> '      . htmlspecialchars($data['tel']      ?? '') . '</p>'
            . '<p><strong>Email :</strong> '    . htmlspecialchars($data['email']    ?? '') . '</p>'
            . '<p><strong>Produits :</strong> ' . htmlspecialchars($data['produits'] ?? '') . '</p>'
            . '<p><strong>Message :</strong><br>' . nl2br(htmlspecialchars($data['msg'] ?? '')) . '</p>';
        $mail->send();
        return true;
    } catch (\Exception $e) { return false; }
}

/**
 * Confirmation automatique au client après dépôt de devis.
 */
function sendDevisConfirmation($data) {
    if (!smtpIsConfigured()) return false;
    try {
        $email = $data['email'] ?? '';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $mail = getMailer();
        $from = param('smtp_username', '');
        $mail->setFrom($from, param('smtp_from_name', 'MATELCOM'));
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Confirmation de votre demande — MATELCOM';
        $nom = htmlspecialchars($data['nom'] ?? '');
        $siteName = htmlspecialchars(param('site_nom', 'MATELCOM'));
        $mail->Body = "
          <div style='font-family:Arial,sans-serif;max-width:580px;margin:0 auto;color:#222'>
            <div style='background:#D32F2F;padding:18px 28px'>
              <h2 style='color:white;margin:0;font-size:18px'>$siteName</h2>
            </div>
            <div style='padding:28px'>
              <p>Bonjour <strong>$nom</strong>,</p>
              <p>Nous avons bien reçu votre demande de devis et nous vous répondrons dans les meilleurs délais.</p>
              <p>Cordialement,<br>L'équipe $siteName</p>
            </div>
            <div style='background:#f5f5f5;padding:12px 28px;font-size:11px;color:#999'>
              Cet email est envoyé automatiquement, merci de ne pas y répondre directement.
            </div>
          </div>";
        $mail->send();
        return true;
    } catch (\Exception $e) { return false; }
}

/**
 * Envoie un message au client depuis l'administration.
 *
 * @param  array       $devis      Ligne de la table `devis`
 * @param  string      $sujet      Sujet de l'email
 * @param  string      $corps      Corps HTML de l'email
 * @param  string|null $pjPath     Chemin absolu vers la pièce jointe (optionnel)
 * @param  string|null $pjNom      Nom du fichier affiché dans l'email (optionnel)
 * @return array  ['ok'=>bool, 'error'=>string|null]
 */
function sendMessageClient(array $devis, string $sujet, string $corps, ?string $pjPath = null, ?string $pjNom = null): array {
    if (!smtpIsConfigured()) {
        return ['ok' => false, 'error' => 'SMTP non configuré. Rendez-vous dans Paramètres > SMTP.'];
    }
    $email = trim($devis['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Adresse email du client invalide ou absente.'];
    }
    try {
        $mail = getMailer();
        $from     = param('smtp_username', '');
        $fromName = param('smtp_from_name', 'MATELCOM');
        $siteName = htmlspecialchars(param('site_nom', 'MATELCOM'));
        $mail->setFrom($from, $fromName);
        $mail->addAddress($email, htmlspecialchars($devis['nom_complet'] ?? ''));
        $mail->isHTML(true);
        $mail->Subject = $sujet;

        // Enveloppe HTML
        $corpsHtml = nl2br(htmlspecialchars($corps));
        $mail->Body = "
          <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#222;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
            <div style='background:#D32F2F;padding:18px 28px;display:flex;align-items:center;gap:12px'>
              <h2 style='color:white;margin:0;font-size:18px'>$siteName</h2>
            </div>
            <div style='padding:28px;line-height:1.7'>
              $corpsHtml
            </div>
            <div style='background:#f5f5f5;padding:12px 28px;font-size:11px;color:#999;border-top:1px solid #e0e0e0'>
              " . htmlspecialchars(param('contact_email', '')) . " &nbsp;·&nbsp; " . htmlspecialchars(param('contact_telephone', '')) . "
            </div>
          </div>";
        $mail->AltBody = strip_tags($corps);

        // Pièce jointe
        if ($pjPath && file_exists($pjPath)) {
            $mail->addAttachment($pjPath, $pjNom ?: basename($pjPath));
        }

        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (\Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
