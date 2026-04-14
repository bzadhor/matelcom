<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/tracker.php';
trackVisite();

// Traitement devis
$devis_ok = false; $devis_errors = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['envoyer_devis'])) {
    verify_csrf_or_fail('quote_form');
    $nom      = limitString($_POST['nom_complet']??'', 150);
    $societe  = limitString($_POST['societe']??'', 200);
    $tel      = limitString($_POST['telephone']??'', 50);
    $email    = limitString($_POST['email']??'', 150);
    $produits_d = limitString($_POST['produits_demandes']??'', 500);
    $quantite = limitString($_POST['quantite']??'', 100);
    $msg      = limitString($_POST['message']??'', 4000);
    $honeypot = trim($_POST['website']??'');
    $quoteBucket = 'quote:'.clientIp();

    if ($honeypot !== '') $devis_errors[]='Demande invalide.';
    if (!$nom) $devis_errors[]='Le nom est obligatoire.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $devis_errors[]='Email invalide.';
    if (rateLimitReached($quoteBucket, 5, 900)) $devis_errors[]='Trop de demandes. Réessayez dans quelques minutes.';

    // Upload fichier optionnel
    $fichier = '';
    if (!empty($_FILES['fichier']) && $_FILES['fichier']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = uploadFile($_FILES['fichier'], 'devis_fichiers');
        if (isset($up['error'])) $devis_errors[] = $up['error'];
        else $fichier = $up['filename'];
    }

    if (empty($devis_errors)) {
        getDB()->prepare("INSERT INTO devis(nom_complet,societe,telephone,email,produits_demandes,quantite,message,fichier,ip_address) VALUES(?,?,?,?,?,?,?,?,?)")
               ->execute([$nom,$societe,$tel,$email,$produits_d,$quantite,$msg,$fichier,clientIp()]);
        rateLimitBump($quoteBucket, 900);
        require_once __DIR__ . '/includes/mailer.php';
        $devisData = ['nom'=>$nom,'email'=>$email,'tel'=>$tel,'societe'=>$societe,'produits'=>$produits_d,'quantite'=>$quantite,'msg'=>$msg];
        sendDevisAdmin($devisData);
        sendDevisConfirmation($devisData);
        $devis_ok = true;
    }
}

$p        = allParams();
$produits = getProduits();
$testis   = getTemoignages();
$cats     = getCategories();
$partenaires = getPartenaires();
$logoUrl  = imgUrl($p['logo']??'');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico"/>
<title><?= e($p['site_nom']??'MATELCOM') ?> | <?= e($p['site_slogan']??'Premium Solutions') ?></title>
<meta name="description" content="MATELCOM - Vente de matériel informatique au Maroc. RAM, SSD, HDD, logiciels Microsoft, antivirus Kaspersky. Partenaire officiel depuis +24 ans."/>
<meta property="og:title" content="<?= e($p['site_nom']??'MATELCOM') ?>"/>
<meta property="og:description" content="Matériel informatique de qualité au Maroc"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --red:#D32F2F;--red-dark:#B71C1C;--red-light:#EF5350;--red-pale:#FFEBEE;
  --white:#FFFFFF;--gray-50:#FAFAFA;--gray-100:#F5F5F5;--gray-200:#EEEEEE;
  --gray-300:#E0E0E0;--gray-400:#BDBDBD;--gray-500:#9E9E9E;--gray-600:#757575;
  --gray-700:#616161;--gray-800:#424242;--gray-900:#212121;
  --dark:#1A1A2E;--dark-light:#16213E;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:'Inter',sans-serif;color:var(--gray-900);background:var(--white);}

/* ── NAV ── */
nav{position:fixed;top:0;left:0;right:0;z-index:1000;background:rgba(255,255,255,.97);backdrop-filter:blur(20px);display:flex;align-items:center;justify-content:space-between;padding:0 5%;height:72px;border-bottom:1px solid var(--gray-200);transition:box-shadow .3s;}
nav.scrolled{box-shadow:0 2px 20px rgba(0,0,0,.08);}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo img{height:44px;}
.nav-logo-text{font-family:'Poppins',sans-serif;font-weight:800;font-size:1.5rem;color:var(--gray-900);}
.nav-logo-text span{color:var(--red);}
.nav-links{display:flex;align-items:center;gap:32px;}
.nav-links a{color:var(--gray-700);text-decoration:none;font-size:.88rem;font-weight:500;transition:color .2s;position:relative;}
.nav-links a:hover{color:var(--red);}
.nav-cta{background:var(--red)!important;color:white!important;padding:10px 24px;border-radius:8px;font-weight:600!important;transition:all .2s!important;box-shadow:0 4px 12px rgba(211,47,47,.3);}
.nav-cta:hover{background:var(--red-dark)!important;transform:translateY(-1px);box-shadow:0 6px 16px rgba(211,47,47,.4);}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:8px;border-radius:6px;background:transparent;border:none;}
.hamburger span{width:22px;height:2px;background:var(--gray-900);border-radius:2px;transition:all .3s;}

