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

$logged_in_role = $_SESSION['spin_admin_role'] ?? '';

if ($logged_in_role !== 'admin') {
    die("Access denied.");
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Delete registrations first
    $sql1 = "DELETE FROM CIY_spin_registrations";

    if (!$conn->query($sql1)) {
        die("Failed deleting registrations: " . $conn->error);
    }

    // Delete spins
    $sql2 = "DELETE FROM CIY_spins";

    if (!$conn->query($sql2)) {
        die("Failed deleting spins: " . $conn->error);
    }

    // Reset auto increment
    $conn->query("ALTER TABLE CIY_spins AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE CIY_spin_registrations AUTO_INCREMENT = 1");

    $message = "All spins and registrations deleted successfully.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Delete All Spins</title>

<link rel="stylesheet" href="../assets/css/style.css?v=1">

<style>

.page{
    max-width:700px;
    margin:0 auto;
    padding:20px 20px 40px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:28px;
    border-top:5px solid #dc2626;
    box-shadow:0 8px 24px rgba(0,0,0,.08);
}

.warning{
    background:#fee2e2;
    color:#991b1b;
    padding:16px;
    border-radius:12px;
    font-weight:700;
    margin-bottom:20px;
}

.success{
    background:#dcfce7;
    color:#166534;
    padding:16px;
    border-radius:12px;
    font-weight:700;
    margin-bottom:20px;
}

.back-link{
    display:inline-block;
    color:#00CFA9;
    font-weight:700;
    text-decoration:none;
    margin-bottom:20px;
}

</style>

</head>
<body>

<div class="page">

    <a 
        href="index.php"
        class="back-link"
    >
        ← Back to Admin
    </a>

    <div class="card">

        <h1>Delete All Spins</h1>

        <?php if ($message): ?>

            <div class="success">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <div class="warning">
            WARNING: This will permanently delete ALL spins and ALL registrations.
        </div>

        <form method="POST">

            <button 
                type="submit"
                class="btn danger"
            >
                Delete Everything
            </button>

        </form>

    </div>

</div>

</body>
</html>