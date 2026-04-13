<?php
require_once __DIR__ . '/../libs/PHPMailer.php';
require_once __DIR__ . '/../libs/SMTP.php';
require_once __DIR__ . '/../libs/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

function getMailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // À configurer
    $mail->SMTPAuth   = true;
    $mail->Username   = ''; // À configurer
    $mail->Password   = ''; // À configurer
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    return $mail;
}

function sendDevisAdmin($data) {
    try {
        $mail = getMailer();
        $mail->setFrom($mail->Username, 'MATELCOM Site');
        $mail->addAddress(param('contact_email', 'contact@matelcom.ma'));
        $mail->isHTML(true);
        $mail->Subject = 'Nouvelle demande de devis - ' . ($data['nom'] ?? 'Client');
        $mail->Body = '<h2>Nouvelle demande de devis</h2>'
            . '<p><strong>Nom :</strong> ' . htmlspecialchars($data['nom'] ?? '') . '</p>'
            . '<p><strong>Société :</strong> ' . htmlspecialchars($data['societe'] ?? '-') . '</p>'
            . '<p><strong>Tél :</strong> ' . htmlspecialchars($data['tel'] ?? '') . '</p>'
            . '<p><strong>Email :</strong> ' . htmlspecialchars($data['email'] ?? '') . '</p>'
            . '<p><strong>Produits :</strong> ' . htmlspecialchars($data['produits'] ?? '') . '</p>'
            . '<p><strong>Quantité :</strong> ' . htmlspecialchars($data['quantite'] ?? '') . '</p>'
            . '<p><strong>Message :</strong><br>' . nl2br(htmlspecialchars($data['msg'] ?? '')) . '</p>';
        $mail->send();
    } catch (\Exception $e) { /* silently fail */ }
}

function sendDevisConfirmation($data) {
    try {
        $email = $data['email'] ?? '';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        $mail = getMailer();
        $mail->setFrom($mail->Username, 'MATELCOM');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Confirmation de votre demande - MATELCOM';
        $mail->Body = '<h2>Merci pour votre demande !</h2>'
            . '<p>Bonjour ' . htmlspecialchars($data['nom'] ?? '') . ',</p>'
            . '<p>Nous avons bien reçu votre demande de devis et nous vous répondrons dans les plus brefs délais.</p>'
            . '<p>Cordialement,<br>L\'équipe MATELCOM</p>';
        $mail->send();
    } catch (\Exception $e) { /* silently fail */ }
}
