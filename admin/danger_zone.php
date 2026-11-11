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

$user_role = $_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '';

if ($user_role !== 'admin') {
    die("Access denied. Admin users only.");
}

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $confirm_text = trim($_POST["confirm_text"] ?? "");

    if ($action === "delete_registrations") {
        if ($confirm_text !== "DELETE REGISTRATIONS") {
            $message = "Confirmation text did not match.";
            $message_type = "error";
        } else {
            if ($conn->query("DELETE FROM CIY_spin_registrations")) {
                $message = "All registrations have been deleted.";
                $message_type = "success";
            } else {
                $message = "Error deleting registrations: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    if ($action === "delete_everything") {
        if ($confirm_text !== "DELETE EVERYTHING") {
            $message = "Confirmation text did not match.";
            $message_type = "error";
        } else {
            $conn->query("DELETE FROM CIY_spin_registrations");

            if ($conn->query("DELETE FROM CIY_spins")) {
                $message = "All spins and registrations have been deleted.";
                $message_type = "success";
            } else {
                $message = "Error deleting spins: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Danger Zone</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=20">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{max-width:760px;margin:0 auto;padding:20px 16px 40px;}
.card{background:#fff;border-radius:18px;padding:22px;margin-bottom:18px;border-top:5px solid #991b1b;box-shadow:0 8px 24px rgba(63,24,83,.10);}
.nav{margin-bottom:18px;}
.nav a{text-decoration:none;color:#00CFA9;font-weight:800;}
.logo{width:90px;height:auto;margin-bottom:14px;}
.warning{background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;font-weight:800;margin-bottom:18px;}
.message{padding:14px;border-radius:12px;font-weight:800;margin-bottom:18px;}
.message.success{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.message.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
label{display:block;margin-top:14px;margin-bottom:6px;font-weight:800;}
input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font-size:16px;}
.danger-btn{width:100%;margin-top:16px;padding:15px;border:none;border-radius:999px;background:#dc2626;color:#fff;font-size:16px;font-weight:800;cursor:pointer;}
.danger-btn:hover{background:#991b1b;}
.secondary-danger{background:#f97316;}
.secondary-danger:hover{background:#c2410c;}
.help{font-size:14px;color:#64748b;line-height:1.5;margin-top:8px;}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Danger Zone</h1>
    <p>Admin-only reset tools</p>
</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="warning">
        These actions cannot be undone. Use only for testing or system reset.
    </div>

    <div class="card">
        <h2>Delete All Registrations</h2>

        <p class="help">
            This removes all rider registrations but keeps all spins.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="delete_registrations">

            <label>Type DELETE REGISTRATIONS to confirm</label>
            <input type="text" name="confirm_text" required>

            <button 
                type="submit" 
                class="danger-btn secondary-danger"
                onclick="return confirm('Delete ALL rider registrations? This cannot be undone.');"
            >
                Delete All Registrations
            </button>
        </form>
    </div>

    <div class="card">
        <h2>Delete Everything</h2>

        <p class="help">
            This removes all spins and all rider registrations.
        </p>

        <form method="post">
            <input type="hidden" name="action" value="delete_everything">

            <label>Type DELETE EVERYTHING to confirm</label>
            <input type="text" name="confirm_text" required>

            <button 
                type="submit" 
                class="danger-btn"
                onclick="return confirm('DELETE EVERYTHING? This will remove all spins and registrations.');"
            >
                Delete Everything
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