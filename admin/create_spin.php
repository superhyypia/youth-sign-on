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

$leader_user_id = $_SESSION['user_id'] ?? $_SESSION['spin_admin_user_id'] ?? null;

if (!$leader_user_id) {
    die("Error: No logged-in user found.");
}

/*
|--------------------------------------------------------------------------
| Load logged-in leader
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT user_id, name, email, phone, ice_name, ice_phone, role, active
    FROM CIY_spin_users
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $leader_user_id);
$stmt->execute();

$result = $stmt->get_result();
$leader = $result->fetch_assoc();

$stmt->close();

if (!$leader) {
    die("Error: Leader account not found.");
}

if (isset($leader['active']) && (int)$leader['active'] !== 1) {
    die("Error: Your account is inactive.");
}

if (!in_array($leader['role'], ['admin', 'leader'], true)) {
    die("Error: You do not have permission to create spins.");
}

/*
|--------------------------------------------------------------------------
| Defaults
|--------------------------------------------------------------------------
*/

$current_month = date('n');

$default_start_time =
    ($current_month >= 4 && $current_month <= 9)
        ? "09:00"
        : "09:45";

$default_meeting_point = "To be confirmed";

/*
|--------------------------------------------------------------------------
| Route Library prefill
|--------------------------------------------------------------------------
|
| A leader arrives here from:
|
| create_spin.php?route_from_spin=123
|
| We copy ONLY:
|
| - distance_km
| - strava_url
| - description
|
| We deliberately do NOT copy:
|
| - title
| - date
| - time
| - meeting point
| - pace
| - leader
| - max riders
|
*/

$route_from_spin = isset($_GET['route_from_spin'])
    ? (int)$_GET['route_from_spin']
    : 0;

$prefill_distance_km = '';
$prefill_strava_url = '';
$prefill_description = '';

$route_loaded = false;

