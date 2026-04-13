<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';
$p = allParams();
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Page non trouvée | <?= e($p['site_nom']??'MATELCOM') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;800&family=Inter:wght@400;500&display=swap" rel="stylesheet"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F5F5F5;color:#212121;text-align:center;padding:40px;}
.wrap{max-width:480px;}
.code{font-family:'Poppins',sans-serif;font-size:8rem;font-weight:800;color:#D32F2F;line-height:1;}
h1{font-family:'Poppins',sans-serif;font-size:1.5rem;font-weight:800;margin:12px 0 8px;}
p{color:#757575;margin-bottom:28px;}
a.btn{display:inline-flex;align-items:center;gap:8px;background:#D32F2F;color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600;transition:background .2s;}
a.btn:hover{background:#B71C1C;}
</style>
</head>
<body>
<div class="wrap">
  <div class="code">404</div>
  <h1>Page non trouvée</h1>
  <p>La page que vous cherchez n'existe pas ou a été déplacée.</p>
  <a href="index.php" class="btn"><i class="fas fa-home"></i> Retour à l'accueil</a>
</div>
</body>
</html>
