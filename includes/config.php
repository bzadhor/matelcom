<?php
// ============================================================
// MATELCOM — Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'matelcom');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_URL', 'http://localhost/matelcom');
define('ADMIN_URL', SITE_URL . '/admin');

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('VAR_DIR', dirname(__DIR__) . '/var/');

require_once __DIR__.'/security.php';

ensureDirectory(UPLOAD_DIR);
ensureDirectory(VAR_DIR);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;background:#fff;color:#c00">
                <h2>Erreur de connexion MySQL</h2>
                <p>Vérifiez <code>includes/config.php</code></p>
                <pre style="background:#f5f5f5;padding:12px;border-radius:6px">'
                . htmlspecialchars($e->getMessage()) .
                '</pre>
            </div>');
        }
    }
    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $cookiePath = rtrim((string) parse_url(SITE_URL, PHP_URL_PATH), '/');
    $cookiePath = $cookiePath === '' ? '/' : $cookiePath . '/';

    session_name('MATELCOMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