if ($route_from_spin > 0) {

    $route_stmt = $conn->prepare("
        SELECT
            distance_km,
            strava_url,
            description
        FROM CIY_spins
        WHERE spin_id = ?
        LIMIT 1
    ");

    if ($route_stmt) {

        $route_stmt->bind_param(
            "i",
            $route_from_spin
        );

        $route_stmt->execute();

        $route_result = $route_stmt->get_result();

        if ($route_spin = $route_result->fetch_assoc()) {

            $prefill_distance_km =
                $route_spin['distance_km'] ?? '';

            $prefill_strava_url =
                $route_spin['strava_url'] ?? '';

            $prefill_description =
                $route_spin['description'] ?? '';

            $route_loaded = true;
        }

        $route_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
|
| These defaults also mean that if validation fails,
| the leader does not lose everything they already typed.
|
*/

$form_title = '';
$form_spin_date = '';
$form_start_time = $default_start_time;
$form_meeting_point = $default_meeting_point;
$form_maps_url = '';
$form_distance_km = $prefill_distance_km;
$form_pace_level = '';
$form_description = $prefill_description;
$form_strava_url = $prefill_strava_url;
$form_max_riders = '12';
$form_status = 'draft';

/*
|--------------------------------------------------------------------------
| Form submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $form_title = trim($_POST["title"] ?? "");
    $form_spin_date = trim($_POST["spin_date"] ?? "");
    $form_start_time = trim($_POST["start_time"] ?? "");
    $form_meeting_point = trim($_POST["meeting_point"] ?? "");
    $form_maps_url = trim($_POST["maps_url"] ?? "");
    $form_distance_km = trim($_POST["distance_km"] ?? "");
    $form_pace_level = trim($_POST["pace_level"] ?? "");
    $form_description = trim($_POST["description"] ?? "");
    $form_strava_url = trim($_POST["strava_url"] ?? "");
    $form_max_riders = trim($_POST["max_riders"] ?? "");
    $form_status = trim($_POST["status"] ?? "draft");

    /*
    |--------------------------------------------------------------------------
    | Validate status
    |--------------------------------------------------------------------------
    */

    $allowed_statuses = [
        'draft',
        'final',
        'cancelled'
    ];

    if (!in_array($form_status, $allowed_statuses, true)) {
        $form_status = 'draft';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate pace
    |--------------------------------------------------------------------------
    */

    $allowed_paces = [
        'u14',
        'u16'
    ];

    if (!in_array($form_pace_level, $allowed_paces, true)) {
        $form_pace_level = '';
    }

    if ($form_maps_url !== "") {
        $maps_parts = parse_url($form_maps_url);
        $maps_host = strtolower($maps_parts['host'] ?? '');
        $maps_path = $maps_parts['path'] ?? '';
        $valid_maps_host = in_array($maps_host, ['maps.app.goo.gl', 'goo.gl'], true)
            || $maps_host === 'google.com'
            || str_ends_with($maps_host, '.google.com');
        $valid_maps_path = ($maps_host === 'maps.app.goo.gl')
            || ($maps_host === 'goo.gl' && str_starts_with($maps_path, '/maps'))
            || str_starts_with($maps_path, '/maps');

        if (($maps_parts['scheme'] ?? '') !== 'https' || !$valid_maps_host || !$valid_maps_path) {
            $message = "<div class='alert error'>Please enter a valid Google Maps link beginning with https://.</div>";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Required fields
    |--------------------------------------------------------------------------
    */

    if ($message === "" && (
        $form_title === "" ||
        $form_spin_date === "" ||
        $form_start_time === "" ||
        $form_meeting_point === "" ||
        $form_pace_level === ""
    )) {

        $message = "
            <div class='alert error'>
                Please complete all required fields.
            </div>
        ";

    } elseif ($message === "") {

        /*
        |--------------------------------------------------------------------------
        | Database values
        |--------------------------------------------------------------------------
        */

        $leader_name = $leader['name'];
        $leader_phone = $leader['phone'] ?? '';

        $created_by = (int)$leader_user_id;
        $leader_user_id_db = (int)$leader_user_id;

        $distance_km_db =
            ($form_distance_km === "")
                ? null
                : (float)$form_distance_km;

        $max_riders_db =
            ($form_max_riders === "")
                ? 12
                : (int)$form_max_riders;

        /*
        |--------------------------------------------------------------------------
        | Create spin
        |--------------------------------------------------------------------------
        */

        $sql = "
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
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssssdsssisii",
            $form_title,
            $form_spin_date,
            $form_start_time,
            $form_meeting_point,
            $form_maps_url,
            $leader_name,
            $leader_phone,
            $distance_km_db,
            $form_pace_level,
            $form_description,
            $form_strava_url,
            $max_riders_db,
            $form_status,
            $created_by,
            $leader_user_id_db
        );

        if ($stmt->execute()) {

            $new_spin_id = $conn->insert_id;

            /*
            |--------------------------------------------------------------------------
            | Optional personal Ride Template
            |--------------------------------------------------------------------------
            |
            | This remains exactly the same concept as before.
            | The Route Library does not change template ownership or behaviour.
            |
            */

            if (isset($_POST["save_as_template"])) {

                $template_stmt = $conn->prepare("
                    INSERT INTO CIY_spin_templates
                    (
                        title,
                        meeting_point,
                        maps_url,
                        distance_km,
                        pace_level,
                        description,
                        strava_url,
                        created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($template_stmt) {

                    $template_stmt->bind_param(
                        "sssdsssi",
                        $form_title,
                        $form_meeting_point,
                        $form_maps_url,
                        $distance_km_db,
                        $form_pace_level,
                        $form_description,
                        $form_strava_url,
                        $created_by
                    );

                    $template_stmt->execute();
                    $template_stmt->close();
                }
            }

            $message = "
                <div class='alert success'>
                    Spin created successfully.<br><br>

                    <a
                        class='message-link'
                        href='riders.php?spin_id=" . (int)$new_spin_id . "'
                    >
                        View Rider List
                    </a>
                </div>
            ";

            /*
            |--------------------------------------------------------------------------
            | Clear form after successful creation
            |--------------------------------------------------------------------------
            */

            $form_title = '';
            $form_spin_date = '';
            $form_start_time = $default_start_time;
            $form_meeting_point = $default_meeting_point;
            $form_maps_url = '';
            $form_distance_km = '';
            $form_pace_level = '';
            $form_description = '';
            $form_strava_url = '';
            $form_max_riders = '12';
            $form_status = 'draft';

            $route_loaded = false;

        } else {

            $message = "
                <div class='alert error'>
                    Error creating spin:
                    " . htmlspecialchars($stmt->error) . "
                </div>
            ";
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Escape helper
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Create Spin</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<link
    rel="stylesheet"
    href="../assets/css/style.css?v=102"
>

<link
    rel="manifest"
    href="/clubspins/manifest.json"
>

<meta
    name="theme-color"
    content="#00CFA9"
>

<link
    rel="apple-touch-icon"
    href="/clubspins/assets/images/icon-192.png"
>

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

.top-link{
    display:inline-block;
    margin-bottom:16px;
    color:#00CFA9;
    font-weight:800;
    text-decoration:none;
}

.card h2{
    margin-top:0;
    color:#164B38;
    font-size:26px;
}

label{
    display:block;
    font-weight:800;
    margin-top:16px;
    margin-bottom:6px;
    color:#111827;
}

input,
textarea,
select{
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #cbd5e1;
    font-size:16px;
    box-sizing:border-box;
    background:#fff;
    font-family:inherit;
}

textarea{
    min-height:120px;
    resize:vertical;
}

input:focus,
textarea:focus,
select:focus{
    outline:none;
    border-color:#00CFA9;
    box-shadow:0 0 0 3px rgba(106,44,130,.10);
}

.readonly-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:16px;
    border-radius:12px;
    margin-bottom:20px;
    line-height:1.7;
}

.alert{
    padding:14px;
    border-radius:12px;
    margin-bottom:20px;
    font-weight:800;
    line-height:1.5;
}

.success{
    background:#dcfce7;
    color:#166534;
    border:1px solid #86efac;
}

.error{
    background:#fee2e2;
    color:#991b1b;
    border:1px solid #fecaca;
}

.route-loaded{
    background:#D9F7EE;
    color:#5b216e;
    border:1px solid #d8b4e2;
}

.small-note{
    margin-top:8px;
    color:#64748b;
    font-size:14px;
    line-height:1.4;
}

.message-link{
    display:inline-block;
    color:#166534;
    text-decoration:underline;
    font-weight:900;
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
    font-family:inherit;
}

button:hover{
    background:#164B38;
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

.checkbox-label{
    display:flex;
    gap:10px;
    align-items:flex-start;
    margin-top:20px;
    font-weight:700;
    line-height:1.4;
}

.checkbox-label input{
    width:auto;
    margin-top:4px;
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

    <h1>
        Create Spin
    </h1>

    <p>
        ClubSpin Register
    </p>

</header>

<div class="page">

    <a
        class="top-link"
        href="index.php"
    >
        ← Back to Admin Home
    </a>

    <div class="card">

        <h2>
            Create New Spin
        </h2>

        <?php echo $message; ?>

        <?php if ($route_loaded): ?>

            <div class="alert route-loaded">

                <strong>Route loaded from the Route Library.</strong>

                <br>

                Distance, route link and description have been filled in.

                <br>

                Give the spin a title and complete the remaining details.

            </div>

        <?php endif; ?>

        <div class="readonly-box">

            <strong>
                Spin Leader
            </strong>

            <br>

            <?php echo h($leader['name']); ?>

            <br>

            <?php if (!empty($leader['phone'])): ?>

                Phone:
                <?php echo h($leader['phone']); ?>

                <br>

            <?php endif; ?>

            <?php if (!empty($leader['ice_name'])): ?>

                ICE:
                <?php echo h($leader['ice_name']); ?>

                <?php if (!empty($leader['ice_phone'])): ?>

                    -
                    <?php echo h($leader['ice_phone']); ?>

                <?php endif; ?>

                <br>

            <?php endif; ?>

        </div>

        <form method="POST">

            <label>
                Spin Title *
            </label>

            <input
                type="text"
                name="title"
                value="<?php echo h($form_title); ?>"
                required
            >

            <label>
                Spin Date *
            </label>

            <input
                type="date"
                name="spin_date"
                value="<?php echo h($form_spin_date); ?>"
                required
            >

            <label>
                Start Time *
            </label>

            <input
                type="time"
                name="start_time"
                value="<?php echo h($form_start_time); ?>"
                required
            >

            <label>
                Meeting Point *
            </label>

            <input
                type="text"
                name="meeting_point"
                value="<?php echo h($form_meeting_point); ?>"
                required
            >

            <label>
                Google Maps Link
            </label>

            <input
                type="url"
                name="maps_url"
                value="<?php echo h($form_maps_url); ?>"
                placeholder="https://maps.app.goo.gl/..."
            >
            <small>Optional. Open the meeting point in Google Maps, tap Share, then paste the link here.</small>

            <label>
                Distance (km)
            </label>

            <input
                type="number"
                step="0.1"
                name="distance_km"
                value="<?php echo h($form_distance_km); ?>"
                placeholder="e.g. 85"
            >

            <label>
                Age Group / Expected Pace *
            </label>

            <select
                name="pace_level"
                required
            >

                <option
                    value=""
                    <?php echo $form_pace_level === '' ? 'selected' : ''; ?>
                >
                    Select age group
                </option>

                <option
                    value="u14"
                    <?php echo $form_pace_level === 'u14' ? 'selected' : ''; ?>
                >
                    Under 14 — 23–25 km/h
                </option>

                <option
                    value="u16"
                    <?php echo $form_pace_level === 'u16' ? 'selected' : ''; ?>
                >
                    Under 16 — 25–28 km/h
                </option>

            </select>

            <label>
                Route Link
            </label>

            <input
                type="url"
                name="strava_url"
                value="<?php echo h($form_strava_url); ?>"
                placeholder="https://..."
            >

            <div class="small-note">
                Strava, Ride with GPS, Garmin or another route link.
            </div>

            <label>
                Maximum Riders
            </label>

            <input
                type="number"
                name="max_riders"
                min="1"
                value="<?php echo h($form_max_riders); ?>"
            >

            <label>
                Description
            </label>

            <textarea
                name="description"
                placeholder="Route notes, coffee stop, weather notes etc."
            ><?php echo h($form_description); ?></textarea>

            <label>
                Status
            </label>

            <select name="status">

                <option
                    value="draft"
                    <?php echo $form_status === 'draft' ? 'selected' : ''; ?>
                >
                    Draft
                </option>

                <option
                    value="final"
                    <?php echo $form_status === 'final' ? 'selected' : ''; ?>
                >
                    Final
                </option>

                <option
                    value="cancelled"
                    <?php echo $form_status === 'cancelled' ? 'selected' : ''; ?>
                >
                    Cancelled
                </option>

            </select>

            <div class="status-help">

                <strong>Draft</strong>
                is hidden from riders.

                <strong>Final</strong>
                is visible and open for registration.

                <strong>Cancelled</strong>
                remains visible but registration is closed.

            </div>

            <label class="checkbox-label">

                <input
                    type="checkbox"
                    name="save_as_template"
                    value="1"
                >

                Save this ride as a reusable template

            </label>

            <button type="submit">
                Create Spin
            </button>

        </form>

    </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>

if ("serviceWorker" in navigator) {

    navigator.serviceWorker.register(
        "/clubspins/service-worker.js"
    );

}

</script>

</body>
</html>