/* ── HERO ── */
#hero{min-height:100vh;background:linear-gradient(135deg,var(--dark) 0%,#0F3460 50%,var(--red-dark) 100%);display:flex;align-items:center;padding:100px 5% 60px;position:relative;overflow:hidden;}
#hero::before{content:'';position:absolute;top:-150px;right:-150px;width:500px;height:500px;background:radial-gradient(circle,rgba(211,47,47,.2) 0%,transparent 70%);border-radius:50%;}
#hero::after{content:'';position:absolute;bottom:-80px;left:-80px;width:350px;height:350px;background:radial-gradient(circle,rgba(211,47,47,.12) 0%,transparent 70%);border-radius:50%;}
.hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;max-width:1300px;margin:0 auto;width:100%;z-index:1;position:relative;}
.hero-content{animation:fadeUp .8s ease both;}
.hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(211,47,47,.15);border:1px solid rgba(211,47,47,.3);color:#FF8A80;padding:6px 16px;border-radius:50px;font-size:.78rem;font-weight:600;margin-bottom:24px;letter-spacing:.5px;}
.hero-content h1{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(2rem,4.5vw,3.2rem);color:white;line-height:1.15;margin-bottom:20px;}
.hero-content h1 span{color:var(--red-light);}
.hero-content p{color:rgba(255,255,255,.7);font-size:1rem;line-height:1.75;margin-bottom:36px;max-width:520px;}
.hero-btns{display:flex;gap:14px;flex-wrap:wrap;}
.btn-hero{background:var(--red);color:white;padding:14px 30px;border-radius:10px;font-weight:700;font-size:.95rem;text-decoration:none;transition:all .2s;box-shadow:0 8px 24px rgba(211,47,47,.4);display:inline-flex;align-items:center;gap:8px;}
.btn-hero:hover{background:var(--red-dark);transform:translateY(-2px);box-shadow:0 12px 30px rgba(211,47,47,.5);}
.btn-hero-outline{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.25);color:white;padding:14px 30px;border-radius:10px;font-weight:600;font-size:.95rem;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:8px;}
.btn-hero-outline:hover{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.4);}
.hero-stats{display:flex;gap:40px;margin-top:48px;flex-wrap:wrap;}
.hero-stat .num{font-family:'Poppins',sans-serif;font-size:2rem;font-weight:800;color:white;}
.hero-stat .num span{color:var(--red-light);}
.hero-stat .label{color:rgba(255,255,255,.5);font-size:.8rem;margin-top:2px;}
.hero-visual{display:flex;justify-content:center;align-items:center;z-index:1;animation:fadeIn 1s ease .3s both;}
.hero-cards{display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:420px;}
.hero-card{background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:24px 20px;text-align:center;transition:transform .3s;}
.hero-card:hover{transform:translateY(-4px);}
.hero-card i{font-size:2rem;color:var(--red-light);margin-bottom:10px;}
.hero-card h3{color:white;font-family:'Poppins',sans-serif;font-size:.9rem;font-weight:700;margin-bottom:4px;}
.hero-card p{color:rgba(255,255,255,.5);font-size:.75rem;}

/* ── SECTIONS ── */
section{padding:90px 5%;}
.section-tag{display:inline-block;background:var(--red-pale);color:var(--red);padding:5px 16px;border-radius:50px;font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;}
.section-title{font-family:'Poppins',sans-serif;font-weight:800;font-size:clamp(1.6rem,3vw,2.4rem);color:var(--gray-900);line-height:1.2;margin-bottom:14px;}
.section-title span{color:var(--red);}
.section-sub{color:var(--gray-600);font-size:.95rem;line-height:1.7;max-width:560px;}
.section-header{margin-bottom:48px;}
.section-header.center{text-align:center;}.section-header.center .section-sub{margin:0 auto;}

/* ── CATEGORIES STRIP ── */
#categories-strip{background:var(--gray-900);padding:50px 5%;}
.cats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;max-width:1100px;margin:0 auto;}
.cat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:28px 20px;text-align:center;transition:all .3s;cursor:pointer;text-decoration:none;}
.cat-card:hover{background:rgba(211,47,47,.15);border-color:var(--red);transform:translateY(-4px);}
.cat-card i{font-size:1.8rem;color:var(--red-light);margin-bottom:12px;display:block;}
.cat-card h3{font-family:'Poppins',sans-serif;font-weight:700;color:white;font-size:.9rem;margin-bottom:4px;}
.cat-card p{color:rgba(255,255,255,.4);font-size:.75rem;}

