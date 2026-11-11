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
$current_name = $_SESSION['user_name'] ?? $_SESSION['spin_admin_name'] ?? 'User';

$is_admin = ($current_role === 'admin');

$spin_id = isset($_GET['spin_id']) ? (int)$_GET['spin_id'] : 0;

if ($spin_id <= 0) {
    die("Invalid spin ID.");
}

$message = "";
$message_type = "";

/*
|--------------------------------------------------------------------------
| Load Spin
|--------------------------------------------------------------------------
*/

$spin_stmt = $conn->prepare("
    SELECT 
        spin_id,
        title,
        spin_date,
        start_time,
        meeting_point,
        maps_url,
        leader_name,
        leader_phone,
        leader_user_id,
        status
    FROM CIY_spins
    WHERE spin_id = ?
    LIMIT 1
");

if (!$spin_stmt) {
    die("Prepare failed: " . $conn->error);
}

$spin_stmt->bind_param("i", $spin_id);
$spin_stmt->execute();

$spin_result = $spin_stmt->get_result();

if ($spin_result->num_rows === 0) {
    die("Spin not found.");
}

$spin = $spin_result->fetch_assoc();
$spin_stmt->close();

/*
|--------------------------------------------------------------------------
| Ownership Rule
|--------------------------------------------------------------------------
| Admins can reassign any spin.
| Leaders can only reassign their own spins.
|--------------------------------------------------------------------------
*/

if (!$is_admin && (int)$spin['leader_user_id'] !== (int)$current_user_id) {
    die("Access denied. You can only reassign your own spins.");
}

/*
|--------------------------------------------------------------------------
| Handle Reassignment
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_leader_user_id = isset($_POST['new_leader_user_id']) ? (int)$_POST['new_leader_user_id'] : 0;

    if ($new_leader_user_id <= 0) {

        $message = "Please select a new spin leader.";
        $message_type = "error";

    } elseif ($new_leader_user_id === (int)$spin['leader_user_id']) {

        $message = "That user is already assigned as the spin leader.";
        $message_type = "error";

    } else {

        $leader_stmt = $conn->prepare("
            SELECT 
                user_id,
                name,
                phone,
                role,
                active
            FROM CIY_spin_users
            WHERE user_id = ?
              AND active = 1
              AND role IN ('admin', 'leader')
            LIMIT 1
        ");

        if (!$leader_stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $leader_stmt->bind_param("i", $new_leader_user_id);
        $leader_stmt->execute();

        $leader_result = $leader_stmt->get_result();

        if ($leader_result->num_rows === 0) {

            $message = "Selected leader was not found or is inactive.";
            $message_type = "error";

        } else {

            $new_leader = $leader_result->fetch_assoc();

            $update_stmt = $conn->prepare("
                UPDATE CIY_spins
                SET
                    leader_user_id = ?,
                    leader_name = ?,
                    leader_phone = ?
                WHERE spin_id = ?
                LIMIT 1
            ");

            if (!$update_stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $new_leader_name = $new_leader['name'];
            $new_leader_phone = $new_leader['phone'] ?? '';

            $update_stmt->bind_param(
                "issi",
                $new_leader_user_id,
                $new_leader_name,
                $new_leader_phone,
                $spin_id
            );

            if ($update_stmt->execute()) {

                $message = "Spin reassigned successfully to " . htmlspecialchars($new_leader_name) . ".";
                $message_type = "success";

                $spin['leader_user_id'] = $new_leader_user_id;
                $spin['leader_name'] = $new_leader_name;
                $spin['leader_phone'] = $new_leader_phone;

            } else {

                $message = "Reassignment failed: " . htmlspecialchars($update_stmt->error);
                $message_type = "error";

            }

            $update_stmt->close();
        }

        $leader_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Load Active Leaders / Admins
|--------------------------------------------------------------------------
*/

$leaders_stmt = $conn->prepare("
    SELECT 
        user_id,
        name,
        email,
        phone,
        role
    FROM CIY_spin_users
    WHERE active = 1
      AND role IN ('admin', 'leader')
    ORDER BY name ASC
");

if (!$leaders_stmt) {
    die("Prepare failed: " . $conn->error);
}

$leaders_stmt->execute();
$leaders_result = $leaders_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reassign Spin Leader</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=91">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:720px;
    margin:0 auto;
    padding:20px 16px 40px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
    margin-bottom:18px;
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

.spin-title{
    margin-top:0;
    color:#164B38;
    font-size:26px;
}

.meta{
    margin:8px 0;
    line-height:1.5;
    color:#334155;
}

.meta strong{
    color:#111827;
}

.current-leader{
    margin-top:16px;
    padding:14px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#334155;
}

label{
    display:block;
    margin-top:18px;
    margin-bottom:6px;
    font-weight:800;
    color:#111827;
}

select{
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
    padding:14px;
    border-radius:12px;
    margin-bottom:18px;
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

.warning{
    margin-top:14px;
    padding:13px;
    border-radius:12px;
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    font-size:14px;
    line-height:1.5;
}

.small-note{
    margin-top:8px;
    font-size:13px;
    line-height:1.4;
    color:#64748b;
}
</style>
</head>

<body>

<header class="app-header">

    <img 
        src="../assets/images/ci-youth.png?v=9"
        alt="Participating cycling club logos"
        class="logo"
    >

    <h1>Reassign Spin</h1>

    <p>Transfer responsibility to another spin leader</p>

</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

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

        <div class="meta">
            <strong>Meeting Point:</strong>
            <?php echo htmlspecialchars($spin['meeting_point']); ?><?php if (!empty($spin['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
        </div>

        <div class="current-leader">
            <strong>Current Leader:</strong><br>
            <?php echo htmlspecialchars($spin['leader_name']); ?>

            <?php if (!empty($spin['leader_phone'])): ?>
                <br>
                Phone: <?php echo htmlspecialchars($spin['leader_phone']); ?>
            <?php endif; ?>
        </div>

        <form method="POST">

            <label>Choose New Spin Leader</label>

            <select name="new_leader_user_id" required>
                <option value="">Select a leader</option>

                <?php while ($leader = $leaders_result->fetch_assoc()): ?>

                    <option 
                        value="<?php echo (int)$leader['user_id']; ?>"
                        <?php if ((int)$leader['user_id'] === (int)$spin['leader_user_id']) echo 'disabled'; ?>
                    >
                        <?php echo htmlspecialchars($leader['name']); ?>
                        -
                        <?php echo ucfirst(htmlspecialchars($leader['role'])); ?>
                        <?php if ((int)$leader['user_id'] === (int)$spin['leader_user_id']): ?>
                            (current)
                        <?php endif; ?>
                    </option>

                <?php endwhile; ?>

            </select>

            <div class="warning">
                Reassigning this spin will move it to the new leader’s dashboard.
                The previous leader will no longer see or manage it unless they are an admin.
            </div>

            <button 
                type="submit"
                onclick="return confirm('Are you sure you want to reassign this spin to another leader?');"
            >
                Reassign Spin
            </button>

        </form>

        <div class="small-note">
            Logged in as <?php echo htmlspecialchars($current_name); ?>.
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