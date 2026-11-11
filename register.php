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

$rider_profile = [];

if (!empty($_COOKIE['clubspin_rider'])) {
    $decoded = json_decode($_COOKIE['clubspin_rider'], true);

    if (is_array($decoded)) {
        $rider_profile = $decoded;
    }
}

$spin_id = isset($_GET['spin_id']) ? (int)$_GET['spin_id'] : 0;
$already_registered_notice = isset($_GET['already_registered']) && $_GET['already_registered'] == '1';

if ($spin_id <= 0) {
    die("Invalid spin.");
}

$stmt = $conn->prepare("
    SELECT 
        spin_id,
        title,
        spin_date,
        start_time,
        meeting_point,
        maps_url,
        leader_name,
        distance_km,
        pace_level,
        description,
        strava_url,
        status
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

$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS registered_count
    FROM CIY_spin_registrations
    WHERE spin_id = ?
");

if (!$count_stmt) {
    die("Prepare failed: " . $conn->error);
}

$count_stmt->bind_param("i", $spin_id);
$count_stmt->execute();

$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();

$registered_count = (int)$count_row['registered_count'];

$count_stmt->close();

$clubs = [];
$club_result = $conn->query("SELECT club_name FROM CIY_clubs WHERE is_active = 1 ORDER BY club_name ASC");
if (!$club_result) { error_log('CIY_clubs lookup failed: ' . $conn->error); die('Club registration is temporarily unavailable.'); }
while ($club_row = $club_result->fetch_assoc()) { $clubs[] = $club_row['club_name']; }
$club_result->free();
$is_cancelled = ($spin['status'] === 'cancelled');
$is_draft = ($spin['status'] === 'draft');
$is_final = ($spin['status'] === 'final');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register for Spin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    background:#f4f5f7;
    color:#1f2933;
}

.header{
    background:linear-gradient(135deg,#164B38,#00CFA9);
    color:#fff;
    padding:24px 20px;
    text-align:center;
    border-bottom:5px solid #00A98F;
}

.header img{
    width:90px;
    height:auto;
    margin-bottom:14px;
}

.header h1{
    margin:0;
    font-size:28px;
}

.header p{
    margin-top:8px;
    color:#D9F7EE;
}

.page{
    max-width:760px;
    margin:0 auto;
    padding:20px;
}

.nav{
    margin-bottom:18px;
}

.nav a{
    color:#00CFA9;
    font-weight:700;
    text-decoration:none;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    margin-bottom:18px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
}

.spin-title{
    margin-top:0;
    margin-bottom:10px;
    font-size:24px;
    color:#164B38;
}

.spin-meta{
    margin:8px 0;
    font-size:15px;
}

.status-badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:999px;
    font-size:13px;
    font-weight:800;
    margin:0 0 12px;
}

.status-final{
    background:#dcfce7;
    color:#166534;
}

.status-draft{
    background:#ffedd5;
    color:#c2410c;
}

.status-cancelled{
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

label{
    display:block;
    font-weight:700;
    margin-top:16px;
    margin-bottom:6px;
}

.required{
    color:#dc2626;
    font-weight:800;
}

input,
textarea,
select{
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #cbd5e1;
    font-size:16px;
}

textarea{
    min-height:90px;
}

select{
    min-height:52px;
    background:#fff;
    color:#1f2937;
    font-family:inherit;
    cursor:pointer;
    appearance:auto;
}

.checkbox-row{
    display:flex;
    gap:10px;
    align-items:flex-start;
    margin-top:18px;
}

.checkbox-row input{
    width:auto;
    margin-top:4px;
}

.checkbox-row label{
    margin:0;
    font-weight:500;
    line-height:1.4;
}

button,
.btn{
    display:block;
    width:100%;
    margin-top:20px;
    padding:14px 16px;
    border:none;
    border-radius:999px;
    background:#00CFA9;
    color:#fff;
    font-size:16px;
    font-weight:700;
    text-align:center;
    text-decoration:none;
    cursor:pointer;
}

.btn.secondary{
    background:#E8F8F3;
    color:#1e3a8a;
    border:1px solid #00A98F;
}

.notice{
    padding:14px;
    border-radius:12px;
    background:#fff7ed;
    color:#9a3412;
    font-weight:700;
    margin-top:14px;
    line-height:1.5;
}

.notice.cancelled{
    background:#fee2e2;
    color:#991b1b;
}

.notice.draft{
    background:#fef3c7;
    color:#92400e;
}

.notice.already{
    background:#E8F8F3;
    color:#1e3a8a;
    border:1px solid #B7E8DB;
}

.small-note{
    font-size:13px;
    color:#64748b;
    line-height:1.4;
    margin-top:10px;
}

.hp-field{
    position:absolute;
    left:-9999px;
    top:auto;
    width:1px;
    height:1px;
    overflow:hidden;
}

.modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.68);
    z-index:999;
    padding:20px;
}

