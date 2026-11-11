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

$message = "";

$user_id = $_SESSION['user_id'] ?? $_SESSION['spin_admin_user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '';

if (!$user_id) {
    die("Error: No logged-in user found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $template_id = isset($_POST["template_id"]) ? (int)$_POST["template_id"] : 0;

    if ($action === "delete" && $template_id > 0) {
        if ($user_role === "admin") {
            $stmt = $conn->prepare("
                DELETE FROM CIY_spin_templates
                WHERE template_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $template_id);
        } else {
            $stmt = $conn->prepare("
                DELETE FROM CIY_spin_templates
                WHERE template_id = ?
                  AND created_by = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $template_id, $user_id);
        }

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        if ($stmt->execute()) {
            $message = "<div class='alert success'>Template deleted.</div>";
        } else {
            $message = "<div class='alert error'>Delete failed: " . htmlspecialchars($stmt->error) . "</div>";
        }

        $stmt->close();
    }
}

if ($user_role === "admin") {
    $stmt = $conn->prepare("
        SELECT 
            t.template_id,
            t.title,
            t.meeting_point,
            t.maps_url,
            t.distance_km,
            t.pace_level,
            t.description,
            t.strava_url,
            t.created_at,
            u.name AS created_by_name
        FROM CIY_spin_templates t
        LEFT JOIN CIY_spin_users u
            ON t.created_by = u.user_id
        ORDER BY t.created_at DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT 
            t.template_id,
            t.title,
            t.meeting_point,
            t.maps_url,
            t.distance_km,
            t.pace_level,
            t.description,
            t.strava_url,
            t.created_at,
            u.name AS created_by_name
        FROM CIY_spin_templates t
        LEFT JOIN CIY_spin_users u
            ON t.created_by = u.user_id
        WHERE t.created_by = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
}

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->execute();
$templates = $stmt->get_result();

$pace_labels = [
    'u14' => 'Under 14 — 23–25 km/h',
    'u16' => 'Under 16 — 25–28 km/h'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ride Templates</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=41">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{max-width:860px;margin:0 auto;padding:20px 16px 40px;}
.nav{margin-bottom:18px;}
.nav a{text-decoration:none;color:#00CFA9;font-weight:800;}
.template-list{display:grid;gap:16px;}
.template-card{background:#fff;border-radius:18px;padding:20px;border-top:5px solid #00CFA9;box-shadow:0 8px 24px rgba(63,24,83,.08);}
.template-card h2{margin:0 0 12px;font-size:24px;color:#111827;}
.meta{font-size:15px;line-height:1.6;color:#334155;}
.meta strong{color:#111827;}
.description{margin-top:12px;padding:12px;border-radius:12px;background:#f8fafc;color:#334155;line-height:1.5;}
.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px;}
.actions a,.actions button{text-align:center;text-decoration:none;padding:13px;border-radius:999px;font-weight:800;font-size:15px;border:none;cursor:pointer;}
.use-btn{background:#00CFA9;color:#fff;}
.delete-btn{background:#fee2e2;color:#991b1b;}
.alert{padding:14px;border-radius:12px;margin-bottom:18px;font-weight:800;}
.success{background:#dcfce7;color:#166534;border:1px solid #86efac;}
.error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.empty-state{background:#fff;padding:24px;border-radius:18px;text-align:center;box-shadow:0 8px 24px rgba(63,24,83,.08);}
.logo{width:90px;height:auto;margin-bottom:14px;}
@media(max-width:520px){.actions{grid-template-columns:1fr;}}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Ride Templates</h1>
    <p>Reuse common spins and routes</p>
</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <?php echo $message; ?>

    <?php if ($templates->num_rows > 0): ?>

        <div class="template-list">

            <?php while ($template = $templates->fetch_assoc()): ?>

                <article class="template-card">

                    <h2>
                        <?php echo htmlspecialchars($template['title']); ?>
                    </h2>

                    <div class="meta">

                        <div>
                            <strong>Meeting Point:</strong>
                            <?php echo htmlspecialchars($template['meeting_point']); ?><?php if (!empty($template['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($template['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
                        </div>

                        <?php if (!empty($template['distance_km'])): ?>
                            <div>
                                <strong>Distance:</strong>
                                <?php echo htmlspecialchars(rtrim(rtrim($template['distance_km'], '0'), '.')); ?> km
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($template['pace_level'])): ?>
                            <div>
                                <strong>Pace:</strong>
                                <?php echo htmlspecialchars($pace_labels[$template['pace_level']] ?? $template['pace_level']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($template['created_by_name'])): ?>
                            <div>
                                <strong>Created by:</strong>
                                <?php echo htmlspecialchars($template['created_by_name']); ?>
                            </div>
                        <?php endif; ?>

                    </div>

                    <?php if (!empty($template['description'])): ?>
                        <div class="description">
                            <?php echo nl2br(htmlspecialchars($template['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="actions">

                        <a 
                            class="use-btn"
                            href="create_from_template.php?template_id=<?php echo (int)$template['template_id']; ?>"
                        >
                            Use Template
                        </a>

                        <form method="post" onsubmit="return confirm('Delete this template?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="template_id" value="<?php echo (int)$template['template_id']; ?>">
                            <button type="submit" class="delete-btn">
                                Delete Template
                            </button>
                        </form>

                    </div>

                </article>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">
            <h2>No ride templates yet</h2>
            <p>Create a spin and save it as a template.</p>
        </div>

    <?php endif; ?>

</div>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js");
}
</script>

</body>
</html>