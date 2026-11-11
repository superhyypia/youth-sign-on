<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */


$session_lifetime = 60 * 60 * 24 * 14;

// Check if the current request is served over HTTPS
$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    // 'secure' => true,
    'secure' => $is_secure, // Dynamic: true on production HTTPS, false on local HTTP
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    empty($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: login.php");
    exit;
}
?>