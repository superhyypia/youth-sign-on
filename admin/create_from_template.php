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

$user_id = $_SESSION['user_id'] ?? $_SESSION['spin_admin_user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '';

if (!$user_id) {
    die("Error: No logged-in user found.");
}

$template_id = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

if ($template_id <= 0) {
    die("Invalid template ID.");
}

if ($user_role === "admin") {
    $stmt = $conn->prepare("
        SELECT *
        FROM CIY_spin_templates
        WHERE template_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $template_id);
} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM CIY_spin_templates
        WHERE template_id = ?
          AND created_by = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $template_id, $user_id);
}

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Template not found or access denied.");
}

$template = $result->fetch_assoc();
$stmt->close();

$user_stmt = $conn->prepare("
    SELECT user_id, name, phone, role, active
    FROM CIY_spin_users
    WHERE user_id = ?
    LIMIT 1
");

if (!$user_stmt) {
    die("Prepare failed: " . $conn->error);
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$leader = $user_result->fetch_assoc();
$user_stmt->close();

if (!$leader || (isset($leader['active']) && (int)$leader['active'] !== 1)) {
    die("Leader account not found or inactive.");
}

$current_month = date('n');
$default_start_time = ($current_month >= 4 && $current_month <= 9) ? "09:00" : "09:45";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $spin_date = trim($_POST["spin_date"] ?? "");
    $start_time = trim($_POST["start_time"] ?? "");
    $meeting_point = trim($_POST["meeting_point"] ?? "");
    $maps_url = trim($_POST["maps_url"] ?? "");
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
        $message = "<div class='alert error'>Please enter a valid Google Maps link beginning with https://.</div>";
    } elseif ($title === "" || $spin_date === "" || $start_time === "" || $meeting_point === "" || $pace_level === "") {
        $message = "<div class='alert error'>Please complete all required fields.</div>";
    } else {
        $leader_name = $leader['name'];
        $leader_phone = $leader['phone'] ?? '';
        $created_by = (int)$user_id;
        $leader_user_id = (int)$user_id;

        $distance_km_db = ($distance_km === "") ? null : (float)$distance_km;
        $max_riders_db = ($max_riders === "") ? 12 : (int)$max_riders;

        $insert = $conn->prepare("
            INSERT INTO CIY_spins
            (
                title,
                spin_date,
                start_time,
                meeting_point,
                maps_url,
                leader_name,
                leader_phone,
                distance_km,
                pace_level,
                description,
                strava_url,
                max_riders,
                status,
                created_by,
                leader_user_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$insert) {
            die("Prepare failed: " . $conn->error);
        }

        $insert->bind_param(
            "sssssssdsssisii",
            $title,
            $spin_date,
            $start_time,
            $meeting_point,
            $maps_url,
            $leader_name,
            $leader_phone,
            $distance_km_db,
            $pace_level,
            $description,
            $strava_url,
            $max_riders_db,
            $status,
            $created_by,
            $leader_user_id
        );

        if ($insert->execute()) {
            $new_spin_id = $conn->insert_id;
            header("Location: riders.php?spin_id=" . (int)$new_spin_id);
            exit;
        } else {
            $message = "<div class='alert error'>Error creating spin: " . htmlspecialchars($insert->error) . "</div>";
        }

        $insert->close();
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
<title>Create From Template</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=42">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{max-width:760px;margin:0 auto;padding:20px 16px 40px;}
.card{background:#fff;border-radius:18px;padding:22px;border-top:5px solid #00CFA9;box-shadow:0 8px 24px rgba(63,24,83,.10);}
.nav{margin-bottom:18px;}
.nav a{text-decoration:none;color:#00CFA9;font-weight:800;}
.logo{width:90px;height:auto;margin-bottom:14px;}
label{display:block;font-weight:800;margin-top:16px;margin-bottom:6px;color:#111827;}
input,textarea,select{width:100%;padding:13px;border-radius:10px;border:1px solid #cbd5e1;font-size:16px;box-sizing:border-box;background:#fff;}
textarea{min-height:120px;}
.alert{padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800;}
.success{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.template-note{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:18px;color:#334155;line-height:1.5;}
button{margin-top:24px;width:100%;padding:15px;border:none;border-radius:999px;background:#00CFA9;color:#fff;font-size:16px;font-weight:800;cursor:pointer;}
button:hover{background:#164B38;}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Create From Template</h1>
    <p>Reuse a saved ride</p>
</header>

<div class="page">

    <div class="nav">
        <a href="templates.php">← Back to Ride Templates</a>
    </div>

    <div class="card">

        <?php echo $message; ?>

        <div class="template-note">
            Creating new spin from template:
            <strong><?php echo htmlspecialchars($template['title']); ?></strong>
        </div>

        <form method="POST">

            <label>Spin Title *</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($template['title']); ?>" required>

            <label>Spin Date *</label>
            <input type="date" name="spin_date" required>

            <label>Start Time *</label>
            <input type="time" name="start_time" value="<?php echo htmlspecialchars($default_start_time); ?>" required>

            <label>Meeting Point *</label>
            <input type="text" name="meeting_point" value="<?php echo htmlspecialchars($template['meeting_point']); ?>" required>

            <label>Google Maps Link</label>
            <input type="url" name="maps_url" value="<?php echo htmlspecialchars($template['maps_url'] ?? ''); ?>" placeholder="https://maps.app.goo.gl/...">

            <label>Distance (km)</label>
            <input type="number" step="0.1" name="distance_km" value="<?php echo htmlspecialchars($template['distance_km'] ?? ''); ?>">

            <label>Age Group / Expected Pace *</label>
            <select name="pace_level" required>
                <option value="">Select age group</option>
                <?php foreach ($pace_labels as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php if (($template['pace_level'] ?? '') === $value) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Route Link</label>
            <input type="url" name="strava_url" value="<?php echo htmlspecialchars($template['strava_url'] ?? ''); ?>">

            <label>Maximum Riders</label>
            <input type="number" name="max_riders" min="1" value="12">

            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($template['description'] ?? ''); ?></textarea>

            <label>Status</label>
            <select name="status">
                <option value="draft">Draft</option>
                <option value="final">Final</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <button type="submit">Create Spin From Template</button>

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