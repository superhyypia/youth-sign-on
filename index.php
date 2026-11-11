<?php
/*
 * CI-Youths ClubSpin Register
 * Copyright (c) 2026 Bray Wheelers Cycling Club. All rights reserved.
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/includes/db_loader.php';
include_once __DIR__ . '/includes/weather.php';

$conn = connect_to_database();
$conn->set_charset("utf8mb4");

$app_version = "1.0";

$rider_profile = [];

if (!empty($_COOKIE['clubspin_rider'])) {
    $decoded = json_decode($_COOKIE['clubspin_rider'], true);

    if (is_array($decoded)) {
        $rider_profile = $decoded;
    }
}

$registered_spin_ids = [];

if (!empty($rider_profile['email']) || !empty($rider_profile['licence_number'])) {

    $email = $rider_profile['email'] ?? '';
    $licence_number = $rider_profile['licence_number'] ?? '';

    $registered_stmt = $conn->prepare("
        SELECT spin_id
        FROM CIY_spin_registrations
        WHERE email = ?
           OR licence_number = ?
    ");

    if ($registered_stmt) {

        $registered_stmt->bind_param(
            "ss",
            $email,
            $licence_number
        );

        $registered_stmt->execute();

        $registered_result = $registered_stmt->get_result();

        while ($row = $registered_result->fetch_assoc()) {
            $registered_spin_ids[] = (int)$row['spin_id'];
        }

        $registered_stmt->close();
    }
}

$sql = "
    SELECT 
        s.spin_id,
        s.title,
        s.spin_date,
        s.start_time,
        s.meeting_point,
        s.maps_url,
        s.leader_name,
        s.distance_km,
        s.pace_level,
        s.strava_url,
        s.description,
        s.status,
        s.max_riders,
        COUNT(r.registration_id) AS rider_count
    FROM CIY_spins s
    LEFT JOIN CIY_spin_registrations r
        ON s.spin_id = r.spin_id
    WHERE s.spin_date >= CURDATE()
      AND s.status IN ('final','cancelled')
    GROUP BY 
        s.spin_id,
        s.title,
        s.spin_date,
        s.start_time,
        s.meeting_point,
        s.maps_url,
        s.leader_name,
        s.distance_km,
        s.pace_level,
        s.strava_url,
        s.description,
        s.status,
        s.max_riders
    ORDER BY s.spin_date ASC, s.start_time ASC
";

$result = $conn->query($sql);

if (!$result) {
    error_log("ClubSpin index query failed: " . $conn->error);
    die("Sorry, we could not load the upcoming spins at this time.");
}

$pace_labels = [
    'u14' => ['label' => 'Under 14', 'class' => 'u14'],
    'u16' => ['label' => 'Under 16', 'class' => 'u16']
];

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function clean_distance($distance) {
    if ($distance === null || $distance === '') {
        return '';
    }

    return rtrim(rtrim((string)$distance, '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inter-Club Youth Spins</title>

<link rel="stylesheet" href="assets/css/style.css?v=47">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>

.spin-card{
    position:relative;
    padding:46px 20px 20px;
}

.spin-card h2{
    margin-bottom:12px;
    font-size:26px;
}

.badge-row{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:12px;
}

.status-badge{
    display:inline-block;
    padding:5px 11px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}

.status-final{
    background:#dcfce7;
    color:#166534;
}

.status-cancelled{
    background:#fee2e2;
    color:#991b1b;
}

.pace-box{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-size:13px;
    font-weight:800;
}

.pace-u14{ background:#D9F7EE; color:#164B38; }
.pace-u16{ background:#D8F7F1; color:#0F766E; }

.pace-blue{
    background:#D5F3EB;
    color:#1e3a8a;
}

.pace-green{
    background:#dcfce7;
    color:#166534;
}

.pace-yellow{
    background:#fef9c3;
    color:#854d0e;
}

.pace-orange{
    background:#ffedd5;
    color:#9a3412;
}

.pace-red{
    background:#fee2e2;
    color:#991b1b;
}

.going-pill{
    display:inline-block;
    margin-top:12px;
    padding:8px 14px;
    border-radius:999px;
    background:#D9F7EE;
    color:#00CFA9;
    font-size:14px;
    font-weight:900;
}

.weather-pill{
    position:absolute;
    top:14px;
    right:14px;
    display:flex;
    align-items:center;
    gap:7px;
    font-size:11px;
    font-weight:800;
    color:#475569;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:999px;
    padding:6px 9px;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}

.weather-indicator{
    width:13px;
    height:13px;
    border-radius:50%;
    border:2px solid #fff;
    box-shadow:0 2px 8px rgba(0,0,0,.2);
}

.weather-green{background:#22c55e;}
.weather-orange{background:#f97316;}
.weather-red{background:#dc2626;}
.weather-unknown{background:#94a3b8;}

.compact-meta{
    margin-top:4px;
    font-size:16px;
    line-height:1.55;
}

.compact-meta strong{
    color:#111827;
}

.distance-line{
    margin-top:8px;
    font-size:16px;
    line-height:1.55;
}

.spin-actions{
    display:grid;
    gap:10px;
    margin-top:18px;
}

.action-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}

.btn{
    padding:11px 14px;
    font-size:15px;
    line-height:1.2;
}

.btn.primary{
    padding:14px 16px;
    font-size:16px;
}

button.btn{
    border:none;
    cursor:pointer;
    width:100%;
    font-family:inherit;
}

.closed-badge{
    display:block;
    text-align:center;
    margin-top:12px;
    padding:12px 16px;
    border-radius:999px;
    background:#fee2e2;
    color:#991b1b;
    font-weight:800;
}

.registered-badge{
    display:block;
    text-align:center;
    margin-top:12px;
    padding:12px 16px;
    border-radius:999px;
    background:#dcfce7;
    color:#166534;
    font-weight:800;
}

.modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.72);
    z-index:9999;
    padding:20px;
}

.modal-box{
    background:#fff;
    max-width:620px;
    margin:80px auto 0;
    border-radius:20px;
    padding:24px;
    box-shadow:0 20px 60px rgba(0,0,0,.28);
}

.modal-box h2{
    margin-top:0;
    margin-bottom:14px;
    color:#164B38;
    font-size:26px;
}

.modal-content{
    color:#334155;
    line-height:1.6;
    font-size:16px;
    white-space:normal;
}

.modal-close{
    margin-top:22px;
}

.app-version{
    text-align:center;
    color:#64748b;
    font-size:12px;
    margin:4px 0 24px;
}

@media(max-width:420px){

    .action-row{
        grid-template-columns:1fr;
    }

    .modal-box{
        margin:50px auto 0;
        padding:20px;
    }
}

.site-footer,
.app-version{
    text-align:center;
    width:100%;
    margin-left:auto;
    margin-right:auto;
}

.site-footer{
    display:block;
    padding:18px 16px 6px;
    color:#64748b;
    font-size:13px;
}

.app-version{
    color:#64748b;
    font-size:12px;
    margin:4px auto 24px;
}


.maps-pill{
    display:inline-flex;
    align-items:center;
    margin-left:7px;
    padding:3px 9px;
    border-radius:999px;
    background:#f3e8ff;
    color:#6a2c82;
    font-size:12px;
    font-weight:800;
    line-height:1.4;
    text-decoration:none;
    white-space:nowrap;
    vertical-align:middle;
}
.maps-pill:hover,
.maps-pill:focus{
    background:#e9d5ff;
    color:#3f1853;
}
</style>
</head>

<body>

<header class="app-header">

    <img 
        src="assets/images/ci-youth.png?v=9"
        alt="Participating cycling club logos"
    >

    <h1>Inter-Club Youth Spins</h1>

    <p>
        Register for Upcoming Spins
    </p>

</header>

<main class="spin-list">

<?php if ($result->num_rows > 0): ?>

    <?php while ($spin = $result->fetch_assoc()): ?>

        <?php

        $spin_id = (int)$spin['spin_id'];
        $pace = trim($spin['pace_level'] ?? '');
        $status = trim($spin['status'] ?? '');
        $is_cancelled = ($status === 'cancelled');

        $weather = get_spin_weather(
            $spin['spin_date'],
            $spin['start_time']
        );

        $spin_date_label = date("D j M Y", strtotime($spin['spin_date']));
        $spin_time_label = date("H:i", strtotime($spin['start_time']));
        $distance_label = clean_distance($spin['distance_km']);

        $whatsapp_text = $spin['title'] . " - " .
            $spin_date_label .
            " at " .
            $spin_time_label;

        if (!empty($spin['meeting_point'])) {
            $whatsapp_text .= ". Meet: " . $spin['meeting_point'];
        }
        if (!empty($spin['maps_url'])) {
            $whatsapp_text .= ". Map: " . $spin['maps_url'];
        }

        if (!empty($spin['leader_name'])) {
            $whatsapp_text .= ". Leader: " . $spin['leader_name'];
        }

        if (!empty($distance_label)) {
            $whatsapp_text .= ". Distance: " . $distance_label . " km";
        }

        if (!empty($pace) && isset($pace_labels[$pace])) {
            $whatsapp_text .= ". Age Group: " . $pace_labels[$pace]['label'];
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $whatsapp_text .= ". Register here: " . $protocol . $_SERVER['HTTP_HOST'] . "/clubspins/register.php?spin_id=" . $spin_id;

        $whatsapp_message = rawurlencode($whatsapp_text);

        $is_registered = in_array(
            $spin_id,
            $registered_spin_ids,
            true
        );

        $modal_id = "routeModal" . $spin_id;

        ?>

        <article class="spin-card">

            <div 
                class="weather-pill"
                title="<?php echo h($weather['label'] ?? 'Weather unavailable'); ?>"
            >
                <span>Expected Conditions</span>

                <span 
                    class="weather-indicator weather-<?php echo h($weather['status'] ?? 'unknown'); ?>"
                ></span>
            </div>

            <h2>
                <?php echo h($spin['title']); ?>
            </h2>

            <div class="badge-row">

                <span class="status-badge status-<?php echo h($status); ?>">
                    <?php echo strtoupper(h($status)); ?>
                </span>

                <?php if (!empty($pace) && isset($pace_labels[$pace])): ?>

                    <span class="pace-box pace-<?php echo h($pace_labels[$pace]['class']); ?>">
                        <?php echo h($pace_labels[$pace]['label']); ?>
                    </span>

                <?php endif; ?>

            </div>

            <div class="compact-meta">

                <div>
                    <strong>
                        <?php echo h($spin_date_label); ?>
                    </strong>

                    at

                    <?php echo h($spin_time_label); ?>
                </div>

                <div>
                    <strong>Meet:</strong>
                    <?php echo !empty($spin['meeting_point']) ? h($spin['meeting_point']) : 'TBC'; ?>
                    <?php if (!empty($spin['maps_url'])): ?>
                        <a class="maps-pill" href="<?php echo h($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open meeting point in Google Maps">📍 Maps</a>
                    <?php endif; ?>
                </div>

                <div>
                    <strong>Leader:</strong>
                    <?php echo !empty($spin['leader_name']) ? h($spin['leader_name']) : 'TBC'; ?>
                </div>

                <?php if (!empty($distance_label)): ?>

                    <div class="distance-line">
                        <strong>Distance:</strong>
                        <?php echo h($distance_label); ?> km
                    </div>

                <?php endif; ?>

                <div class="going-pill">
                    <?php echo (int)$spin['rider_count']; ?> Going
                </div>

            </div>

            <div class="spin-actions">

                <?php if (!empty($spin['description'])): ?>

                    <button 
                        type="button"
                        class="btn secondary"
                        onclick="openRouteDescription('<?php echo h($modal_id); ?>')"
                    >
                        Route Description
                    </button>

                <?php endif; ?>

                <div class="action-row">

                    <?php if (!empty($spin['strava_url'])): ?>

                        <a 
                            class="btn secondary"
                            href="<?php echo h($spin['strava_url']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            View Route
                        </a>

                    <?php endif; ?>

                    <a 
                        class="btn secondary"
                        href="https://wa.me/?text=<?php echo $whatsapp_message; ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Share Spin
                    </a>

                </div>

                <?php if ($is_cancelled): ?>

                    <div class="closed-badge">
                        REGISTRATION CLOSED
                    </div>

                <?php elseif ($is_registered): ?>

                    <div class="registered-badge">
                        ALREADY REGISTERED
                    </div>

                    <a 
                        class="btn secondary"
                        href="cancel_registration.php?spin_id=<?php echo $spin_id; ?>"
                    >
                        Cancel Registration
                    </a>

                <?php else: ?>

                    <a 
                        class="btn primary"
                        href="register.php?spin_id=<?php echo $spin_id; ?>"
                    >
                        Register
                    </a>

                    <a 
                        class="btn secondary"
                        href="cancel_registration.php?spin_id=<?php echo $spin_id; ?>"
                    >
                        Cancel Registration
                    </a>

                <?php endif; ?>

            </div>

            <?php if (!empty($spin['description'])): ?>

                <div 
                    id="<?php echo h($modal_id); ?>" 
                    class="modal-overlay"
                >
                    <div class="modal-box">

                        <h2>
                            <?php echo h($spin['title']); ?>
                        </h2>

                        <div class="modal-content">
                            <?php echo nl2br(h($spin['description'])); ?>
                        </div>

                        <button 
                            type="button" 
                            class="btn primary modal-close"
                            onclick="closeRouteDescription('<?php echo h($modal_id); ?>')"
                        >
                            Close
                        </button>

                    </div>
                </div>

            <?php endif; ?>

        </article>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty-state">

        <h2>No upcoming spins yet</h2>

        <p>
            Check back soon. Spin Leaders normally add weekend spins here on Thursdays or Fridays
        </p>

    </div>

<?php endif; ?>

</main>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<div class="app-version">
    ClubSpin Register v<?php echo h($app_version); ?>
</div>

<script>
function openRouteDescription(id) {
    var modal = document.getElementById(id);

    if (modal) {
        modal.style.display = "block";
    }
}

function closeRouteDescription(id) {
    var modal = document.getElementById(id);

    if (modal) {
        modal.style.display = "none";
    }
}

window.addEventListener("click", function(event) {
    if (event.target.classList.contains("modal-overlay")) {
        event.target.style.display = "none";
    }
});

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
}
</script>

</body>
</html>