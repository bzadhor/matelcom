<?php
require_once __DIR__.'/functions.php';

function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) redirect(ADMIN_URL.'/login.php');
    if (!validateAdminSession()) logoutAdmin();
}

function isLogged() { return isset($_SESSION['admin_id']) && validateAdminSession(); }

function loginAdmin($username, $password) {
    $s = getDB()->prepare("SELECT * FROM admins WHERE username=? AND is_active=1");
    $s->execute([$username]); $a = $s->fetch();
    if ($a && password_verify($password, $a['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $a['id'];
        $_SESSION['admin_user'] = $a['username'];
        $_SESSION['admin_role_id'] = $a['role_id'];
        $_SESSION['admin_is_super'] = $a['is_super_admin'];
        $_SESSION['admin_ua']   = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['admin_last_activity'] = time();
        $_SESSION['admin_last_regen'] = time();
        return true;
    }
    return false;
}

function validateAdminSession() {
    $ua = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (empty($_SESSION['admin_ua']) || !hash_equals($_SESSION['admin_ua'], $ua)) return false;
    $now = time();
    if (($now - (int) ($_SESSION['admin_last_activity'] ?? 0)) > 1800) return false;
    if (($now - (int) ($_SESSION['admin_last_regen'] ?? 0)) > 300) {
        session_regenerate_id(true);
        $_SESSION['admin_last_regen'] = $now;
    }
    $_SESSION['admin_last_activity'] = $now;
    return true;
}

function logoutAdmin() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
    redirect(ADMIN_URL.'/login.php');
}

function gh_is_super_admin() {
    return !empty($_SESSION['admin_is_super']);
}

function gh_role_label() {
    if (gh_is_super_admin()) return 'Super Admin';
    $rid = $_SESSION['admin_role_id'] ?? null;
    if (!$rid) return 'Admin';
    $s = getDB()->prepare("SELECT name FROM admin_roles WHERE id=?");
    $s->execute([$rid]);
    $name = $s->fetchColumn();
    return $name ?: 'Admin';
}

function gh_has_page_permission($page_key, $type = 'read') {
    if (gh_is_super_admin()) return true;
    $rid = $_SESSION['admin_role_id'] ?? null;
    if (!$rid) return false;
    $col = $type === 'write' ? 'can_write' : 'can_read';
    $s = getDB()->prepare("SELECT $col FROM admin_role_permissions WHERE role_id=? AND (page_key=? OR page_key='all')");
    $s->execute([$rid, $page_key]);
    return (bool) $s->fetchColumn();
}

function gh_require_permission($page_key, $type = 'read') {
    if (!gh_has_page_permission($page_key, $type)) {
        http_response_code(403);
        exit('<div style="font-family:sans-serif;padding:60px;text-align:center"><h2>Accès refusé</h2><p>Vous n\'avez pas les permissions nécessaires.</p><a href="index.php">Retour</a></div>');
    }
}