/* ── PRODUITS ── */
#produits{background:var(--gray-100);}
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:36px;}
.filter-btn{padding:8px 20px;border-radius:50px;border:1.5px solid var(--gray-300);background:white;color:var(--gray-600);font-size:.84rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:'Inter',sans-serif;}
.filter-btn.active,.filter-btn:hover{background:var(--red);color:white;border-color:var(--red);}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;}
.product-card{background:white;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04);transition:all .3s;border:1px solid var(--gray-200);}
.product-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(0,0,0,.1);}
.product-img{height:200px;overflow:hidden;position:relative;background:var(--gray-100);display:flex;align-items:center;justify-content:center;}
.product-img img{width:100%;height:100%;object-fit:contain;padding:16px;transition:transform .4s;}
.product-card:hover .product-img img{transform:scale(1.05);}
.product-badge{position:absolute;top:12px;right:12px;color:white;padding:4px 12px;border-radius:50px;font-size:.7rem;font-weight:700;}
.product-dispo{position:absolute;top:12px;left:12px;padding:4px 10px;border-radius:50px;font-size:.68rem;font-weight:600;background:white;border:1px solid var(--gray-200);}
.product-body{padding:20px;}
.product-marque{color:var(--red);font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.product-body h3{font-family:'Poppins',sans-serif;font-weight:700;font-size:.95rem;color:var(--gray-900);margin-bottom:4px;}
.product-body .model{color:var(--gray-500);font-size:.82rem;margin-bottom:10px;}
.product-body p{color:var(--gray-600);font-size:.83rem;line-height:1.6;margin-bottom:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.product-tags{display:flex;gap:5px;flex-wrap:wrap;margin-bottom:16px;}
.product-tag{background:var(--red-pale);color:var(--red);padding:3px 10px;border-radius:50px;font-size:.7rem;font-weight:600;}
.product-actions{display:flex;gap:8px;}
.product-btn{flex:1;text-align:center;background:var(--red);color:white;padding:10px;border-radius:8px;font-weight:600;font-size:.84rem;text-decoration:none;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;}
.product-btn:hover{background:var(--red-dark);}
.product-btn-compare{flex:1;text-align:center;background:white;color:var(--gray-800);padding:10px;border-radius:8px;font-weight:700;font-size:.82rem;text-decoration:none;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;border:1px solid var(--gray-300);cursor:pointer;font-family:'Inter',sans-serif;}
.product-btn-compare:hover{border-color:var(--red);color:var(--red);background:var(--red-pale);}
.product-btn-compare.active{background:var(--gray-900);border-color:var(--gray-900);color:white;}
.product-btn-wa{flex:0 0 44px;background:#25D366;color:white;padding:10px;border-radius:8px;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.product-btn-wa:hover{background:#1ea952;}
.compare-dock{position:fixed;left:50%;bottom:20px;transform:translateX(-50%) translateY(120px);width:min(1000px,calc(100% - 32px));background:rgba(26,26,46,.96);color:white;border:1px solid rgba(255,255,255,.08);border-radius:22px;box-shadow:0 20px 60px rgba(0,0,0,.28);padding:16px 18px;z-index:1200;transition:transform .3s ease,opacity .3s ease;opacity:0;backdrop-filter:blur(18px);}
.compare-dock.open{transform:translateX(-50%) translateY(0);opacity:1;}
.compare-dock-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;}
.compare-dock-title{font-family:'Poppins',sans-serif;font-size:.98rem;font-weight:700;display:flex;align-items:center;gap:10px;}
.compare-dock-sub{font-size:.78rem;color:rgba(255,255,255,.58);}
.compare-dock-content{display:flex;align-items:center;gap:16px;justify-content:space-between;flex-wrap:wrap;}
.compare-pills{display:flex;gap:10px;flex-wrap:wrap;flex:1;}
.compare-pill{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);padding:8px 10px;border-radius:999px;font-size:.8rem;}
.compare-pill button{width:22px;height:22px;border:none;border-radius:999px;background:rgba(255,255,255,.12);color:white;cursor:pointer;}
.compare-pill button:hover{background:rgba(255,255,255,.2);}
.compare-dock-actions{display:flex;align-items:center;gap:10px;}
.compare-link{display:inline-flex;align-items:center;gap:8px;background:var(--red);color:white;text-decoration:none;padding:11px 18px;border-radius:12px;font-weight:700;font-size:.84rem;box-shadow:0 12px 26px rgba(211,47,47,.28);}
.compare-link:hover{background:var(--red-dark);}
.compare-clear{background:transparent;color:rgba(255,255,255,.72);border:1px solid rgba(255,255,255,.14);padding:10px 14px;border-radius:12px;cursor:pointer;font-weight:600;}
.compare-clear:hover{background:rgba(255,255,255,.08);color:white;}

/* ── AVANTAGES ── */
#avantages{background:white;}
.avantages-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:28px;}
.avantage-card{padding:32px 28px;border-radius:16px;border:1px solid var(--gray-200);transition:all .3s;position:relative;overflow:hidden;}
.avantage-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--red);transform:scaleX(0);transition:transform .3s;transform-origin:left;}
.avantage-card:hover{border-color:var(--red-light);box-shadow:0 8px 24px rgba(211,47,47,.08);}
.avantage-card:hover::before{transform:scaleX(1);}
.avantage-icon{width:52px;height:52px;background:var(--red-pale);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;}
.avantage-icon i{color:var(--red);font-size:1.3rem;}
.avantage-card h3{font-family:'Poppins',sans-serif;font-weight:700;font-size:1rem;margin-bottom:8px;color:var(--gray-900);}
.avantage-card p{color:var(--gray-600);font-size:.88rem;line-height:1.65;}

/* ── À PROPOS ── */
#apropos{background:var(--gray-100);}
.about-layout{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;max-width:1200px;margin:0 auto;}
.about-visual{position:relative;}
.about-visual-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.about-stat-card{background:white;border-radius:14px;padding:24px 20px;border:1px solid var(--gray-200);text-align:center;transition:transform .3s;}
.about-stat-card:hover{transform:translateY(-4px);}
.about-stat-card .big{font-family:'Poppins',sans-serif;font-size:2rem;font-weight:800;color:var(--red);}
.about-stat-card .small{color:var(--gray-600);font-size:.8rem;margin-top:4px;}
.about-stat-card:first-child{grid-column:1/-1;background:var(--red);border-color:var(--red);}
.about-stat-card:first-child .big{color:white;font-size:3rem;}
.about-stat-card:first-child .small{color:rgba(255,255,255,.7);}
.about-content .section-sub{max-width:none;margin-bottom:24px;}
.about-list{list-style:none;display:flex;flex-direction:column;gap:12px;margin-bottom:28px;}
.about-list li{display:flex;align-items:flex-start;gap:12px;color:var(--gray-700);font-size:.9rem;line-height:1.6;}
.about-list li i{color:var(--red);margin-top:4px;flex-shrink:0;}
.partners-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:28px;padding-top:28px;border-top:1px solid var(--gray-300);}
.partner-badge{background:white;border:1px solid var(--gray-200);border-radius:10px;padding:10px 18px;font-weight:700;font-size:.85rem;color:var(--gray-800);transition:all .2s;}
.partner-badge:hover{border-color:var(--red);color:var(--red);}

