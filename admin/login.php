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

session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/../includes/db_loader.php';

$conn = connect_to_database();
$conn->set_charset("utf8mb4");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? "");
    $password = trim($_POST['password'] ?? "");

    $sql = "
        SELECT 
            user_id,
            name,
            email,
            password_hash,
            role
        FROM CIY_spin_users
        WHERE email = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            // Run retention housekeeping after a successful login.
            // A ride dated 11 October becomes purge-eligible on 16 October.
            include_once __DIR__ . '/../includes/data_retention.php';
            purge_expired_registration_data($conn);

            header("Location: index.php");
            exit;

        } else {
            $error = "Invalid email or password.";
        }

    } else {
        $error = "Invalid email or password.";
    }

    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ClubSpin Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=16">
<link rel="manifest" href="/clubspins/manifest.json">

<meta name="theme-color" content="#00CFA9">

<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>

body{
    margin:0;
    background:#f4f5f7;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    color:#1f2937;
}

.login-wrapper{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}

.login-card{
    width:100%;
    max-width:420px;
    background:#fff;
    border-radius:22px;
    padding:30px 26px;
    box-shadow:0 12px 30px rgba(63,24,83,.12);
    border-top:6px solid #00CFA9;
}

.logo{
    width:92px;
    display:block;
    margin:0 auto 18px;
}

h1{
    margin:0;
    text-align:center;
    font-size:30px;
    color:#111827;
}

.subtitle{
    margin-top:8px;
    text-align:center;
    color:#64748b;
    font-size:15px;
}

.error{
    margin-top:18px;
    background:#fee2e2;
    color:#991b1b;
    padding:14px;
    border-radius:12px;
    font-weight:700;
    border:1px solid #fecaca;
}

label{
    display:block;
    margin-top:18px;
    margin-bottom:6px;
    font-weight:800;
    color:#111827;
}

input{
    width:100%;
    padding:14px;
    border:1px solid #cbd5e1;
    border-radius:12px;
    font-size:16px;
    background:#fff;
}

input:focus{
    outline:none;
    border-color:#00CFA9;
    box-shadow:0 0 0 3px rgba(106,44,130,.12);
}

button{
    width:100%;
    margin-top:24px;
    padding:15px;
    border:none;
    border-radius:999px;
    background:#00CFA9;
    color:#fff;
    font-size:16px;
    font-weight:800;
    cursor:pointer;
    transition:all .2s ease;
}

button:hover{
    background:#164B38;
}

.footer-note{
    margin-top:20px;
    text-align:center;
    font-size:13px;
    color:#94a3b8;
}

</style>
</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <img 
            src="../assets/images/ci-youth.png?v=9"
            alt="Participating cycling club logos"
            class="logo"
        >

        <h1>ClubSpin Admin</h1>

        <div class="subtitle">
            Sign in to manage club spins
        </div>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <label>Email Address</label>

            <input 
                type="email"
                name="email"
                required
                autocomplete="email"
            >

            <label>Password</label>

            <input 
                type="password"
                name="password"
                required
                autocomplete="current-password"
            >

            <button type="submit">
                Sign In
            </button>

        </form>

        <div class="footer-note">
            ClubSpin Register
        </div>

    </div>

</div>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js");
}
</script>

</body>
</html>