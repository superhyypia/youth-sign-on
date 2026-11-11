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
$current_name = $_SESSION['user_name'] ?? $_SESSION['spin_admin_name'] ?? 'Admin';

$is_admin = ($current_role === 'admin');
$today = date('Y-m-d');

$spins = [];

$pace_labels = [
    'u14' => [
        'label' => 'Under 14 — 23–25 km/h',
        'class' => 'u14',
        'short' => 'Under 14'
    ],
    'u16' => [
        'label' => 'Under 16 — 25–28 km/h',
        'class' => 'u16',
        'short' => 'Under 16'
    ]
];

if ($is_admin) {

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
            s.status,
            s.pace_level,
            s.leader_user_id,
            COALESCE(rc.rider_count, 0) AS rider_count
        FROM CIY_spins s
        LEFT JOIN (
            SELECT spin_id, COUNT(*) AS rider_count
            FROM CIY_spin_registrations
            GROUP BY spin_id
        ) rc ON s.spin_id = rc.spin_id
        ORDER BY s.spin_date ASC, s.start_time ASC, s.title ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        die("Query failed: " . $conn->error);
    }

} else {

    $stmt = $conn->prepare("
        SELECT 
            s.spin_id,
            s.title,
            s.spin_date,
            s.start_time,
            s.meeting_point,
            s.maps_url,
            s.leader_name,
            s.distance_km,
            s.status,
            s.pace_level,
            s.leader_user_id,
            COALESCE(rc.rider_count, 0) AS rider_count
        FROM CIY_spins s
        LEFT JOIN (
            SELECT spin_id, COUNT(*) AS rider_count
            FROM CIY_spin_registrations
            GROUP BY spin_id
        ) rc ON s.spin_id = rc.spin_id
        WHERE s.leader_user_id = ?
        ORDER BY s.spin_date DESC, s.start_time ASC, s.title ASC
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $spins[] = $row;
}

$upcoming_spins_by_pace = [
    'u14' => [],
    'u16' => []
];

$past_spins = [];

foreach ($spins as $spin) {
    if ($is_admin && $spin['spin_date'] < $today) {
        $past_spins[] = $spin;
        continue;
    }

    if ($is_admin) {
        $pace = trim($spin['pace_level'] ?? '');

        if (isset($upcoming_spins_by_pace[$pace])) {
            $upcoming_spins_by_pace[$pace][] = $spin;
        }
    }
}

usort($past_spins, function ($a, $b) {
    return strcmp($b['spin_date'] . $b['start_time'], $a['spin_date'] . $a['start_time']);
});

function build_whatsapp_share_message($spin, $pace_labels)
{
    $pace = trim($spin['pace_level'] ?? '');

    $share_message = "Youth Club Spin\n\n";
    $share_message .= $spin['title'] . "\n";
    $share_message .= date("D j M Y", strtotime($spin['spin_date']));
    $share_message .= " at ";
    $share_message .= date("H:i", strtotime($spin['start_time'])) . "\n";

    if (!empty($spin['meeting_point'])) {
        $share_message .= "Meet: " . $spin['meeting_point'] . "\n";
    }
    if (!empty($spin['maps_url'])) {
        $share_message .= "Map: " . $spin['maps_url'] . "\n";
    }

    if (!empty($spin['leader_name'])) {
        $share_message .= "Leader: " . $spin['leader_name'] . "\n";
    }

    if (!empty($spin['distance_km'])) {
        $share_message .= "Distance: " . rtrim(rtrim($spin['distance_km'], '0'), '.') . " km\n";
    }

    if (!empty($pace) && isset($pace_labels[$pace])) {
        $share_message .= "Age Group / Expected Pace: " . $pace_labels[$pace]['label'] . "\n";
    }

    $share_message .= "\nRegister here:\n";

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $share_message .= $protocol . $_SERVER['HTTP_HOST'] . "/clubspins/register.php?spin_id=" . (int) $spin['spin_id'];

    return rawurlencode($share_message);
}

function normalise_status($status)
{
    $status = strtolower(trim($status ?? 'draft'));
    $allowed_statuses = ['draft', 'final', 'cancelled'];

    if (!in_array($status, $allowed_statuses, true)) {
        return 'draft';
    }

    return $status;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>ClubSpin Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../assets/css/style.css?v=95">
    <link rel="manifest" href="/clubspins/manifest.json">
    <meta name="theme-color" content="#00CFA9">
    <link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

    <style>
        .app-header {
            position: relative;
        }

        .header-actions {
            position: absolute;
            top: 18px;
            right: 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }

        .header-actions a {
            width: 96px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: #ffffff22;
            color: #fff;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            backdrop-filter: blur(6px);
            box-sizing: border-box;
        }

        .header-actions a:hover {
            background: #ffffff33;
        }

        .header-logo {
            width: 110px;
            height: auto;
            margin-bottom: 16px;
        }

        .page {
            max-width: 860px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }

        .top-actions {
            display: grid;
            gap: 12px;
            margin-bottom: 24px;
        }

        .create-btn {
            display: block;
            text-align: center;
            background: #00CFA9;
            color: #fff;
            text-decoration: none;
            padding: 16px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 16px;
        }

        .create-btn:hover {
            background: #164B38;
        }

        .secondary-admin-btn {
            background: #eef2ff;
            color: #4338ca;
        }

        .secondary-admin-btn:hover {
            background: #D5F3EB;
        }

        .danger-link {
            display: block;
            text-align: center;
            margin-top: 8px;
            color: #991b1b;
            font-weight: 800;
            text-decoration: none;
        }

        .section-title {
            margin: 30px 0 14px;
            font-size: 28px;
            color: #111827;
            text-align: center;
        }

        .role-note {
            max-width: 560px;
            margin: 0 auto 22px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 15px;
            line-height: 1.5;
            text-align: center;
        }

        .spin-list {
            display: grid;
            gap: 16px;
        }

        .spin-card {
            background: #fff;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(63, 24, 83, .08);
            border-top: 5px solid #00CFA9;
        }

        .spin-card h2 {
            margin: 0 0 12px;
            font-size: 24px;
            color: #111827;
        }

        .badge-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-final {
            background: #dcfce7;
            color: #166534;
        }

        .status-draft {
            background: #ffedd5;
            color: #c2410c;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .pace-box {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .pace-blue {
            background: #D5F3EB;
            color: #1e3a8a;
        }

        .pace-green {
            background: #dcfce7;
            color: #166534;
        }

        .pace-yellow {
            background: #fef9c3;
            color: #854d0e;
        }

        .pace-orange {
            background: #ffedd5;
            color: #9a3412;
        }

        .pace-red {
            background: #fee2e2;
            color: #991b1b;
        }

        .pace-u14 { background:#D9F7EE; color:#164B38; }
        .pace-u16 { background:#D8F7F1; color:#0F766E; }

        .pace-section {
            margin-bottom: 36px;
        }

        .pace-section-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 24px;
            margin: 22px 0 16px;
            color: #111827;
        }

        .pace-dot {
            width: 16px;
            height: 16px;
            border-radius: 999px;
            display: inline-block;
        }

        .pace-dot.blue {
            background: #00A98F;
        }

        .pace-dot.green {
            background: #16a34a;
        }

        .pace-dot.yellow {
            background: #eab308;
        }

        .pace-dot.orange {
            background: #f97316;
        }

        .pace-dot.u14 { background:#00CFA9; }
        .pace-dot.u16 { background:#164B38; }

        .pace-dot.red {
            background: #dc2626;
        }

        .meta {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
        }

        .meta strong {
            color: #111827;
        }

        .rider-count {
            margin-top: 10px;
            font-weight: 800;
            color: #00CFA9;
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }

        .card-actions a,
        .card-actions button {
            width: 100%;
            text-align: center;
            text-decoration: none;
            padding: 13px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 15px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1.2;
            min-height: 48px;
        }

        .delete-form {
            margin: 0;
        }

        .view-btn {
            background: #eef2ff;
            color: #4338ca;
        }

        .edit-btn {
            background: #D9F7EE;
            color: #00CFA9;
        }

        .reassign-btn {
            background: #ecfeff;
            color: #0e7490;
        }

        .share-btn {
            background: #dcfce7;
            color: #166534;
        }

        .qr-btn {
            background: #fef9c3;
            color: #854d0e;
        }

        .delete-btn {
            background: #fee2e2;
            color: #991b1b;
        }

        .admin-user {
            margin-top: 10px;
            color: #D9F7EE;
            font-size: 14px;
            font-weight: 500;
        }

        .admin-user strong {
            color: #ffffff;
        }

        .empty-state {
            background: #fff;
            padding: 24px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(63, 24, 83, .08);
        }

        .past-spins {
            background: #ffffff;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(63, 24, 83, .08);
            margin-top: 18px;
        }

        .past-spin-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .past-spin-list li {
            border-bottom: 1px solid #e5e7eb;
        }

        .past-spin-list li:last-child {
            border-bottom: none;
        }

        .past-spin-list a {
            display: block;
            padding: 14px 4px;
            text-decoration: none;
            color: #334155;
            font-weight: 700;
        }

        .past-spin-list a:hover {
            color: #00CFA9;
        }

        .past-spin-date {
            display: block;
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            margin-top: 3px;
        }

        @media(max-width:760px) {
            .card-actions {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media(max-width:640px) {

            .app-header {
                padding-top: 28px;
            }

            .header-logo {
                width: 96px;
                height: auto;
                margin-bottom: 14px;
            }

            .header-actions {
                position: static;
                display: flex;
                flex-direction: row;
                justify-content: center;
                align-items: center;
                gap: 10px;
                margin: 0 auto 18px;
            }

            .header-actions a {
                width: 96px;
                height: 36px;
                font-size: 13px;
            }

            .section-title {
                font-size: 28px;
                margin-top: 28px;
                text-align: center;
            }

            .role-note {
                text-align: center;
                font-size: 15px;
                margin-left: auto;
                margin-right: auto;
            }

            .card-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <header class="app-header">

        <div class="header-actions">
            <a href="logout.php">
                Logout
            </a>

            <a href="change_password.php">
                Password
            </a>
        </div>

        <img class="header-logo" src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos">

        <h1>ClubSpin Admin</h1>

        <div class="admin-user">
            <strong>
                <?php echo htmlspecialchars($current_name); ?>
            </strong>
            - Role:
            <?php echo ucfirst(htmlspecialchars($current_role)); ?>
        </div>

        <p>
            Manage spins and rider registrations
        </p>

    </header>

    <div class="page">

        <div class="top-actions">

            <a class="create-btn" href="create_spin.php">
                + Create New Spin
            </a>

            <a class="create-btn secondary-admin-btn" href="templates.php">
                Ride Templates
            </a>
            
            <a class="create-btn secondary-admin-btn" href="routes.php">
                Route Library
            </a>

            <?php if ($is_admin): ?>

                <a class="create-btn secondary-admin-btn" href="statistics.php">
                    Club Statistics
                </a>

                <a class="create-btn secondary-admin-btn" href="manage_clubs.php">
                    Manage Clubs
                </a>

                <a class="create-btn secondary-admin-btn" href="admin_manage_users.php">
                    Manage Users
                </a>

                <a class="danger-link" href="danger_zone.php">
                    Danger Zone
                </a>

            <?php endif; ?>

        </div>

        <?php if ($is_admin): ?>

            <h2 class="section-title">
                Upcoming Spins
            </h2>

            <div class="role-note">
                You are viewing all future club spins, grouped by age group / expected pace.
            </div>

            <?php
            $has_upcoming = false;

            foreach ($upcoming_spins_by_pace as $pace_key => $pace_spins) {
                if (!empty($pace_spins)) {
                    $has_upcoming = true;
                    break;
                }
            }
            ?>

            <?php if ($has_upcoming): ?>

                <?php foreach ($upcoming_spins_by_pace as $pace_key => $pace_spins): ?>

                    <?php if (empty($pace_spins)) continue; ?>

                    <?php
                    $pace_class = $pace_labels[$pace_key]['class'];
                    $pace_short = $pace_labels[$pace_key]['short'];
                    $pace_full = $pace_labels[$pace_key]['label'];
                    ?>

                    <section class="pace-section">

                        <h3 class="pace-section-title">
                            <span class="pace-dot <?php echo htmlspecialchars($pace_class); ?>"></span>
                            <?php echo htmlspecialchars($pace_short); ?> Spins
                        </h3>

                        <div class="spin-list">

                            <?php foreach ($pace_spins as $spin): ?>

                                <?php
                                $pace = trim($spin['pace_level'] ?? '');
                                $status = normalise_status($spin['status'] ?? 'draft');
                                $whatsapp_share = build_whatsapp_share_message($spin, $pace_labels);
                                ?>

                                <article class="spin-card">

                                    <h2>
                                        <?php echo htmlspecialchars($spin['title']); ?>
                                    </h2>

                                    <div class="badge-row">

                                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                            <?php echo strtoupper(htmlspecialchars($status)); ?>
                                        </span>

                                        <span class="pace-box pace-<?php echo htmlspecialchars($pace_class); ?>">
                                            <?php echo htmlspecialchars($pace_full); ?>
                                        </span>

                                    </div>

                                    <div class="meta">

                                        <div>
                                            <strong>Date:</strong>
                                            <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
                                        </div>

                                        <div>
                                            <strong>Time:</strong>
                                            <?php echo date("H:i", strtotime($spin['start_time'])); ?>
                                        </div>

                                        <?php if (!empty($spin['meeting_point'])): ?>
                                            <div>
                                                <strong>Meet:</strong>
                                                <?php echo htmlspecialchars($spin['meeting_point']); ?><?php if (!empty($spin['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div>
                                            <strong>Leader:</strong>
                                            <?php echo htmlspecialchars($spin['leader_name']); ?>
                                        </div>

                                        <?php if (!empty($spin['distance_km'])): ?>
                                            <div>
                                                <strong>Distance:</strong>
                                                <?php echo htmlspecialchars(rtrim(rtrim($spin['distance_km'], '0'), '.')); ?> km
                                            </div>
                                        <?php endif; ?>

                                    </div>

                                    <div class="rider-count">
                                        <?php echo (int) $spin['rider_count']; ?> Riders Registered
                                    </div>

                                    <div class="card-actions">

                                        <a class="view-btn" href="riders.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                            View Riders
                                        </a>

                                        <a class="edit-btn" href="edit_spin.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                            Edit Spin
                                        </a>

                                        <a class="reassign-btn" href="reassign_spin.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                            Reassign
                                        </a>

                                        <a class="share-btn" href="https://wa.me/?text=<?php echo $whatsapp_share; ?>" target="_blank" rel="noopener noreferrer">
                                            Share
                                        </a>

                                        <a class="qr-btn" href="spin_qr.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                            QR Code
                                        </a>

                                        <form method="POST" action="delete_spin.php" class="delete-form"
                                            onsubmit="return confirm('Are you sure you want to delete this spin and all registrations? This cannot be undone.');">
                                            <input type="hidden" name="spin_id" value="<?php echo (int) $spin['spin_id']; ?>">

                                            <button type="submit" class="delete-btn">
                                                Delete Spin
                                            </button>
                                        </form>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    </section>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <h3>No upcoming spins</h3>
                    <p>Create a new spin to add it to the upcoming list.</p>
                </div>

            <?php endif; ?>

            <h2 class="section-title">
                Past Spins
            </h2>

            <div class="role-note">
                Past spins are shown as quick links to their rider lists.
            </div>

            <?php if (!empty($past_spins)): ?>

                <div class="past-spins">

                    <ul class="past-spin-list">

                        <?php foreach ($past_spins as $spin): ?>

                            <li>
                                <a href="riders.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                    <?php echo htmlspecialchars($spin['title']); ?>

                                    <span class="past-spin-date">
                                        <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
                                        at
                                        <?php echo date("H:i", strtotime($spin['start_time'])); ?>
                                        -
                                        <?php echo htmlspecialchars($spin['leader_name']); ?>
                                        -
                                        <?php echo (int) $spin['rider_count']; ?> riders
                                    </span>
                                </a>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>No past spins</h3>
                    <p>Past spins will appear here once their date has passed.</p>
                </div>

            <?php endif; ?>

        <?php else: ?>

            <h2 class="section-title">
                Your Spins
            </h2>

            <div class="role-note">
                You are viewing only spins created by you or assigned to you as a spin leader.
            </div>

            <?php if (!empty($spins)): ?>

                <div class="spin-list">

                    <?php foreach ($spins as $spin): ?>

                        <?php
                        $pace = trim($spin['pace_level'] ?? '');
                        $status = normalise_status($spin['status'] ?? 'draft');
                        $whatsapp_share = build_whatsapp_share_message($spin, $pace_labels);
                        ?>

                        <article class="spin-card">

                            <h2>
                                <?php echo htmlspecialchars($spin['title']); ?>
                            </h2>

                            <div class="badge-row">

                                <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo strtoupper(htmlspecialchars($status)); ?>
                                </span>

                                <?php if (!empty($pace) && isset($pace_labels[$pace])): ?>

                                    <span class="pace-box pace-<?php echo htmlspecialchars($pace_labels[$pace]['class']); ?>">
                                        <?php echo htmlspecialchars($pace_labels[$pace]['label']); ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <div class="meta">

                                <div>
                                    <strong>Date:</strong>
                                    <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
                                </div>

                                <div>
                                    <strong>Time:</strong>
                                    <?php echo date("H:i", strtotime($spin['start_time'])); ?>
                                </div>

                                <?php if (!empty($spin['meeting_point'])): ?>
                                    <div>
                                        <strong>Meet:</strong>
                                        <?php echo htmlspecialchars($spin['meeting_point']); ?><?php if (!empty($spin['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div>
                                    <strong>Leader:</strong>
                                    <?php echo htmlspecialchars($spin['leader_name']); ?>
                                </div>

                                <?php if (!empty($spin['distance_km'])): ?>
                                    <div>
                                        <strong>Distance:</strong>
                                        <?php echo htmlspecialchars(rtrim(rtrim($spin['distance_km'], '0'), '.')); ?> km
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div class="rider-count">
                                <?php echo (int) $spin['rider_count']; ?> Riders Registered
                            </div>

                            <div class="card-actions">

                                <a class="view-btn" href="riders.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                    View Riders
                                </a>

                                <a class="edit-btn" href="edit_spin.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                    Edit Spin
                                </a>

                                <a class="share-btn" href="https://wa.me/?text=<?php echo $whatsapp_share; ?>" target="_blank" rel="noopener noreferrer">
                                    Share
                                </a>

                                <a class="qr-btn" href="spin_qr.php?spin_id=<?php echo (int) $spin['spin_id']; ?>">
                                    QR Code
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>No spins available</h3>
                    <p>No spins are currently assigned to you.</p>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
        }
    </script>

</body>

</html>