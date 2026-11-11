<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

include_once 'auth_check.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/../includes/db_loader.php';

$conn = connect_to_database();
$conn->set_charset("utf8mb4");

$current_user_id = $_SESSION['user_id'] ?? $_SESSION['spin_admin_user_id'] ?? 0;
$current_name = $_SESSION['user_name'] ?? $_SESSION['spin_admin_name'] ?? 'User';

if ($current_user_id <= 0) {
    die("Error: No logged-in user found.");
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password = trim($_POST["current_password"] ?? "");
    $new_password = trim($_POST["new_password"] ?? "");
    $confirm_password = trim($_POST["confirm_password"] ?? "");

    if ($current_password === "" || $new_password === "" || $confirm_password === "") {

        $message = "Please complete all password fields.";
        $message_type = "error";

    } elseif ($new_password !== $confirm_password) {

        $message = "New password and confirmation do not match.";
        $message_type = "error";

    } elseif (strlen($new_password) < 8) {

        $message = "New password must be at least 8 characters.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            SELECT password_hash
            FROM CIY_spin_users
            WHERE user_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $message = "User account not found.";
            $message_type = "error";

        } else {

            $user = $result->fetch_assoc();

            if (!password_verify($current_password, $user['password_hash'])) {

                $message = "Current password is incorrect.";
                $message_type = "error";

            } else {

                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $update_stmt = $conn->prepare("
                    UPDATE CIY_spin_users
                    SET password_hash = ?
                    WHERE user_id = ?
                    LIMIT 1
                ");

                if (!$update_stmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $update_stmt->bind_param(
                    "si",
                    $new_hash,
                    $current_user_id
                );

                if ($update_stmt->execute()) {
                    $message = "Password updated successfully.";
                    $message_type = "success";
                } else {
                    $message = "Password update failed: " . htmlspecialchars($update_stmt->error);
                    $message_type = "error";
                }

                $update_stmt->close();
            }
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=82">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:620px;
    margin:0 auto;
    padding:20px 16px 40px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
}

.nav{
    margin-bottom:18px;
}

.nav a{
    text-decoration:none;
    color:#00CFA9;
    font-weight:800;
}

.logo{
    width:90px;
    height:auto;
    margin-bottom:14px;
}

label{
    display:block;
    margin-top:16px;
    margin-bottom:6px;
    font-weight:800;
    color:#111827;
}

input{
    width:100%;
    padding:13px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-size:16px;
    background:#fff;
    box-sizing:border-box;
}

button{
    margin-top:24px;
    width:100%;
    padding:15px;
    border:none;
    border-radius:999px;
    background:#00CFA9;
    color:#fff;
    font-size:16px;
    font-weight:800;
    cursor:pointer;
}

button:hover{
    background:#164B38;
}

.message{
    margin-bottom:18px;
    padding:14px;
    border-radius:12px;
    font-weight:800;
}

.message.success{
    background:#dcfce7;
    color:#166534;
    border:1px solid #86efac;
}

.message.error{
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
}

.help{
    margin-top:10px;
    padding:12px;
    border-radius:12px;
    background:#f8fafc;
    color:#475569;
    border:1px solid #e2e8f0;
    font-size:14px;
    line-height:1.5;
}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Change Password</h1>
    <p>ClubSpin Register Admin</p>
</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <div class="card">

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>
            <?php echo htmlspecialchars($current_name); ?>
        </h2>

        <form method="POST">

            <label>Current Password</label>
            <input 
                type="password" 
                name="current_password" 
                autocomplete="current-password"
                required
            >

            <label>New Password</label>
            <input 
                type="password" 
                name="new_password" 
                autocomplete="new-password"
                required
            >

            <label>Confirm New Password</label>
            <input 
                type="password" 
                name="confirm_password" 
                autocomplete="new-password"
                required
            >

            <div class="help">
                Password must be at least 8 characters. After changing it, use the new password the next time you log in.
            </div>

            <button type="submit">
                Update Password
            </button>

        </form>

    </div>

</div>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js");
}
</script>

</body>
</html>