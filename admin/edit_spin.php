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

$message = "";

/*
    Load spin first so we can check ownership before allowing updates.
*/
$sql = "SELECT * FROM CIY_spins WHERE spin_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);

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
    Ownership rule:
    - Admins can edit any spin
    - Leaders can only edit their own spins
*/
if (!$is_admin && (int)$spin['leader_user_id'] !== (int)$current_user_id) {
    die("Access denied. You can only edit your own spins.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $spin_date = trim($_POST["spin_date"] ?? "");
    $start_time = trim($_POST["start_time"] ?? "");
    $meeting_point = trim($_POST["meeting_point"] ?? "");
    $maps_url = trim($_POST["maps_url"] ?? "");
    $leader_name = trim($_POST["leader_name"] ?? "");
    $leader_phone = trim($_POST["leader_phone"] ?? "");
    $distance_km = trim($_POST["distance_km"] ?? "");
    $pace_level = trim($_POST["pace_level"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $strava_url = trim($_POST["strava_url"] ?? "");
    $max_riders = trim($_POST["max_riders"] ?? "");
    $status = trim($_POST["status"] ?? "draft");

    $allowed_statuses = ['draft', 'final', 'cancelled'];

    if (!in_array($status, $allowed_statuses)) {
        $status = 'draft';
    }

    $allowed_paces = ['u14', 'u16'];

    if (!in_array($pace_level, $allowed_paces)) {
        $pace_level = '';
    }

    $maps_url_valid = true;
    if ($maps_url !== "") {
        $maps_parts = parse_url($maps_url);
        $maps_host = strtolower($maps_parts['host'] ?? '');
        $maps_path = $maps_parts['path'] ?? '';
        $valid_maps_host = in_array($maps_host, ['maps.app.goo.gl', 'goo.gl'], true) || $maps_host === 'google.com' || str_ends_with($maps_host, '.google.com');
        $valid_maps_path = ($maps_host === 'maps.app.goo.gl') || ($maps_host === 'goo.gl' && str_starts_with($maps_path, '/maps')) || str_starts_with($maps_path, '/maps');
        $maps_url_valid = (($maps_parts['scheme'] ?? '') === 'https' && $valid_maps_host && $valid_maps_path);
    }

    if (!$maps_url_valid) {
        $message = "Please enter a valid Google Maps link beginning with https://.";
    } elseif (
        $title === "" ||
        $spin_date === "" ||
        $start_time === "" ||
        $meeting_point === "" ||
        $leader_name === "" ||
        $pace_level === ""
    ) {
        $message = "Please complete all required fields.";
    } else {

        $sql = "
            UPDATE CIY_spins
            SET
                title = ?,
                spin_date = ?,
                start_time = ?,
                meeting_point = ?,
                maps_url = ?,
                leader_name = ?,
                leader_phone = ?,
                distance_km = ?,
                pace_level = ?,
                description = ?,
                strava_url = ?,
                max_riders = ?,
                status = ?
            WHERE spin_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $distance_value = ($distance_km !== "") ? (float)$distance_km : null;
        $max_riders_value = ($max_riders !== "") ? (int)$max_riders : 12;

        $stmt->bind_param(
            "sssssssdsssisi",
            $title,
            $spin_date,
            $start_time,
            $meeting_point,
            $maps_url,
            $leader_name,
            $leader_phone,
            $distance_value,
            $pace_level,
            $description,
            $strava_url,
            $max_riders_value,
            $status,
            $spin_id
        );

        if ($stmt->execute()) {
            $message = "Spin updated successfully.";

            /*
                Reload spin after update so form shows latest saved values.
            */
            $reload_stmt = $conn->prepare("
                SELECT *
                FROM CIY_spins
                WHERE spin_id = ?
                LIMIT 1
            ");

            if (!$reload_stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $reload_stmt->bind_param("i", $spin_id);
            $reload_stmt->execute();

            $reload_result = $reload_stmt->get_result();

            if ($reload_result->num_rows === 1) {
                $spin = $reload_result->fetch_assoc();
            }

            $reload_stmt->close();

        } else {
            $message = "Update failed: " . htmlspecialchars($stmt->error);
        }

        $stmt->close();
    }
}

$pace_labels = [
    'u14' => 'Under 14 — 23–25 km/h',
    'u16' => 'Under 16 — 25–28 km/h'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Spin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=90">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:760px;
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

input,
textarea,
select{
    width:100%;
    padding:13px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-size:16px;
    background:#fff;
    box-sizing:border-box;
}

textarea{
    min-height:120px;
}

.help-text{
    font-size:13px;
    color:#64748b;
    margin-top:6px;
    line-height:1.4;
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
    background:#dcfce7;
    color:#166534;
    font-weight:800;
    border:1px solid #86efac;
}

.message.error{
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
}

.status-help{
    margin-top:10px;
    padding:12px;
    border-radius:12px;
    background:#E8F8F3;
    color:#1e3a8a;
    font-size:14px;
    line-height:1.4;
    border:1px solid #B7E8DB;
}

.readonly-note{
    margin-top:10px;
    padding:12px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#475569;
    font-size:14px;
    line-height:1.4;
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

    <h1>Edit Spin</h1>

    <p>ClubSpin Register Admin</p>

</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <div class="card">

        <?php if (!empty($message)): ?>

            <div class="message <?php echo (strpos($message, 'failed') !== false || strpos($message, 'required') !== false) ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form method="post">

            <label>Spin Title *</label>
            <input 
                type="text" 
                name="title" 
                value="<?php echo htmlspecialchars($spin['title'] ?? ''); ?>" 
                required
            >

            <label>Date *</label>
            <input 
                type="date" 
                name="spin_date" 
                value="<?php echo htmlspecialchars($spin['spin_date'] ?? ''); ?>" 
                required
            >

            <label>Start Time *</label>
            <input 
                type="time" 
                name="start_time" 
                value="<?php echo htmlspecialchars(substr($spin['start_time'] ?? '', 0, 5)); ?>" 
                required
            >

            <label>Meeting Point *</label>
            <input 
                type="text" 
                name="meeting_point" 
                value="<?php echo htmlspecialchars($spin['meeting_point'] ?? 'To be confirmed'); ?>" 
                required
            >

            <label>Google Maps Link</label>
            <input
                type="url"
                name="maps_url"
                value="<?php echo htmlspecialchars($spin['maps_url'] ?? ''); ?>"
                placeholder="https://maps.app.goo.gl/..."
            >
            <small>Optional. Paste the Google Maps share link for the meeting point.</small>

            <label>Leader Name *</label>
            <input 
                type="text" 
                name="leader_name" 
                value="<?php echo htmlspecialchars($spin['leader_name'] ?? ''); ?>" 
                required
            >

            <label>Leader Phone</label>
            <input 
                type="text" 
                name="leader_phone" 
                value="<?php echo htmlspecialchars($spin['leader_phone'] ?? ''); ?>"
            >

            <label>Distance KM</label>
            <input 
                type="number" 
                step="0.1" 
                name="distance_km" 
                value="<?php echo htmlspecialchars($spin['distance_km'] ?? ''); ?>"
            >

            <label>Age Group / Expected Pace *</label>
            <select name="pace_level" required>

                <option value="">Select age group</option>

                <?php foreach ($pace_labels as $value => $label): ?>

                    <option 
                        value="<?php echo htmlspecialchars($value); ?>"
                        <?php if (($spin['pace_level'] ?? '') === $value) echo 'selected'; ?>
                    >
                        <?php echo htmlspecialchars($label); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <div class="help-text">
                The rider app displays the age group and expected pace.
            </div>

            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($spin['description'] ?? ''); ?></textarea>

            <label>Route URL</label>
            <input 
                type="url" 
                name="strava_url" 
                value="<?php echo htmlspecialchars($spin['strava_url'] ?? ''); ?>"
            >

            <label>Maximum Riders</label>
            <input 
                type="number" 
                name="max_riders" 
                min="1"
                value="<?php echo htmlspecialchars($spin['max_riders'] ?? '12'); ?>"
            >

            <label>Status</label>
            <select name="status">

                <option 
                    value="draft" 
                    <?php if (($spin['status'] ?? '') === 'draft') echo 'selected'; ?>
                >
                    Draft
                </option>

                <option 
                    value="final" 
                    <?php if (($spin['status'] ?? '') === 'final') echo 'selected'; ?>
                >
                    Final
                </option>

                <option 
                    value="cancelled" 
                    <?php if (($spin['status'] ?? '') === 'cancelled') echo 'selected'; ?>
                >
                    Cancelled
                </option>

            </select>

            <div class="status-help">
                <strong>Draft</strong> is hidden from riders.
                <strong>Final</strong> is visible and open for registration.
                <strong>Cancelled</strong> remains visible but registration is closed.
            </div>

            <?php if ($is_admin): ?>
                <div class="readonly-note">
                    <strong>Admin note:</strong>
                    This spin is assigned to leader user ID
                    <?php echo (int)($spin['leader_user_id'] ?? 0); ?>.
                </div>
            <?php endif; ?>

            <button type="submit">
                Update Spin
            </button>

        </form>

    </div>

</div>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js");
}
</script>

</body>
</html>