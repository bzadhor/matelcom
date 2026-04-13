<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';
requireAdmin();
$db = getDB();
$pageTitle  = 'Mon compte';
$activePage = 'compte';

$admin = $db->prepare("SELECT * FROM admins WHERE id=?");
$admin->execute([$_SESSION['admin_id']]); $admin = $admin->fetch();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf_or_fail('compte_form');
    $email = limitString($_POST['email']??'',150);
    $current = $_POST['current_password']??'';
    $new = $_POST['new_password']??'';
    $confirm = $_POST['confirm_password']??'';

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $db->prepare("UPDATE admins SET email=? WHERE id=?")->execute([$email,$admin['id']]);
    }

    if ($new) {
        if (!password_verify($current, $admin['password'])) {
            setFlash('error','Mot de passe actuel incorrect.');
            header('Location: compte.php'); exit;
        }
        if ($new !== $confirm) {
            setFlash('error','Les mots de passe ne correspondent pas.');
            header('Location: compte.php'); exit;
        }
        if (strlen($new) < 6) {
            setFlash('error','Le mot de passe doit contenir au moins 6 caractères.');
            header('Location: compte.php'); exit;
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare("UPDATE admins SET password=? WHERE id=?")->execute([$hash,$admin['id']]);
    }

    setFlash('success','Compte mis à jour.');
    header('Location: compte.php'); exit;
}

require '_top.php';
?>

<div style="max-width:560px">
  <div class="card" style="padding:28px">
    <h3 style="font-family:'Poppins',sans-serif;font-weight:700;margin-bottom:20px"><i class="fas fa-user-shield" style="color:var(--red);margin-right:8px"></i> Mon compte</h3>
    <form method="POST">
      <?= csrf_input('compte_form') ?>
      <div class="fg" style="margin-bottom:14px"><label>Nom d'utilisateur</label><input type="text" value="<?= e($admin['username']) ?>" disabled style="background:var(--soft)"/></div>
      <div class="fg" style="margin-bottom:14px"><label>Email</label><input type="email" name="email" value="<?= e($admin['email']) ?>"/></div>
      <hr style="border:none;border-top:1px solid var(--border);margin:20px 0"/>
      <p style="font-size:.82rem;color:var(--muted);margin-bottom:14px">Laisser vide pour ne pas changer le mot de passe.</p>
      <div class="fg" style="margin-bottom:14px"><label>Mot de passe actuel</label><input type="password" name="current_password"/></div>
      <div class="fgrid" style="margin-bottom:14px">
        <div class="fg"><label>Nouveau mot de passe</label><input type="password" name="new_password"/></div>
        <div class="fg"><label>Confirmer</label><input type="password" name="confirm_password"/></div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
    </form>
  </div>
</div>

<?php require '_bottom.php'; ?>