/* ── TEMOIGNAGES ── */
#temoignages{background:white;}
.testi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;}
.testi-card{background:var(--gray-50);border-radius:16px;padding:28px;border:1px solid var(--gray-200);transition:all .25s;}
.testi-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.06);}
.testi-stars{color:#F59E0B;font-size:.9rem;letter-spacing:2px;margin-bottom:14px;}
.testi-text{color:var(--gray-700);font-size:.9rem;line-height:1.75;margin-bottom:20px;font-style:italic;}
.testi-author{display:flex;align-items:center;gap:12px;}
.testi-avatar{width:44px;height:44px;border-radius:50%;overflow:hidden;background:var(--red-pale);flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.testi-avatar img{width:100%;height:100%;object-fit:cover;}
.testi-name{font-size:.88rem;font-weight:700;color:var(--gray-900);}
.testi-role{font-size:.76rem;color:var(--gray-500);}

/* ── CONTACT / DEVIS ── */
#contact{background:var(--gray-100);}
.contact-layout{display:grid;grid-template-columns:1fr 1.3fr;gap:48px;max-width:1200px;margin:0 auto;}
.contact-info h3{font-family:'Poppins',sans-serif;font-size:1.4rem;font-weight:800;color:var(--gray-900);margin-bottom:12px;}
.contact-info>p{color:var(--gray-600);line-height:1.7;margin-bottom:28px;}
.contact-detail{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px;}
.contact-detail-icon{width:44px;height:44px;background:var(--red-pale);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.contact-detail-icon i{color:var(--red);font-size:1rem;}
.contact-detail-text strong{display:block;color:var(--gray-900);font-size:.85rem;font-weight:600;}
.contact-detail-text span{color:var(--gray-600);font-size:.88rem;}
.form-card{background:white;border-radius:18px;padding:36px;box-shadow:0 4px 24px rgba(0,0,0,.06);border:1px solid var(--gray-200);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group.full{grid-column:1/-1;}
.form-group label{font-size:.82rem;font-weight:600;color:var(--gray-800);}
.form-group input,.form-group select,.form-group textarea{padding:11px 14px;border:1.5px solid var(--gray-300);border-radius:8px;font-family:'Inter',sans-serif;font-size:.88rem;color:var(--gray-900);background:var(--gray-50);transition:all .2s;outline:none;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--red);box-shadow:0 0 0 3px rgba(211,47,47,.08);background:white;}
.form-group textarea{resize:vertical;min-height:100px;}
.form-submit{margin-top:20px;width:100%;background:var(--red);color:white;border:none;padding:14px;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:10px;}
.form-submit:hover{background:var(--red-dark);transform:translateY(-1px);}
.alert-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:14px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}
.alert-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:14px 18px;border-radius:10px;margin-bottom:16px;font-size:.9rem;}

/* ── FOOTER ── */
footer{background:var(--gray-900);color:rgba(255,255,255,.6);padding:60px 5% 30px;}
.footer-top{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:48px;max-width:1200px;margin-left:auto;margin-right:auto;margin-bottom:48px;}
.footer-brand .brand{font-family:'Poppins',sans-serif;font-weight:800;font-size:1.5rem;color:white;margin-bottom:12px;}
.footer-brand .brand span{color:var(--red);}
.footer-brand p{font-size:.86rem;line-height:1.7;max-width:280px;}
.footer-col h4{font-family:'Poppins',sans-serif;color:white;font-size:.88rem;font-weight:700;margin-bottom:18px;}
.footer-col a{display:block;color:rgba(255,255,255,.5);text-decoration:none;font-size:.86rem;margin-bottom:10px;transition:color .2s;}
.footer-col a:hover{color:var(--red-light);}
.footer-bottom{border-top:1px solid rgba(255,255,255,.1);padding-top:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;max-width:1200px;margin:0 auto;}
.footer-bottom p{font-size:.82rem;}
.social-links{display:flex;gap:10px;}
.social-links a{width:36px;height:36px;background:rgba(255,255,255,.07);border-radius:8px;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);text-decoration:none;transition:all .2s;}
.social-links a:hover{background:var(--red);color:white;}

