<?php

function isHttpsRequest() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return (($_SERVER['SERVER_PORT'] ?? null) === '443');
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ensureDirectory($dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function securityStorageDir() {
    $dir = defined('VAR_DIR') ? VAR_DIR . 'security/' : dirname(__DIR__) . '/var/security/';
    ensureDirectory($dir);
    return $dir;
}

function rateLimitFile($bucket) {
    return securityStorageDir() . sha1($bucket) . '.json';
}

function readRateLimitBucket($bucket, $window) {
    $file = rateLimitFile($bucket);
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    $data = json_decode($raw ?: '[]', true);
    if (!is_array($data)) {
        $data = [];
    }

    $now = time();
    return array_values(array_filter($data, static function ($ts) use ($now, $window) {
        return is_int($ts) && ($now - $ts) < $window;
    }));
}

function writeRateLimitBucket($bucket, array $timestamps) {
    @file_put_contents(rateLimitFile($bucket), json_encode(array_values($timestamps), JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function rateLimitReached($bucket, $maxAttempts, $window) {
    return count(readRateLimitBucket($bucket, $window)) >= $maxAttempts;
}

function rateLimitBump($bucket, $window) {
    $timestamps = readRateLimitBucket($bucket, $window);
    $timestamps[] = time();
    writeRateLimitBucket($bucket, $timestamps);
}

function clearRateLimit($bucket) {
    $file = rateLimitFile($bucket);
    if (is_file($file)) {
        @unlink($file);
    }
}

function rateLimitRetryAfter($bucket, $window) {
    $timestamps = readRateLimitBucket($bucket, $window);
    if (!$timestamps) {
        return 0;
    }
    return max(0, $window - (time() - min($timestamps)));
}

function csrfStore() {
    if (!isset($_SESSION['_csrf']) || !is_array($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = [];
    }
    return $_SESSION['_csrf'];
}

function csrf_token($form = 'default') {
    $store = csrfStore();
    $entry = $store[$form] ?? null;
    if (!is_array($entry) || empty($entry['token']) || (time() - (int) ($entry['time'] ?? 0)) > 7200) {
        $entry = [
            'token' => bin2hex(random_bytes(32)),
            'time' => time(),
        ];
        $_SESSION['_csrf'][$form] = $entry;
    }
    return $entry['token'];
}

function csrf_input($form = 'default') {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token($form), ENT_QUOTES, 'UTF-8') . '"/>';
}

function verify_csrf_or_fail($form = 'default') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $expected = $_SESSION['_csrf'][$form]['token'] ?? '';
    $provided = (string) ($_POST['_csrf'] ?? '');
    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        http_response_code(419);
        exit('Token de securite invalide.');
    }
}

function safeUrl($url, $fallback = '#') {
    $url = trim((string) $url);
    if ($url === '') {
        return $fallback;
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return $fallback;
    }
    if (preg_match('/^(?:javascript|data|vbscript):/i', $url)) {
        return $fallback;
    }
    if ($url[0] === '#') {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        return $fallback;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === '') {
        return $url;
    }

    $scheme = strtolower($scheme);
    if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
        return $url;
    }

    return $fallback;
}

function safeTarget($target) {
    return strtolower((string) $target) === '_blank' ? '_blank' : '_self';
}

function targetRelAttr($target) {
    return safeTarget($target) === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
}

function sanitizeRedirectUrl($url, $fallback = null) {
    $fallback = $fallback ?: (defined('SITE_URL') ? SITE_URL . '/index.php' : '/');
    $url = trim((string) $url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return $fallback;
    }

    if ($url[0] === '/' || $url[0] === '?' || $url[0] === '#') {
        return $url;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null || $scheme === '') {
        return $url;
    }

    $scheme = strtolower($scheme);
    if (!in_array($scheme, ['http', 'https'], true) || !defined('SITE_URL')) {
        return $fallback;
    }

    $targetHost = strtolower((string) parse_url($url, PHP_URL_HOST));
    $siteHost = strtolower((string) parse_url(SITE_URL, PHP_URL_HOST));
    return ($targetHost && $targetHost === $siteHost) ? $url : $fallback;
}

function redirect($url, $fallback = null) {
    header('Location: ' . sanitizeRedirectUrl($url, $fallback));
    exit;
}

function normalizeRelativeUploadPath($path) {
    $path = trim(str_replace('\\', '/', (string) $path), '/');
    if ($path === '' || strpos($path, '..') !== false || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return '';
    }
    return preg_replace('~[^A-Za-z0-9/_\.-]~', '', $path);
}

function deleteUploadFile($path) {
    $relative = normalizeRelativeUploadPath($path);
    if ($relative === '') {
        return false;
    }
    $full = UPLOAD_DIR . $relative;
    return is_file($full) ? @unlink($full) : false;
}

function allowInlineMarkup($html) {
    $clean = strip_tags((string) $html, '<span><strong><em><br>');
    return preg_replace('/<(\/?)(span|strong|em|br)\b[^>]*>/i', '<$1$2>', $clean);
}

function limitString($value, $maxLength) {
    $value = trim((string) $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function normalizeSlug($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string) $value, '-');
}
