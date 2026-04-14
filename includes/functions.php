<?php
// ── Paramètre site ──────────────────────────────────────────
function param($cle, $defaut = '') {
    static $cache = [];
    if (!array_key_exists($cle, $cache)) {
        try {
            $s = getDB()->prepare("SELECT valeur FROM parametres WHERE cle=?");
            $s->execute([$cle]);
            $r = $s->fetchColumn();
            $cache[$cle] = ($r !== false) ? $r : $defaut;
        } catch(Exception $e) { $cache[$cle] = $defaut; }
    }
    return $cache[$cle];
}

function allParams() {
    $rows = getDB()->query("SELECT cle,valeur FROM parametres")->fetchAll();
    $out = [];
    foreach($rows as $r) $out[$r['cle']] = $r['valeur'];
    return $out;
}

// ── Produits ────────────────────────────────────────────────
function getProduits($cat = null, $limit = null, $vedette = false) {
    $sql = "SELECT * FROM produits WHERE statut=1";
    $p = [];
    if ($cat && $cat !== 'all') { $sql .= " AND FIND_IN_SET(?,REPLACE(categories,' ',','))"; $p[] = $cat; }
    if ($vedette) $sql .= " AND en_vedette=1";
    $sql .= " ORDER BY ordre ASC";
    if ($limit) $sql .= " LIMIT ".(int)$limit;
    $s = getDB()->prepare($sql); $s->execute($p);
    return $s->fetchAll();
}

function getProduit($id) {
    $s = getDB()->prepare("SELECT * FROM produits WHERE id=? AND statut=1");
    $s->execute([(int)$id]); return $s->fetch();
}

function getProduitBySlug($slug) {
    $s = getDB()->prepare("SELECT * FROM produits WHERE slug=? AND statut=1");
    $s->execute([$slug]); return $s->fetch();
}

function getProduitsByIds(array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $s = getDB()->prepare("SELECT * FROM produits WHERE statut=1 AND id IN ($placeholders)");
    $s->execute($ids);
    $rows = $s->fetchAll();
    $byId = [];
    foreach ($rows as $row) $byId[(int)$row['id']] = $row;
    $ordered = [];
    foreach ($ids as $id) if (isset($byId[$id])) $ordered[] = $byId[$id];
    return $ordered;
}

function getCategories() {
    return getDB()->query("SELECT * FROM categories ORDER BY ordre ASC")->fetchAll();
}

// ── Témoignages ─────────────────────────────────────────────
function getTemoignages() {
    return getDB()->query("SELECT * FROM temoignages WHERE statut=1 ORDER BY ordre ASC")->fetchAll();
}

// ── Partenaires ─────────────────────────────────────────────
function getPartenaires() {
    return getDB()->query("SELECT * FROM partenaires WHERE actif=1 ORDER BY ordre ASC")->fetchAll();
}

// ── Menu ────────────────────────────────────────────────────
function getMenu() {
    return getDB()->query("SELECT * FROM menu_items WHERE actif=1 AND parent_id IS NULL ORDER BY ordre ASC")->fetchAll();
}

// ── Helpers ─────────────────────────────────────────────────
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function jdecode($json, $defaut = []) {
    $r = json_decode($json ?? '[]', true);
    return is_array($r) ? $r : $defaut;
}

function uploadImage($file, $sous_dossier = 'products') {
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error'=>'Upload invalide.'];
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 5*1024*1024) return ['error'=>'Fichier trop lourd (max 5 Mo)'];
    if (!is_uploaded_file($file['tmp_name'] ?? '')) return ['error'=>'Fichier source invalide.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext  = $allowed[$mime] ?? null;
    if (!$ext || !@getimagesize($file['tmp_name'])) return ['error'=>'Format image non autorise.'];
    $folder = normalizeRelativeUploadPath($sous_dossier);
    if ($folder === '') return ['error'=>'Dossier d\'upload invalide.'];
    $dir = UPLOAD_DIR.$folder.'/';
    ensureDirectory($dir);
    $nom = bin2hex(random_bytes(16)).'.'.$ext;
    if (move_uploaded_file($file['tmp_name'], $dir.$nom))
        return ['success'=>true, 'filename'=>$folder.'/'.$nom];
    return ['error'=>'Erreur lors de l\'upload'];
}

function uploadFile($file, $sous_dossier = 'devis_fichiers') {
    $allowed_mimes = ['application/pdf','image/jpeg','image/png','application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error'=>'Upload invalide.'];
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 10*1024*1024) return ['error'=>'Fichier trop lourd (max 10 Mo)'];
    if (!is_uploaded_file($file['tmp_name'] ?? '')) return ['error'=>'Fichier source invalide.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $allowed_mimes)) return ['error'=>'Type de fichier non autorisé.'];
    $folder = normalizeRelativeUploadPath($sous_dossier);
    if ($folder === '') return ['error'=>'Dossier d\'upload invalide.'];
    $dir = UPLOAD_DIR.$folder.'/';
    ensureDirectory($dir);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $nom = bin2hex(random_bytes(16)).'.'.$ext;
    if (move_uploaded_file($file['tmp_name'], $dir.$nom))
        return ['success'=>true, 'filename'=>$folder.'/'.$nom];
    return ['error'=>'Erreur lors de l\'upload'];
}

function imgUrl($path, $fallback = '') {
    $path = normalizeRelativeUploadPath($path);
    if ($path && file_exists(UPLOAD_DIR.$path)) return UPLOAD_URL.$path;
    return safeUrl($fallback, '');
}

function setFlash($type, $msg) { $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }

function getFlash() {
    if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function statutDevisInfo($s) {
    return [
        'nouveau'  => ['label'=>'Nouveau',   'class'=>'badge-danger'],
        'en_cours' => ['label'=>'En cours',  'class'=>'badge-warning'],
        'traite'   => ['label'=>'Traité',    'class'=>'badge-success'],
        'archive'  => ['label'=>'Archivé',   'class'=>'badge-secondary'],
    ][$s] ?? ['label'=>$s, 'class'=>'badge-secondary'];
}

function dispoInfo($d) {
    return [
        'en_stock'     => ['label'=>'En stock',      'class'=>'badge-success', 'color'=>'#059669'],
        'sur_commande' => ['label'=>'Sur commande',  'class'=>'badge-warning', 'color'=>'#d97706'],
        'rupture'      => ['label'=>'Rupture',       'class'=>'badge-danger',  'color'=>'#dc2626'],
    ][$d] ?? ['label'=>$d, 'class'=>'badge-secondary', 'color'=>'#64748b'];
}

function whatsappLink($produit = '') {
    $p = allParams();
    $num = preg_replace('/[^0-9]/', '', $p['whatsapp_numero'] ?? '');
    $msg = ($p['whatsapp_message'] ?? 'Bonjour, je souhaite un devis pour') . ' ' . $produit;
    return 'https://wa.me/' . $num . '?text=' . urlencode($msg);
}