/* ── WHATSAPP FLOAT ── */
.wa-float{position:fixed;bottom:24px;right:24px;z-index:999;width:56px;height:56px;background:#25D366;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.6rem;text-decoration:none;box-shadow:0 4px 16px rgba(37,211,102,.4);transition:all .3s;animation:pulse 2s infinite;}
.wa-float:hover{transform:scale(1.1);box-shadow:0 6px 24px rgba(37,211,102,.5);}

@keyframes fadeUp{from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
@keyframes pulse{0%,100%{box-shadow:0 4px 16px rgba(37,211,102,.4);}50%{box-shadow:0 4px 24px rgba(37,211,102,.6);}}

@media(max-width:900px){
  .hero-grid{grid-template-columns:1fr;text-align:center;}
  .hero-content p{margin-left:auto;margin-right:auto;}
  .hero-btns{justify-content:center;}
  .hero-stats{justify-content:center;}
  .hero-visual{margin-top:32px;}
  .about-layout{grid-template-columns:1fr;}
  .contact-layout{grid-template-columns:1fr;}
  .footer-top{grid-template-columns:1fr 1fr;}
  .nav-links{display:none;flex-direction:column;position:absolute;top:72px;left:0;right:0;background:rgba(255,255,255,.98);padding:20px 5%;gap:16px;border-bottom:1px solid var(--gray-200);box-shadow:0 8px 24px rgba(0,0,0,.06);}
  .nav-links.open{display:flex;}
  .hamburger{display:flex;}
}
@media(max-width:600px){
  .form-grid{grid-template-columns:1fr;}
  .footer-top{grid-template-columns:1fr;}
  .hero-cards{grid-template-columns:1fr 1fr;max-width:300px;margin:0 auto;}
  .products-grid{grid-template-columns:1fr;}
  .compare-dock{bottom:12px;padding:14px;}
  .compare-dock-content{flex-direction:column;align-items:stretch;}
  .compare-dock-actions{width:100%;display:grid;grid-template-columns:1fr 1fr;}
  .compare-link,.compare-clear{justify-content:center;}
}
</style>
</head>
<body>

<!-- NAV -->
<nav id="mainNav">
  <a href="index.php" class="nav-logo">
    <?php if($logoUrl): ?>
      <img src="<?= e($logoUrl) ?>" alt="<?= e($p['site_nom']??'MATELCOM') ?>"/>
    <?php else: ?>
      <span class="nav-logo-text">MATEL<span>COM</span></span>
    <?php endif; ?>
  </a>
  <div class="nav-links" id="navLinks">
    <?php foreach (getMenu() as $item): ?>
      <a href="<?= e(safeUrl($item['url'])) ?>"<?= targetRelAttr($item['target'] ?? '_self') ?>><?= e($item['label']) ?></a>
    <?php endforeach; ?>
    <a href="#contact" class="nav-cta"><i class="fas fa-file-invoice" style="margin-right:4px"></i> Demander un devis</a>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
</nav>

<!-- HERO -->
<section id="hero">
  <div class="hero-grid">
    <div class="hero-content">
      <div class="hero-badge"><i class="fas fa-certificate"></i> <?= e($p['hero_badge']??'Partenaire officiel Microsoft & Kaspersky') ?></div>
      <h1><?= allowInlineMarkup($p['hero_titre']??'Votre partenaire <span>informatique</span> de confiance') ?></h1>
      <p><?= e($p['hero_sous_titre']??'') ?></p>
      <div class="hero-btns">
        <a href="#produits" class="btn-hero"><i class="fas fa-th-large"></i> Voir les produits</a>
        <a href="<?= e(whatsappLink()) ?>" target="_blank" rel="noopener" class="btn-hero-outline"><i class="fab fa-whatsapp"></i> WhatsApp</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="num"><?= e($p['stat_1_valeur']??'24+') ?></div><div class="label"><?= e($p['stat_1_label']??'Années d\'expérience') ?></div></div>
        <div class="hero-stat"><div class="num"><?= e($p['stat_2_valeur']??'1000+') ?></div><div class="label"><?= e($p['stat_2_label']??'Clients satisfaits') ?></div></div>
        <div class="hero-stat"><div class="num"><?= e($p['stat_3_valeur']??'50+') ?></div><div class="label"><?= e($p['stat_3_label']??'Produits disponibles') ?></div></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-cards">
        <div class="hero-card"><i class="fas fa-memory"></i><h3>RAM</h3><p>DDR3 / DDR4 / DDR5</p></div>
        <div class="hero-card"><i class="fas fa-hdd"></i><h3>Stockage</h3><p>SSD & HDD</p></div>
        <div class="hero-card"><i class="fas fa-shield-alt"></i><h3>Kaspersky</h3><p>Antivirus officiel</p></div>
        <div class="hero-card"><i class="fab fa-microsoft"></i><h3>Microsoft</h3><p>Licences officielles</p></div>
      </div>
    </div>
  </div>
</section>

<!-- CATEGORIES STRIP -->
<div id="categories-strip">
  <div class="cats-grid">
    <?php foreach ($cats as $cat): if($cat['slug']==='all') continue; ?>
    <a href="#produits" class="cat-card" onclick="filterProducts('<?= e($cat['slug']) ?>',document.querySelector('.filter-btn[data-cat=<?= e($cat['slug']) ?>]'))">
      <i class="<?= e($cat['icone']??'fas fa-folder') ?>"></i>
      <h3><?= e($cat['nom']) ?></h3>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- PRODUITS -->
<section id="produits">
  <div class="section-header center">
    <div class="section-tag">Catalogue</div>
    <h2 class="section-title">Nos <span>produits</span></h2>
    <p class="section-sub">Découvrez notre gamme complète de matériel informatique, logiciels et solutions de sécurité.</p>
  </div>
  <div class="filter-bar" style="justify-content:center">
    <?php foreach ($cats as $cat): ?>
      <button class="filter-btn <?= $cat['slug']==='all'?'active':'' ?>" data-cat="<?= e($cat['slug']) ?>" onclick="filterProducts('<?= e($cat['slug']) ?>',this)"><?= e($cat['nom']) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="products-grid" id="productsGrid">
    <?php foreach ($produits as $prod):
      $tags  = jdecode($prod['tags']);
      $imgSrc = imgUrl($prod['image']??'');
      $dispo = dispoInfo($prod['disponibilite']??'en_stock');
    ?>
    <div class="product-card" data-cat="<?= e($prod['categories']??'') ?>">
      <a href="produit.php?slug=<?= e($prod['slug']??'') ?>" style="text-decoration:none;color:inherit">
        <div class="product-img">
          <?php if($imgSrc): ?><img src="<?= e($imgSrc) ?>" alt="<?= e($prod['nom']) ?>" loading="lazy"/>
          <?php else: ?><i class="fas fa-box-open" style="font-size:3rem;color:var(--gray-400)"></i><?php endif; ?>
          <?php if ($prod['badge_texte']): ?>
            <span class="product-badge" style="background:<?= e($prod['badge_couleur']??'#D32F2F') ?>"><?= e($prod['badge_texte']) ?></span>
          <?php endif; ?>
          <span class="product-dispo" style="color:<?= $dispo['color'] ?>"><?= $dispo['label'] ?></span>
        </div>
        <div class="product-body">
          <div class="product-marque"><?= e($prod['marque']??'') ?></div>
          <h3><?= e($prod['nom']) ?></h3>
          <div class="model"><?= e($prod['modele']??'') ?></div>
          <p><?= e($prod['description_courte']??'') ?></p>
          <div class="product-tags">
            <?php $i=0; foreach ($tags as $t): if($i++>=4) break; ?><span class="product-tag"><?= e($t) ?></span><?php endforeach; ?>
          </div>
        </div>
      </a>
      <div style="padding:0 20px 20px">
        <div class="product-actions">
          <a href="produit.php?slug=<?= e($prod['slug']??'') ?>" class="product-btn"><i class="fas fa-eye"></i> Détails</a>
          <button type="button" class="product-btn-compare" data-compare-id="<?= (int)$prod['id'] ?>" data-compare-name="<?= e($prod['nom']) ?>" data-compare-model="<?= e($prod['modele'] ?? '') ?>"><i class="fas fa-scale-balanced"></i> Comparer</button>
          <a href="<?= e(whatsappLink($prod['nom'])) ?>" target="_blank" rel="noopener" class="product-btn-wa"><i class="fab fa-whatsapp"></i></a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- AVANTAGES -->
<section id="avantages">
  <div class="section-header center">
    <div class="section-tag">Pourquoi nous choisir</div>
    <h2 class="section-title">Nos <span>avantages</span></h2>
  </div>
  <div class="avantages-grid" style="max-width:1200px;margin:0 auto">
    <div class="avantage-card">
      <div class="avantage-icon"><i class="fas fa-award"></i></div>
      <h3>+24 ans d'expérience</h3>
      <p>Un savoir-faire reconnu dans la distribution de matériel informatique au Maroc depuis 2000.</p>
    </div>
    <div class="avantage-card">
      <div class="avantage-icon"><i class="fas fa-handshake"></i></div>
      <h3>Partenaire officiel</h3>
      <p>Distributeur agréé Microsoft et Kaspersky. Produits 100% authentiques avec garantie constructeur.</p>
    </div>
    <div class="avantage-card">
      <div class="avantage-icon"><i class="fas fa-tags"></i></div>
      <h3>Prix compétitifs</h3>
      <p>Les meilleurs tarifs du marché grâce à nos partenariats directs avec les fabricants.</p>
    </div>
    <div class="avantage-card">
      <div class="avantage-icon"><i class="fas fa-headset"></i></div>
      <h3>Support technique</h3>
      <p>Notre équipe technique vous accompagne dans le choix et l'installation de vos solutions.</p>
    </div>
  </div>
</section>

<!-- À PROPOS -->
<section id="apropos">
  <div class="about-layout">
    <div class="about-visual">
      <div class="about-visual-grid">
        <div class="about-stat-card">
          <div class="big"><?= e($p['about_annees']??'24+') ?></div>
          <div class="small">Années d'expertise dans le secteur informatique</div>
        </div>
        <div class="about-stat-card">
          <div class="big">1000+</div>
          <div class="small">Clients entreprises</div>
        </div>
        <div class="about-stat-card">
          <div class="big">50+</div>
          <div class="small">Références produits</div>
        </div>
        <div class="about-stat-card">
          <div class="big">100%</div>
          <div class="small">Produits authentiques</div>
        </div>
      </div>
    </div>
    <div class="about-content">
      <div class="section-tag">À propos</div>
      <h2 class="section-title"><?= allowInlineMarkup($p['about_titre']??'Plus de 24 ans d\'expertise <span>informatique</span>') ?></h2>
      <p class="section-sub"><?= e($p['about_texte']??'') ?></p>
      <ul class="about-list">
        <li><i class="fas fa-check-circle"></i> Partenaire officiel Microsoft & Kaspersky</li>
        <li><i class="fas fa-check-circle"></i> Produits neufs et authentiques avec garantie</li>
        <li><i class="fas fa-check-circle"></i> Livraison rapide sur tout le Maroc</li>
        <li><i class="fas fa-check-circle"></i> Conseils personnalisés et support technique</li>
        <li><i class="fas fa-check-circle"></i> Solutions adaptées aux entreprises et particuliers</li>
      </ul>
      <div class="partners-row">
        <span style="color:var(--gray-500);font-size:.85rem;font-weight:600;">Nos partenaires :</span>
        <?php foreach($partenaires as $part): ?>
          <span class="partner-badge"><?= e($part['nom']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- TÉMOIGNAGES -->
<?php if ($testis): ?>
<section id="temoignages">
  <div class="section-header center">
    <div class="section-tag">Témoignages</div>
    <h2 class="section-title">Ce que disent nos <span>clients</span></h2>
    <p class="section-sub">Des entreprises à travers tout le Maroc nous font confiance.</p>
  </div>
  <div class="testi-grid" style="max-width:1200px;margin:0 auto">
    <?php foreach ($testis as $t): ?>
    <div class="testi-card">
      <div class="testi-stars"><?= str_repeat('★',(int)$t['note']) ?><?= str_repeat('☆',5-(int)$t['note']) ?></div>
      <p class="testi-text">"<?= e($t['texte']) ?>"</p>
      <div class="testi-author">
        <div class="testi-avatar">
          <?php $av=imgUrl($t['photo']??''); if($av): ?>
            <img src="<?= e($av) ?>" alt="<?= e($t['nom']) ?>"/>
          <?php else: ?>
            <i class="fas fa-user" style="color:var(--red);font-size:1.1rem"></i>
          <?php endif; ?>
        </div>
        <div>
          <div class="testi-name"><?= e($t['nom']) ?></div>
          <div class="testi-role"><?= e($t['role']??'') ?><?= $t['societe']?' · '.e($t['societe']):'' ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- CONTACT -->
<section id="contact">
  <div class="section-header center">
    <div class="section-tag">Contact</div>
    <h2 class="section-title">Demandez votre <span>devis gratuit</span></h2>
    <p class="section-sub">Notre équipe vous répond sous 24h avec une offre personnalisée.</p>
  </div>
  <div class="contact-layout">
    <div class="contact-info">
      <h3>Parlons de votre projet</h3>
      <p>Que vous soyez une entreprise ou un particulier, nous avons la solution adaptée à vos besoins et votre budget.</p>
      <div class="contact-detail">
        <div class="contact-detail-icon"><i class="fas fa-phone"></i></div>
        <div class="contact-detail-text"><strong>Téléphone</strong><span><?= e($p['contact_telephone']??'') ?></span></div>
      </div>
      <div class="contact-detail">
        <div class="contact-detail-icon"><i class="fab fa-whatsapp"></i></div>
        <div class="contact-detail-text"><strong>WhatsApp</strong><span><?= e($p['contact_whatsapp']??'') ?></span></div>
      </div>
      <div class="contact-detail">
        <div class="contact-detail-icon"><i class="fas fa-envelope"></i></div>
        <div class="contact-detail-text"><strong>Email</strong><span><?= e($p['contact_email']??'') ?></span></div>
      </div>
      <div class="contact-detail">
        <div class="contact-detail-icon"><i class="fas fa-map-marker-alt"></i></div>
        <div class="contact-detail-text"><strong>Adresse</strong><span><?= e($p['contact_adresse']??'') ?></span></div>
      </div>
      <a href="<?= e(whatsappLink()) ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;background:#25D366;color:white;padding:12px 24px;border-radius:10px;text-decoration:none;font-weight:600;margin-top:20px;transition:background .2s;"><i class="fab fa-whatsapp"></i> Contacter via WhatsApp</a>
    </div>
    <div class="contact-form">
      <div class="form-card">
        <?php if ($devis_ok): ?>
          <div class="alert-success">✅ Votre demande a été envoyée ! Nous vous contactons sous 24h.</div>
        <?php endif; ?>
        <?php if ($devis_errors): ?>
          <div class="alert-error">⚠️ <?= implode('<br>',array_map('e',$devis_errors)) ?></div>
        <?php endif; ?>
        <?php if (!$devis_ok): ?>
        <form method="POST" action="#contact" enctype="multipart/form-data">
          <input type="hidden" name="envoyer_devis" value="1"/>
          <?= csrf_input('quote_form') ?>
          <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;opacity:0"/>
          <div class="form-grid">
            <div class="form-group"><label>Nom complet *</label><input type="text" name="nom_complet" value="<?= e($_POST['nom_complet']??'') ?>" required placeholder="Votre nom"/></div>
            <div class="form-group"><label>Société (optionnel)</label><input type="text" name="societe" value="<?= e($_POST['societe']??'') ?>" placeholder="Nom de la société"/></div>
            <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= e($_POST['telephone']??'') ?>" placeholder="+212 6XX XXX XXX"/></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= e($_POST['email']??'') ?>" required placeholder="vous@exemple.com"/></div>
            <div class="form-group"><label>Produits demandés</label>
              <select name="produits_demandes">
                <option value="">Sélectionnez un produit</option>
                <?php foreach ($produits as $pr): ?>
                  <option value="<?= e($pr['nom'].' - '.$pr['modele']) ?>" <?= (($_POST['produits_demandes']??'')===$pr['nom'].' - '.$pr['modele'])?'selected':'' ?>><?= e($pr['nom'].' - '.$pr['modele']) ?></option>
                <?php endforeach; ?>
                <option value="Autre">Autre</option>
              </select>
            </div>
            <div class="form-group"><label>Quantité</label><input type="text" name="quantite" value="<?= e($_POST['quantite']??'') ?>" placeholder="Ex: 10 unités"/></div>
            <div class="form-group full"><label>Message</label><textarea name="message" placeholder="Décrivez votre besoin..."><?= e($_POST['message']??'') ?></textarea></div>
            <div class="form-group full"><label>Fichier joint (optionnel)</label><input type="file" name="fichier" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"/></div>
          </div>
          <button type="submit" class="form-submit"><i class="fas fa-paper-plane"></i> Envoyer ma demande</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div class="footer-brand">
      <div class="brand">MATEL<span>COM</span></div>
      <p><?= e($p['footer_texte']??'') ?></p>
    </div>
    <div class="footer-col">
      <h4>Produits</h4>
      <?php foreach (getProduits(null,5) as $pr): ?>
        <a href="produit.php?slug=<?= e($pr['slug']??'') ?>"><?= e($pr['modele']??$pr['nom']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="footer-col">
      <h4>Catégories</h4>
      <?php foreach ($cats as $cat): if($cat['slug']==='all') continue; ?>
        <a href="#produits"><?= e($cat['nom']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <a href="tel:<?= e($p['contact_telephone']??'') ?>"><?= e($p['contact_telephone']??'') ?></a>
      <a href="mailto:<?= e($p['contact_email']??'') ?>"><?= e($p['contact_email']??'') ?></a>
      <a href="#"><?= e($p['contact_adresse']??'') ?></a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> <?= e($p['site_nom']??'MATELCOM') ?>. Tous droits réservés.</p>
    <div class="social-links">
      <a href="<?= e(safeUrl($p['footer_facebook']??'#')) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
      <a href="<?= e(safeUrl($p['footer_instagram']??'#')) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
      <a href="<?= e(safeUrl($p['footer_linkedin']??'#')) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin-in"></i></a>
      <a href="<?= e(safeUrl($p['footer_whatsapp']??'#')) ?>" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i></a>
    </div>
  </div>
</footer>

<!-- WhatsApp Floating -->
<a href="<?= e(whatsappLink()) ?>" target="_blank" rel="noopener" class="wa-float" title="Contactez-nous sur WhatsApp"><i class="fab fa-whatsapp"></i></a>

<div class="compare-dock" id="compareDock" aria-live="polite">
  <div class="compare-dock-head">
    <div>
      <div class="compare-dock-title"><i class="fas fa-scale-balanced"></i> Comparateur de produits</div>
      <div class="compare-dock-sub">Ajoutez plusieurs produits puis ouvrez une comparaison detaillee.</div>
    </div>
  </div>
  <div class="compare-dock-content">
    <div class="compare-pills" id="comparePills"></div>
    <div class="compare-dock-actions">
      <button type="button" class="compare-clear" id="compareClear">Vider</button>
      <a href="compare.php" class="compare-link" id="compareLaunch"><i class="fas fa-table-columns"></i> Comparer <span id="compareCount">0</span></a>
    </div>
  </div>
</div>

<script>
// Hamburger
document.getElementById('hamburger').addEventListener('click',function(){
  document.getElementById('navLinks').classList.toggle('open');
});
// Nav scroll
window.addEventListener('scroll',function(){
  document.getElementById('mainNav').classList.toggle('scrolled',window.scrollY>50);
});
// Filter
function filterProducts(cat,btn){
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  document.querySelectorAll('.product-card').forEach(c=>{
    if(cat==='all'){c.style.display='';}
    else{
      const slugs=(c.dataset.cat||'').split(/\s+/);
      c.style.display=slugs.includes(cat)?'':'none';
    }
  });
}
// Reveal
const obs=new IntersectionObserver(entries=>entries.forEach(e=>{
  if(e.isIntersecting){e.target.style.opacity='1';e.target.style.transform='translateY(0)';}
}),{threshold:.1});
document.querySelectorAll('.product-card,.avantage-card,.testi-card,.cat-card,.about-stat-card').forEach(el=>{
  el.style.opacity='0';el.style.transform='translateY(20px)';
  el.style.transition='opacity .5s ease,transform .5s ease';
  obs.observe(el);
});

const compareStorageKey='matelcom_compare_products';
const compareButtons=[...document.querySelectorAll('[data-compare-id]')];
const compareDock=document.getElementById('compareDock');
const comparePills=document.getElementById('comparePills');
const compareCount=document.getElementById('compareCount');
const compareLaunch=document.getElementById('compareLaunch');
const compareClear=document.getElementById('compareClear');

function readCompareProducts(){
  try{
    const raw=localStorage.getItem(compareStorageKey);
    const parsed=raw?JSON.parse(raw):[];
    return Array.isArray(parsed)?parsed:[];
  }catch(e){return [];}
}

function writeCompareProducts(items){
  localStorage.setItem(compareStorageKey, JSON.stringify(items));
}

function toggleCompareProduct(product){
  const items=readCompareProducts();
  const exists=items.some(item=>item.id===product.id);
  const next=exists?items.filter(item=>item.id!==product.id):[...items, product];
  writeCompareProducts(next);
  renderCompareDock();
}

function removeCompareProduct(id){
  writeCompareProducts(readCompareProducts().filter(item=>item.id!==id));
  renderCompareDock();
}

function renderCompareDock(){
  const items=readCompareProducts();
  compareButtons.forEach(btn=>{
    const active=items.some(item=>item.id===Number(btn.dataset.compareId));
    btn.classList.toggle('active', active);
    btn.innerHTML=active
      ? '<i class="fas fa-check"></i> Ajoute'
      : '<i class="fas fa-scale-balanced"></i> Comparer';
  });

  comparePills.innerHTML='';
  items.forEach(item=>{
    const pill=document.createElement('div');
    pill.className='compare-pill';
    pill.innerHTML='<span>'+item.name+(item.model?' - '+item.model:'')+'</span><button type="button" aria-label="Retirer"><i class="fas fa-times"></i></button>';
    pill.querySelector('button').addEventListener('click', ()=>removeCompareProduct(item.id));
    comparePills.appendChild(pill);
  });

  compareCount.textContent=String(items.length);
  compareLaunch.href='compare.php?ids='+items.map(item=>item.id).join(',');
  compareLaunch.style.pointerEvents=items.length ? 'auto' : 'none';
  compareLaunch.style.opacity=items.length ? '1' : '.55';
  compareDock.classList.toggle('open', items.length > 0);
}

compareButtons.forEach(btn=>{
  btn.addEventListener('click', function(){
    toggleCompareProduct({
      id:Number(btn.dataset.compareId),
      name:btn.dataset.compareName || '',
      model:btn.dataset.compareModel || ''
    });
  });
});

compareClear.addEventListener('click', function(){
  writeCompareProducts([]);
  renderCompareDock();
});

renderCompareDock();
</script>
</body>
</html>
