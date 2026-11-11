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
$current_role = $_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '';
$is_admin = ($current_role === 'admin');

$spin_id = isset($_GET['spin_id']) ? (int)$_GET['spin_id'] : 0;

if ($spin_id <= 0) {
    die("Invalid spin ID.");
}

/*
|--------------------------------------------------------------------------
| Load Spin
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        spin_id,
        title,
        spin_date,
        start_time,
        meeting_point,
        maps_url,
        leader_name,
        status,
        leader_user_id
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

/*
|--------------------------------------------------------------------------
| Ownership Rule
|--------------------------------------------------------------------------
| Admins can view QR codes for all spins.
| Leaders can only view QR codes for their own spins.
|--------------------------------------------------------------------------
*/

if (!$is_admin && (int)$spin['leader_user_id'] !== (int)$current_user_id) {
    die("Access denied. You can only view QR codes for your own spins.");
}

$conn->close();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$registration_url = $protocol . $_SERVER['HTTP_HOST'] . "/clubspins/register.php?spin_id=" . (int)$spin['spin_id'];

$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=" . rawurlencode($registration_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Spin QR Code</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=100">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:720px;
    margin:0 auto;
    padding:20px 16px 40px;
}

.nav{
    margin-bottom:18px;
}

.nav a{
    text-decoration:none;
    color:#00CFA9;
    font-weight:800;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:24px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
    text-align:center;
}

.logo{
    width:90px;
    height:auto;
    margin-bottom:14px;
}

.spin-title{
    margin:0 0 10px;
    color:#164B38;
    font-size:28px;
}

.meta{
    margin:8px 0;
    color:#334155;
    font-size:16px;
    line-height:1.5;
}

.qr-box{
    margin:24px auto;
    padding:18px;
    background:#ffffff;
    border:1px solid #e2e8f0;
    border-radius:18px;
    display:inline-block;
}

.qr-box img{
    width:100%;
    max-width:360px;
    height:auto;
    display:block;
}

.notice{
    margin-top:18px;
    padding:14px;
    border-radius:12px;
    background:#E8F8F3;
    color:#1e3a8a;
    border:1px solid #B7E8DB;
    font-weight:700;
    line-height:1.5;
    text-align:left;
}

.url-box{
    margin-top:18px;
    padding:12px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#475569;
    font-size:13px;
    line-height:1.5;
    word-break:break-all;
    text-align:left;
}

.actions{
    display:grid;
    gap:12px;
    margin-top:22px;
}

.btn{
    display:block;
    width:100%;
    text-align:center;
    text-decoration:none;
    padding:14px 16px;
    border-radius:999px;
    font-weight:800;
    font-size:16px;
}

.btn.primary{
    background:#00CFA9;
    color:#fff;
}

.btn.secondary{
    background:#eef2ff;
    color:#4338ca;
}

.print-note{
    margin-top:14px;
    color:#64748b;
    font-size:13px;
    line-height:1.4;
}

@media print{
    .nav,
    .actions,
    .print-note,
    .app-header{
        display:none;
    }

    body{
        background:#fff;
    }

    .page{
        max-width:100%;
        padding:0;
    }

    .card{
        box-shadow:none;
        border:none;
    }
}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Spin QR Code</h1>
    <p>ClubSpin Register Admin</p>
</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <div class="card">

        <h2 class="spin-title">
            <?php echo htmlspecialchars($spin['title']); ?>
        </h2>

        <div class="meta">
            <strong>Date:</strong>
            <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
        </div>

        <div class="meta">
            <strong>Time:</strong>
            <?php echo date("H:i", strtotime($spin['start_time'])); ?>
        </div>

        <?php if (!empty($spin['meeting_point'])): ?>
            <div class="meta">
                <strong>Meet:</strong>
                <?php echo htmlspecialchars($spin['meeting_point']); ?><?php if (!empty($spin['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="meta">
            <strong>Leader:</strong>
            <?php echo htmlspecialchars($spin['leader_name']); ?>
        </div>

        <div class="qr-box">
            <img 
                src="<?php echo htmlspecialchars($qr_url); ?>" 
                alt="QR code for spin registration"
            >
        </div>

        <div class="notice">
            Ask riders who have not registered to scan this QR code before joining the spin.
            It links directly to the registration form for this spin.
        </div>

        <div class="url-box">
            <?php echo htmlspecialchars($registration_url); ?>
        </div>

        <div class="actions">

            <a 
                class="btn primary" 
                href="<?php echo htmlspecialchars($registration_url); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                Open Registration Form
            </a>

            <a 
                class="btn secondary" 
                href="javascript:window.print();"
            >
                Print QR Code
            </a>

        </div>

        <div class="print-note">
            Tip: leaders can show this screen from their phone, or print the QR code if needed.
        </div>

    </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
}
</script>

</body>
</html>