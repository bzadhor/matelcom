<?php
function trackVisite() {
    try {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $appareil = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone/i', $ua)) $appareil = 'Mobile';
        elseif (preg_match('/Tablet|iPad/i', $ua)) $appareil = 'Tablette';

        $navigateur = 'Autre';
        if (preg_match('/Chrome/i', $ua)) $navigateur = 'Chrome';
        elseif (preg_match('/Firefox/i', $ua)) $navigateur = 'Firefox';
        elseif (preg_match('/Safari/i', $ua)) $navigateur = 'Safari';
        elseif (preg_match('/Edge/i', $ua)) $navigateur = 'Edge';

        $page = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        getDB()->prepare(
            "INSERT INTO visites (page, ip, appareil, navigateur, referer) VALUES (?,?,?,?,?)"
        )->execute([$page, $ip, $appareil, $navigateur, $referer]);
    } catch (Exception $e) { /* silently fail */ }
}