.modal-box{
    background:#fff;
    max-width:620px;
    margin:80px auto 0;
    border-radius:18px;
    padding:22px;
    box-shadow:0 20px 50px rgba(0,0,0,.25);
}

.modal-box h2{
    margin-top:0;
    color:#164B38;
}

.modal-close{
    background:#111827;
}

.site-footer{
    text-align:center;
    padding:24px 16px 36px;
    color:#64748b;
    font-size:13px;
    line-height:1.5;
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

<header class="header">
    <img src="assets/images/ci-youth.png?v=9" alt="Participating cycling club logos">
    <h1>Register for Spin</h1>
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

        <div class="status-badge status-<?php echo htmlspecialchars($spin['status']); ?>">
            <?php echo strtoupper(htmlspecialchars($spin['status'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Date:</strong>
            <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Time:</strong>
            <?php echo date("H:i", strtotime($spin['start_time'])); ?>
        </div>

        <?php if (!empty($spin['meeting_point'])): ?>
            <div class="spin-meta">
                <strong>Meet:</strong>
                <?php echo htmlspecialchars($spin['meeting_point']); ?>
                <?php if (!empty($spin['maps_url'])): ?>
                    <a class="maps-pill" href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open meeting point in Google Maps">📍 Maps</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($spin['leader_name'])): ?>
            <div class="spin-meta">
                <strong>Leader:</strong>
                <?php echo htmlspecialchars($spin['leader_name']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($spin['distance_km'])): ?>
            <div class="spin-meta">
                <strong>Distance:</strong>
                <?php echo htmlspecialchars(rtrim(rtrim($spin['distance_km'], '0'), '.')); ?> km
            </div>
        <?php endif; ?>

        <div class="going-pill">
            <?php echo $registered_count; ?>
            <?php echo ($registered_count === 1) ? 'rider' : 'riders'; ?>
            already registered
        </div>

        <?php if (!empty($spin['description'])): ?>
            <button type="button" class="btn secondary" onclick="openDescriptionModal()">
                View Spin Description
            </button>
        <?php endif; ?>
    </div>

    <div class="card">

        <?php if ($is_cancelled): ?>

            <div class="notice cancelled">
                This spin has been cancelled.
            </div>

        <?php elseif ($is_draft): ?>

            <div class="notice draft">
                This spin is not yet open for registration.
            </div>

        <?php elseif (!$is_final): ?>

            <div class="notice draft">
                This spin is not open for registration.
            </div>

        <?php else: ?>

            <?php if ($already_registered_notice): ?>
                <div class="notice already">
                    You appear to already be registered for this spin using that email address.
                </div>
            <?php endif; ?>

            <h2>Rider Details</h2>

            <form method="post" action="register_submit.php">

                <input type="hidden" name="spin_id" value="<?php echo (int)$spin['spin_id']; ?>">

                <label>First Name <span class="required">*</span></label>
                <input 
                    type="text" 
                    name="first_name" 
                    value="<?php echo htmlspecialchars($rider_profile['first_name'] ?? ''); ?>"
                    required
                >

                <label>Last Name <span class="required">*</span></label>
                <input 
                    type="text" 
                    name="last_name" 
                    value="<?php echo htmlspecialchars($rider_profile['last_name'] ?? ''); ?>"
                    required
                >

                <label>Mobile Number <span class="required">*</span></label>
                <input 
                    type="tel" 
                    name="mobile" 
                    value="<?php echo htmlspecialchars($rider_profile['mobile'] ?? ''); ?>"
                    required
                >

                <label>Email Address <span class="required">*</span></label>
                <input 
                    type="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($rider_profile['email'] ?? ''); ?>"
                    required
                >

                <label>Club <span class="required">*</span></label>
                <?php $selected_club = $rider_profile['licence_number'] ?? ''; ?>
                <select name="licence_number" required>
                    <option value="" <?php echo $selected_club === '' ? 'selected' : ''; ?> disabled>Select your club</option>
                    <?php foreach ($clubs as $club_name): ?>
                    <option value="<?php echo htmlspecialchars($club_name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_club === $club_name ? 'selected' : ''; ?>><?php echo htmlspecialchars($club_name, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                    <option value="Other" <?php echo $selected_club === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>

                <label for="ci_licence_number">Cycling Ireland Licence Number (optional)</label>
                <input type="text" id="ci_licence_number" name="ci_licence_number"
                    maxlength="50" autocomplete="off" placeholder="Leave blank if not applicable">

                <label>Emergency Contact Name <span class="required">*</span></label>
                <input 
                    type="text" 
                    name="emergency_contact_name" 
                    value="<?php echo htmlspecialchars($rider_profile['emergency_contact_name'] ?? ''); ?>"
                    required
                >

                <label>Emergency Contact Phone <span class="required">*</span></label>
                <input 
                    type="tel" 
                    name="emergency_contact_phone" 
                    value="<?php echo htmlspecialchars($rider_profile['emergency_contact_phone'] ?? ''); ?>"
                    required
                >

                <label>Medical Notes / Important Info</label>
                <textarea name="medical_notes" placeholder="Optional"><?php echo htmlspecialchars($rider_profile['medical_notes'] ?? ''); ?></textarea>

                <div class="checkbox-row">
                    <input type="checkbox" name="bw_member_confirmed" value="1" required>
                    <label>I confirm that I am a current member of the club entered above or have been invited to participate in this spin by the organizers.</label>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" name="consent_given" value="1" required>
                    <label>
                        I consent to the participating cycling clubs storing my registration and emergency contact details for this spin.
                        I understand this information will only be available to authorised spin leaders/admins and used for safety or emergency purposes.
                    </label>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" name="remember_me" value="1" checked>
                    <label>
                        Remember my details on this device for 30 days.
                    </label>
                </div>

                <div class="hp-field" aria-hidden="true">
                    <label>Website</label>
                    <input 
                        type="text" 
                        name="website" 
                        tabindex="-1" 
                        autocomplete="off"
                    >
                </div>

                <p class="small-note">
                    Your details are collected for this spin only and are not displayed publicly.
                </p>

                <button type="submit">Submit Registration</button>

            </form>

        <?php endif; ?>

    </div>

</div>

<div id="descriptionModal" class="modal-overlay">
    <div class="modal-box">
        <h2><?php echo htmlspecialchars($spin['title']); ?></h2>

        <?php if (!empty($spin['description'])): ?>
            <p><?php echo nl2br(htmlspecialchars($spin['description'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($spin['strava_url'])): ?>
            <a 
                class="btn secondary" 
                href="<?php echo htmlspecialchars($spin['strava_url']); ?>" 
                target="_blank" 
                rel="noopener noreferrer"
            >
                View Route
            </a>
        <?php endif; ?>

        <button type="button" class="btn modal-close" onclick="closeDescriptionModal()">Close</button>
    </div>
</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
function openDescriptionModal() {
    document.getElementById('descriptionModal').style.display = 'block';
}

function closeDescriptionModal() {
    document.getElementById('descriptionModal').style.display = 'none';
}

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
}
</script>

</body>
</html>