<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/auth.php';

if (isLogged()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf_or_fail('admin_login');
    $bucket = 'login:'.clientIp();
    if (rateLimitReached($bucket, 5, 300)) {
        $error = 'Trop de tentatives. Réessayez dans 5 minutes.';
    } else {
        $u = trim($_POST['username']??'');
        $pw = $_POST['password']??'';
        if (loginAdmin($u, $pw)) { header('Location: index.php'); exit; }
        rateLimitBump($bucket, 300);
        $error = 'Identifiants incorrects.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Connexion Admin | MATELCOM</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1A1A2E 0%,#16213E 50%,#B71C1C 100%);padding:20px;}
.login-card{background:white;border-radius:20px;padding:48px 40px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.login-logo{text-align:center;margin-bottom:32px;}
.login-logo .mark{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:#D32F2F;border-radius:14px;color:white;font-family:'Poppins',sans-serif;font-weight:800;font-size:18px;margin-bottom:12px;}
.login-logo h1{font-family:'Poppins',sans-serif;font-size:1.4rem;font-weight:800;color:#212121;}
.login-logo h1 span{color:#D32F2F;}
.login-logo p{color:#757575;font-size:.85rem;margin-top:4px;}
.fg{margin-bottom:18px;}
.fg label{display:block;font-size:.82rem;font-weight:600;color:#424242;margin-bottom:6px;}
.fg input{width:100%;padding:12px 14px;border:1.5px solid #E0E0E0;border-radius:10px;font-size:.9rem;font-family:'Inter',sans-serif;outline:none;transition:all .2s;}
.fg input:focus{border-color:#D32F2F;box-shadow:0 0 0 3px rgba(211,47,47,.1);}
.login-btn{width:100%;padding:14px;background:#D32F2F;color:white;border:none;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:24px;}
.login-btn:hover{background:#B71C1C;transform:translateY(-1px);}
.error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px;border-radius:10px;font-size:.85rem;margin-bottom:18px;text-align:center;}
.back-link{display:block;text-align:center;margin-top:20px;color:#757575;font-size:.82rem;text-decoration:none;}
.back-link:hover{color:#D32F2F;}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <div class="mark">MC</div>
    <h1>MATEL<span>COM</span></h1>
    <p>Espace Administration</p>
  </div>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="POST">
    <?= csrf_input('admin_login') ?>
    <div class="fg"><label>Nom d'utilisateur</label><input type="text" name="username" required autofocus placeholder="admin"/></div>
    <div class="fg"><label>Mot de passe</label><input type="password" name="password" required placeholder="••••••••"/></div>
    <button type="submit" class="login-btn"><i class="fas fa-sign-in-alt"></i> Se connecter</button>
  </form>
  <a href="../index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour au site</a>
</div>
</body>
</html>
