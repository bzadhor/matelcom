<?php
// $pageTitle et $activePage doivent être définis avant l'include
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin – <?= e($pageTitle??'') ?> | MATELCOM</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{--red:#D32F2F;--red-dark:#B71C1C;--red-light:#EF5350;--red-pale:#FFEBEE;--ink:#212121;--muted:#757575;--border:#E0E0E0;--soft:#F5F5F5;--sidebar:248px;}
body{font-family:'Inter',sans-serif;background:var(--soft);color:var(--ink);display:flex;min-height:100vh;}
.sidebar{width:var(--sidebar);background:linear-gradient(160deg,#1A1A2E,#16213E);position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:200;display:flex;flex-direction:column;}
.sb-brand{padding:22px 18px;border-bottom:1px solid rgba(255,255,255,.07);display:flex;align-items:center;gap:12px;}
.sb-mark{width:40px;height:40px;background:var(--red);border-radius:10px;display:flex;align-items:center;justify-content:center;color:white;font-family:'Poppins',sans-serif;font-weight:800;font-size:11px;flex-shrink:0;}
.sb-brand strong{color:white;font-family:'Poppins',sans-serif;font-size:1rem;}
.sb-brand small{color:rgba(255,255,255,.4);font-size:.72rem;display:block;}
.sb-nav{padding:14px 10px;flex:1;}
.sb-sec{font-size:.68rem;font-weight:700;letter-spacing:1.8px;color:rgba(255,255,255,.28);text-transform:uppercase;padding:8px 10px;margin-top:6px;}
.sb-link{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;color:rgba(255,255,255,.6);text-decoration:none;font-size:.85rem;font-weight:500;transition:all .2s;margin-bottom:2px;}
.sb-link:hover,.sb-link.on{background:rgba(211,47,47,.2);color:white;}
.sb-link .ico{font-size:1rem;width:20px;text-align:center;flex-shrink:0;}
.sb-foot{padding:14px 10px;border-top:1px solid rgba(255,255,255,.07);}
.sb-user{display:flex;align-items:center;gap:10px;padding:8px 12px;margin-bottom:4px;}
.sb-avatar{width:32px;height:32px;background:rgba(211,47,47,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:.8rem;flex-shrink:0;}
.sb-user strong{color:white;font-size:.82rem;display:block;}
.sb-user span{color:rgba(255,255,255,.35);font-size:.72rem;}
.sb-logout{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;color:rgba(255,255,255,.4);text-decoration:none;font-size:.82rem;transition:all .2s;}
.sb-logout:hover{background:rgba(255,255,255,.07);color:rgba(255,255,255,.75);}
.main{margin-left:var(--sidebar);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{background:white;border-bottom:1px solid var(--border);padding:0 28px;height:62px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar h1{font-family:'Poppins',sans-serif;font-size:1.2rem;font-weight:800;color:var(--ink);}
.topbar-right{display:flex;gap:12px;align-items:center;}
.site-link{display:flex;align-items:center;gap:6px;color:var(--red);font-size:.82rem;font-weight:600;text-decoration:none;background:var(--red-pale);padding:7px 14px;border-radius:8px;transition:background .2s;}
.site-link:hover{background:#ffcdd2;}
.content{padding:28px;flex:1;}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:26px;}
.stat-card{background:white;border-radius:14px;padding:22px;border:1px solid var(--border);position:relative;overflow:hidden;transition:transform .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.06);}
.stat-card .ico{font-size:1.4rem;margin-bottom:8px;}
.stat-card .lbl{font-size:.7rem;font-weight:700;letter-spacing:.5px;color:var(--muted);text-transform:uppercase;margin-bottom:5px;}
.stat-card .val{font-family:'Poppins',sans-serif;font-size:1.9rem;font-weight:800;color:var(--ink);line-height:1;}
.stat-card .sub{font-size:.74rem;color:var(--muted);margin-top:6px;}
.stat-card .stat-bg{position:absolute;right:-10px;bottom:-10px;font-size:4rem;opacity:.06;}
.card{background:white;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:22px;}
.card-header{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-header h2{font-size:.95rem;font-weight:700;color:var(--ink);}
table{width:100%;border-collapse:collapse;}
th{padding:10px 18px;text-align:left;font-size:.7rem;font-weight:700;letter-spacing:.5px;color:var(--muted);text-transform:uppercase;background:var(--soft);border-bottom:1px solid var(--border);}
td{padding:13px 18px;border-bottom:1px solid var(--border);font-size:.85rem;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#fafbff;}
.badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.bd-danger{background:#fee2e2;color:#dc2626;}
.bd-warning{background:#fef3c7;color:#d97706;}
.bd-success{background:#d1fae5;color:#059669;}
.bd-secondary{background:#f1f5f9;color:#64748b;}
.bd-red{background:var(--red-pale);color:var(--red);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-family:'Inter',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all .2s;}
.btn-primary{background:var(--red);color:white;box-shadow:0 3px 10px rgba(211,47,47,.2);}
.btn-primary:hover{background:var(--red-dark);transform:translateY(-1px);}
.btn-success{background:#059669;color:white;}.btn-success:hover{background:#047857;}
.btn-warning{background:#d97706;color:white;}
.btn-danger{background:#dc2626;color:white;}.btn-danger:hover{background:#b91c1c;}
.btn-secondary{background:var(--soft);color:var(--ink);border:1px solid var(--border);}
.btn-secondary:hover{background:#e8ecf4;}
.btn-sm{padding:5px 11px;font-size:.75rem;}
.fg{margin-bottom:0;}
.fg label{display:block;font-size:.78rem;font-weight:600;letter-spacing:.3px;color:var(--ink);margin-bottom:6px;}
.fg input,.fg select,.fg textarea{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;font-family:'Inter',sans-serif;color:var(--ink);outline:none;transition:border-color .2s;background:white;}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(211,47,47,.07);}
.fg textarea{resize:vertical;min-height:90px;}
.fg small{display:block;font-size:.72rem;color:var(--muted);margin-top:4px;}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.toggle{position:relative;display:inline-block;width:42px;height:22px;}
.toggle input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:22px;transition:.3s;}
.toggle-slider:before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s;}
.toggle input:checked+.toggle-slider{background:var(--red);}
.toggle input:checked+.toggle-slider:before{transform:translateX(20px);}
.img-prev{width:54px;height:54px;object-fit:cover;border-radius:8px;border:1px solid var(--border);}
.img-ph{width:54px;height:54px;background:var(--soft);border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);}
.alert{padding:12px 16px;border-radius:9px;font-size:.85rem;margin-bottom:18px;}
.alert-success{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46;}
.alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}
.pag{display:flex;gap:6px;align-items:center;justify-content:flex-end;margin-top:14px;}
.pag a{padding:6px 11px;border-radius:7px;border:1px solid var(--border);background:white;color:var(--ink);font-size:.8rem;text-decoration:none;transition:all .2s;}
.pag a:hover,.pag a.on{background:var(--red);color:white;border-color:var(--red);}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;border-radius:7px;background:var(--soft);border:1px solid var(--border);}
.hamburger span{display:block;width:20px;height:2px;background:var(--ink);border-radius:2px;transition:all .3s;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:199;}
.sidebar-overlay.open{display:block;}
@media(max-width:768px){
  :root{--sidebar:0px;}
  .sidebar{transform:translateX(-100%);transition:transform .3s ease;width:260px;z-index:201;}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0!important;}
  .topbar{padding:0 16px;}
  .content{padding:14px;}
  .hamburger{display:flex;}
  .stats-row{grid-template-columns:1fr 1fr!important;}
  .fgrid{grid-template-columns:1fr!important;}
  .card{overflow-x:auto;}table{min-width:500px;}
}
@media(max-width:480px){.stats-row{grid-template-columns:1fr!important;}}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-mark">MC</div>
    <div><strong>MATELCOM</strong><small>Administration</small></div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Principal</div>
    <?php if (gh_has_page_permission('dashboard','read')): ?>
      <a href="index.php" class="sb-link <?= ($activePage??'')==='dashboard'?'on':'' ?>"><span class="ico"><i class="fas fa-chart-bar"></i></span>Tableau de bord</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('produits','read')): ?>
      <a href="produits.php" class="sb-link <?= ($activePage??'')==='produits'?'on':'' ?>"><span class="ico"><i class="fas fa-boxes"></i></span>Produits</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('categories','read')): ?>
      <a href="categories.php" class="sb-link <?= ($activePage??'')==='categories'?'on':'' ?>"><span class="ico"><i class="fas fa-tags"></i></span>Catégories</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('devis','read')): ?>
      <a href="devis.php" class="sb-link <?= ($activePage??'')==='devis'?'on':'' ?>"><span class="ico"><i class="fas fa-file-invoice"></i></span>Devis reçus</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('devis_generateur','read')): ?>
      <a href="devis_generateur.php" class="sb-link <?= ($activePage??'')==='devis_generateur'?'on':'' ?>"><span class="ico"><i class="fas fa-file-invoice-dollar"></i></span>Générer un devis</a>
    <?php endif; ?>

    <div class="sb-sec">Contenu</div>
    <?php if (gh_has_page_permission('temoignages','read')): ?>
      <a href="temoignages.php" class="sb-link <?= ($activePage??'')==='temoignages'?'on':'' ?>"><span class="ico"><i class="fas fa-comments"></i></span>Témoignages</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('menu','read')): ?>
      <a href="menu.php" class="sb-link <?= ($activePage??'')==='menu'?'on':'' ?>"><span class="ico"><i class="fas fa-bars"></i></span>Menu</a>
    <?php endif; ?>

    <div class="sb-sec">Configuration</div>
    <?php if (gh_has_page_permission('parametres','read')): ?>
      <a href="parametres.php" class="sb-link <?= ($activePage??'')==='parametres'?'on':'' ?>"><span class="ico"><i class="fas fa-cog"></i></span>Paramètres</a>
    <?php endif; ?>
    <?php if (gh_has_page_permission('compte','read')): ?>
      <a href="compte.php" class="sb-link <?= ($activePage??'')==='compte'?'on':'' ?>"><span class="ico"><i class="fas fa-user-shield"></i></span>Mon compte</a>
    <?php endif; ?>

    <?php if (gh_is_super_admin()): ?>
      <div class="sb-sec">Sécurité</div>
      <a href="users.php" class="sb-link <?= ($activePage??'')==='users'?'on':'' ?>"><span class="ico"><i class="fas fa-users-cog"></i></span>Utilisateurs</a>
    <?php endif; ?>
  </nav>
  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-avatar"><i class="fas fa-user"></i></div>
      <div><strong><?= e($_SESSION['admin_user']??'Admin') ?></strong><span><?= e(gh_role_label()) ?></span></div>
    </div>
    <form method="POST" action="logout.php" style="margin:0">
      <?= csrf_input('admin_logout') ?>
      <button type="submit" class="sb-logout" style="width:100%;background:none;border:none;text-align:left;cursor:pointer;font-family:inherit;">
        <i class="fas fa-sign-out-alt"></i>Déconnexion
      </button>
    </form>
  </div>
</aside>
<div class="main">
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu"><span></span><span></span><span></span></button>
      <h1><?= e($pageTitle??'') ?></h1>
    </div>
    <div class="topbar-right">
      <a href="../index.php" target="_blank" rel="noopener noreferrer" class="site-link"><i class="fas fa-external-link-alt"></i><span>Voir le site</span></a>
    </div>
  </div>
  <div class="content">
    <?php $f=getFlash(); if($f): ?>
      <div class="alert alert-<?= $f['type']==='success'?'success':'error' ?>">
        <?= $f['type']==='success'?'✅':'⚠️' ?> <?= e($f['msg']) ?>
      </div>
    <?php endif; ?>
<script>
function toggleSidebar(){
  document.querySelector('.sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
</script>
