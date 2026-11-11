<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */


$session_lifetime = 60 * 60 * 24 * 14;

$is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'secure' => $is_secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

$_SESSION = [];

session_destroy();

setcookie(session_name(), '', time() - 3600, '/', '', $is_secure, true);

header("Location: login.php");
exit;
?>