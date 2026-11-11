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

/*
|--------------------------------------------------------------------------
| Only allow POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Honeypot Anti-Spam Check
|--------------------------------------------------------------------------
| Real users will not see/fill this field.
|--------------------------------------------------------------------------
*/

$honeypot = trim($_POST['website'] ?? '');

if ($honeypot !== '') {
    header("Location: index.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Collect Form Data
|--------------------------------------------------------------------------
*/

$spin_id = isset($_POST['spin_id']) ? (int)$_POST['spin_id'] : 0;

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$email = trim($_POST['email'] ?? '');
$licence_number = trim($_POST['licence_number'] ?? '');
$emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
$emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
$medical_notes = trim($_POST['medical_notes'] ?? '');

$bw_member_confirmed = isset($_POST['bw_member_confirmed']) ? 1 : 0;
$consent_given = isset($_POST['consent_given']) ? 1 : 0;
$remember_me = isset($_POST['remember_me']) ? 1 : 0;

$email = strtolower($email);

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($spin_id <= 0) {
    die("Invalid spin.");
}

if (
    $first_name === '' ||
    $last_name === '' ||
    $mobile === '' ||
    $email === '' ||
    $licence_number === '' ||
    $emergency_contact_name === '' ||
    $emergency_contact_phone === ''
) {
    die("Please complete all required fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Please enter a valid email address.");
}

// Validate against the current active club list (or the permanent Other option).
$club_valid = ($licence_number === 'Other');
if (!$club_valid) {
    $club_stmt = $conn->prepare("SELECT 1 FROM CIY_clubs WHERE club_name = ? AND is_active = 1 LIMIT 1");
    if (!$club_stmt) { error_log('CIY_clubs validation failed: ' . $conn->error); die('Club registration is temporarily unavailable.'); }
    $club_stmt->bind_param('s', $licence_number);
    $club_stmt->execute();
    $club_valid = $club_stmt->get_result()->num_rows > 0;
    $club_stmt->close();
}
if (!$club_valid) { die('Please select a valid active club.'); }

if ($bw_member_confirmed !== 1 || $consent_given !== 1) {
    die("You must confirm membership and consent before registering.");
}

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
        pace_level,
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
| Only allow registration for final spins
|--------------------------------------------------------------------------
*/

if ($spin['status'] !== 'final') {
    die("This spin is not open for registration.");
}

/*
|--------------------------------------------------------------------------
| Duplicate Registration Check
|--------------------------------------------------------------------------
| Rule: same spin + same email = already registered.
|--------------------------------------------------------------------------
*/

$duplicate_stmt = $conn->prepare("
    SELECT registration_id
    FROM CIY_spin_registrations
    WHERE spin_id = ?
      AND LOWER(email) = LOWER(?)
    LIMIT 1
");

if (!$duplicate_stmt) {
    die("Prepare failed: " . $conn->error);
}

$duplicate_stmt->bind_param(
    "is",
    $spin_id,
    $email
);

$duplicate_stmt->execute();

$duplicate_result = $duplicate_stmt->get_result();

if ($duplicate_result->num_rows > 0) {
    $duplicate_stmt->close();

    header("Location: register.php?spin_id=" . (int)$spin_id . "&already_registered=1");
    exit;
}

$duplicate_stmt->close();

/*
|--------------------------------------------------------------------------
| Insert Registration
|--------------------------------------------------------------------------
*/

$insert_stmt = $conn->prepare("
    INSERT INTO CIY_spin_registrations
    (
        spin_id,
        first_name,
        last_name,
        mobile,
        email,
        licence_number,
        emergency_contact_name,
        emergency_contact_phone,
        medical_notes,
        bw_member_confirmed,
        consent_given,
        registered_at
    )
    VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

if (!$insert_stmt) {
    die("Prepare failed: " . $conn->error);
}

$insert_stmt->bind_param(
    "issssssssii",
    $spin_id,
    $first_name,
    $last_name,
    $mobile,
    $email,
    $licence_number,
    $emergency_contact_name,
    $emergency_contact_phone,
    $medical_notes,
    $bw_member_confirmed,
    $consent_given
);

if (!$insert_stmt->execute()) {
    die("Registration failed: " . htmlspecialchars($insert_stmt->error));
}

$insert_stmt->close();

/*
|--------------------------------------------------------------------------
| Remember Rider Details for 30 Days
|--------------------------------------------------------------------------
*/

if ($remember_me === 1) {

    $rider_profile = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'mobile' => $mobile,
        'email' => $email,
        'licence_number' => $licence_number,
        'emergency_contact_name' => $emergency_contact_name,
        'emergency_contact_phone' => $emergency_contact_phone,
        'medical_notes' => $medical_notes
    ];

    setcookie(
        'clubspin_rider',
        json_encode($rider_profile),
        [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/clubspins/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax'
        ]
    );

} else {

    setcookie(
        'clubspin_rider',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/clubspins/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax'
        ]
    );
}

$conn->close();

$pace_labels = [
    'u14' => 'Under 14 — 23–25 km/h',
    'u16' => 'Under 16 — 25–28 km/h'
];

$pace_label = $pace_labels[$spin['pace_level']] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Registration Confirmed</title>
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

.card{
    background:#fff;
    border-radius:18px;
    padding:24px;
    margin-bottom:18px;
    border-top:5px solid #16a34a;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
}

.success-icon{
    width:56px;
    height:56px;
    border-radius:50%;
    background:#dcfce7;
    color:#166534;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:30px;
    font-weight:900;
    margin-bottom:16px;
}

h2{
    margin-top:0;
    color:#164B38;
}

.meta{
    margin:9px 0;
    line-height:1.5;
}

.meta strong{
    color:#111827;
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
}

.whatsapp-note{
    margin-top:18px;
    padding:14px;
    border-radius:12px;
    background:#f8fafc;
    color:#475569;
    border:1px solid #e2e8f0;
    line-height:1.5;
}

.btn{
    display:block;
    width:100%;
    margin-top:18px;
    padding:14px 16px;
    border:none;
    border-radius:999px;
    background:#00CFA9;
    color:#fff;
    font-size:16px;
    font-weight:800;
    text-align:center;
    text-decoration:none;
}

.btn.secondary{
    background:#E8F8F3;
    color:#1e3a8a;
    border:1px solid #00A98F;
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
    <h1>Registration Confirmed</h1>
    <p>ClubSpin Register</p>
</header>

<div class="page">

    <div class="card">

        <div class="success-icon">✓</div>

        <h2>
            You are registered
        </h2>

        <div class="meta">
            <strong>Rider:</strong>
            <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>
        </div>

        <div class="meta">
            <strong>Spin:</strong>
            <?php echo htmlspecialchars($spin['title']); ?>
        </div>

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
                <?php echo htmlspecialchars($spin['meeting_point']); ?>
                <?php if (!empty($spin['maps_url'])): ?>
                    <a class="maps-pill" href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open meeting point in Google Maps">📍 Maps</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($spin['leader_name'])): ?>
            <div class="meta">
                <strong>Leader:</strong>
                <?php echo htmlspecialchars($spin['leader_name']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pace_label)): ?>
            <div class="meta">
                <strong>Age Group / Expected Pace:</strong>
                <?php echo htmlspecialchars($pace_label); ?>
            </div>
        <?php endif; ?>

        <div class="notice">
            Your registration has been saved. Your emergency contact details will be available only to authorised spin leaders/admins for safety purposes.
        </div>

        <?php if (!empty($pace_label)): ?>
            <div class="whatsapp-note">
                Keep an eye out on the
                <strong><?php echo htmlspecialchars($pace_label); ?></strong>
                WhatsApp channel for potential changes or cancellations.
            </div>
        <?php endif; ?>

        <?php if ($remember_me === 1): ?>
            <div class="whatsapp-note">
                Your details will be remembered on this device for 30 days.
            </div>
        <?php endif; ?>

        <a class="btn" href="index.php">
            Back to Upcoming Spins
        </a>

        <a class="btn secondary" href="cancel_registration.php?spin_id=<?php echo (int)$spin_id; ?>">
            Cancel Registration
        </a>

    </div>

</div>

<?php include_once __DIR__ . '/includes/footer.php'; ?>

<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
}
</script>

</body>
</html>