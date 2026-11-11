<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/includes/db_loader.php';

$conn = connect_to_database();
$conn->set_charset("utf8mb4");

$spin_id = isset($_GET['spin_id']) ? (int)$_GET['spin_id'] : (isset($_POST['spin_id']) ? (int)$_POST['spin_id'] : 0);
$message = "";
$message_type = "";

if ($spin_id <= 0) {
    die("Invalid spin.");
}

$stmt = $conn->prepare("
    SELECT spin_id, title, spin_date, start_time, leader_name, status
    FROM CIY_spins
    WHERE spin_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $spin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Spin not found.");
}

$spin = $result->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if ($email === "") {

        $message = "Please enter your email address.";
        $message_type = "error";

    } else {

        $check_stmt = $conn->prepare("
            SELECT registration_id
            FROM CIY_spin_registrations
            WHERE spin_id = ?
              AND email = ?
            LIMIT 1
        ");

        if (!$check_stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $check_stmt->bind_param("is", $spin_id, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows === 0) {

            $message = "No matching registration was found for that email address.";
            $message_type = "error";
            $check_stmt->close();

        } else {

            $row = $check_result->fetch_assoc();
            $registration_id = (int)$row["registration_id"];
            $check_stmt->close();

            $delete_stmt = $conn->prepare("
                DELETE FROM CIY_spin_registrations
                WHERE registration_id = ?
                LIMIT 1
            ");

            if (!$delete_stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $delete_stmt->bind_param("i", $registration_id);

            if ($delete_stmt->execute()) {
                $message = "Your registration has been cancelled.";
                $message_type = "success";
            } else {
                $message = "Cancellation failed: " . $delete_stmt->error;
                $message_type = "error";
            }

            $delete_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cancel Registration</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="assets/css/style.css?v=7">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:680px;
    margin:0 auto;
    padding:20px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    margin-bottom:18px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
}

.logo{
    width:90px;
    height:auto;
    margin-bottom:14px;
}

.nav{
    margin-bottom:18px;
}

.nav a{
    color:#00CFA9;
    font-weight:700;
    text-decoration:none;
}

label{
    display:block;
    font-weight:700;
    margin-top:16px;
    margin-bottom:6px;
}

input{
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #cbd5e1;
    font-size:16px;
    box-sizing:border-box;
}

.message{
    padding:14px;
    border-radius:12px;
    font-weight:700;
    margin-bottom:18px;
}

.message.success{
    background:#dcfce7;
    color:#166534;
}

.message.error{
    background:#fee2e2;
    color:#991b1b;
}

.spin-title{
    margin-top:0;
    color:#164B38;
}

.spin-meta{
    margin:8px 0;
    font-size:15px;
}

.warning{
    background:#fff7ed;
    color:#9a3412;
    padding:14px;
    border-radius:12px;
    margin-top:14px;
    font-weight:700;
}
</style>
</head>

<body>

<header class="app-header">
    <img src="assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Cancel Registration</h1>
    <p>ClubSpin Register</p>
</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Upcoming Spins</a>
    </div>

    <div class="card">

        <h2 class="spin-title">
            <?php echo htmlspecialchars($spin['title']); ?>
        </h2>

        <div class="spin-meta">
            <strong>Date:</strong>
            <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Time:</strong>
            <?php echo date("H:i", strtotime($spin['start_time'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Leader:</strong>
            <?php echo htmlspecialchars($spin['leader_name']); ?>
        </div>

        <div class="warning">
            Enter the same email address you used to register.
        </div>

    </div>

    <div class="card">

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== "success"): ?>

            <form method="post">

                <input type="hidden" name="spin_id" value="<?php echo (int)$spin_id; ?>">

                <label>Email Address</label>
                <input type="email" name="email" required>

                <button type="submit" class="btn primary">
                    Cancel My Registration
                </button>

            </form>

        <?php else: ?>

            <a class="btn primary" href="index.php">
                Back to Upcoming Spins
            </a>

        <?php endif; ?>

    </div>

</div>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js");
}
</script>

</body>
</html>