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
        pace_level,
        max_riders,
        leader_user_id
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
| Admins can view all rider lists.
| Leaders can only view rider lists for spins assigned to them.
|--------------------------------------------------------------------------
*/

if (!$is_admin && (int)$spin['leader_user_id'] !== (int)$current_user_id) {
    die("Access denied. You can only view riders for your own spins.");
}

/*
|--------------------------------------------------------------------------
| Load Riders
|--------------------------------------------------------------------------
*/

$riders_stmt = $conn->prepare("
    SELECT
        registration_id,
        first_name,
        last_name,
        mobile,
        email,
        licence_number,
        emergency_contact_name,
        emergency_contact_phone,
        medical_notes,
        registered_at
    FROM CIY_spin_registrations
    WHERE spin_id = ?
    ORDER BY registered_at ASC
");

if (!$riders_stmt) {
    die("Prepare failed: " . $conn->error);
}

$riders_stmt->bind_param("i", $spin_id);
$riders_stmt->execute();

$riders_result = $riders_stmt->get_result();

$total_riders = $riders_result->num_rows;
$riders = $riders_result->fetch_all(MYSQLI_ASSOC);

$age_group_labels = [
    'u14' => 'Under 14',
    'u16' => 'Under 16'
];
$age_group = $age_group_labels[$spin['pace_level']] ?? $spin['pace_level'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Riders</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="manifest" href="/ci-youths/manifest.json">
    <meta name="theme-color" content="#00CFA9">
    <link rel="apple-touch-icon" href="/ci-youths/assets/images/icon-192.png">
    <link rel="stylesheet" href="../assets/css/style.css?v=5">

    <style>

        .page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .nav {
            margin-bottom: 18px;
        }

        .nav a {
            text-decoration: none;
            color: #00CFA9;
            font-weight: 700;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            border-top: 5px solid #00CFA9;
        }

        .logo {
            width: 90px;
            height: auto;
            margin-bottom: 14px;
        }

        .spin-meta {
            margin: 8px 0;
        }

        .count {
            margin-top: 14px;
            padding: 12px;
            border-radius: 10px;
            background: #E8F8F3;
            color: #1e3a8a;
            font-weight: 700;
            border: 1px solid #00A98F;
        }

        .ownership-note {
            margin-top: 14px;
            padding: 12px;
            border-radius: 10px;
            background: #f8fafc;
            color: #475569;
            font-size: 14px;
            line-height: 1.4;
            border: 1px solid #e2e8f0;
        }

        .rider-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-top: 16px;
        }

        .rider-name {
            margin-top: 0;
            margin-bottom: 14px;
            font-size: 20px;
            color: #164B38;
        }

        .row {
            margin: 7px 0;
            line-height: 1.4;
        }

        .medical {
            margin-top: 12px;
            padding: 12px;
            border-radius: 10px;
            background: #fff7ed;
            color: #9a3412;
        }

        .empty {
            text-align: center;
            padding: 28px;
            color: #64748b;
        }

        .action-buttons {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .print-sheet {
            display: none;
        }

        @media print {

            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            .app-header,
            .page,
            footer {
                display: none !important;
            }

            .print-sheet {
                display: block !important;
                font-family: Arial, Helvetica, sans-serif;
                color: #111827;
                font-size: 10pt;
            }

            .print-title {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 2px solid #164B38;
                padding-bottom: 8px;
                margin-bottom: 8px;
            }

            .print-title h1 {
                margin: 0 0 3px;
                font-size: 18pt;
                color: #164B38;
            }

            .print-title .age-group {
                font-size: 13pt;
                font-weight: 700;
            }

            .print-summary {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 5px 16px;
                margin-bottom: 10px;
                font-size: 9.5pt;
            }

            .print-summary div {
                min-width: 0;
            }

            .print-riders {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .print-riders th,
            .print-riders td {
                border: 1px solid #9ca3af;
                padding: 5px 6px;
                text-align: left;
                vertical-align: top;
                overflow-wrap: anywhere;
            }

            .print-riders th {
                background: #eef6f2 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-size: 8.5pt;
            }

            .print-riders td {
                font-size: 8.5pt;
            }

            .print-riders tr {
                break-inside: avoid;
            }

            .col-num { width: 3%; text-align: center !important; }
            .col-present { width: 5%; text-align: center !important; }
            .col-rider { width: 14%; }
            .col-club { width: 15%; }
            .col-mobile { width: 11%; }
            .col-ice { width: 14%; }
            .col-ice-phone { width: 11%; }
            .col-medical { width: 27%; }

            .present-box {
                display: inline-block;
                width: 13px;
                height: 13px;
                border: 1.5px solid #111827;
            }

            .print-confidential {
                margin-top: 9px;
                border-top: 1px solid #9ca3af;
                padding-top: 6px;
                font-size: 8pt;
                font-weight: 700;
            }

            body {
                background: #ffffff;
            }

            .nav,
            .action-buttons,
            .ownership-note {
                display: none;
            }

            body {
                background: #ffffff;
            }

            .card {
                box-shadow: none;
                border: 1px solid #cccccc;
            }

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

    <h1>Registered Riders</h1>

    <p>
        ClubSpin Register
    </p>

</header>

<div class="page">

    <div class="nav">
        <a href="index.php">← Back to Admin Home</a>
    </div>

    <div class="card">

        <h2><?php echo htmlspecialchars($spin['title']); ?></h2>

        <div class="spin-meta">
            <strong>Date:</strong>
            <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Start Time:</strong>
            <?php echo date("H:i", strtotime($spin['start_time'])); ?>
        </div>

        <div class="spin-meta">
            <strong>Meeting Point:</strong>
            <?php echo htmlspecialchars($spin['meeting_point']); ?><?php if (!empty($spin['maps_url'])): ?> · <a href="<?php echo htmlspecialchars($spin['maps_url']); ?>" target="_blank" rel="noopener noreferrer">Map</a><?php endif; ?>
        </div>

        <div class="spin-meta">
            <strong>Leader:</strong>
            <?php echo htmlspecialchars($spin['leader_name']); ?>
        </div>

        <div class="count">
            Registered Riders:
            <?php echo $total_riders; ?>

            <?php if (!empty($spin['max_riders'])): ?>
                / <?php echo (int)$spin['max_riders']; ?>
            <?php endif; ?>
        </div>

        <?php if ($is_admin): ?>
            <div class="ownership-note">
                <strong>Admin view:</strong>
                This spin is assigned to leader user ID
                <?php echo (int)$spin['leader_user_id']; ?>.
            </div>
        <?php endif; ?>

        <div class="action-buttons">

            <a 
                class="btn primary"
                href="javascript:window.print();"
            >
                Print Rider List
            </a>

            <a 
                class="btn secondary"
                href="create_spin.php"
            >
                Create Another Spin
            </a>

        </div>

    </div>

    <div class="card">

        <h2>Riders</h2>

        <?php if ($total_riders > 0): ?>

            <?php foreach ($riders as $rider): ?>

                <div class="rider-card">

                    <h3 class="rider-name">
                        <?php
                        echo htmlspecialchars(
                            $rider['first_name'] . ' ' . $rider['last_name']
                        );
                        ?>
                    </h3>

                    <div class="row">
                        <strong>Mobile:</strong>
                        <?php echo htmlspecialchars($rider['mobile']); ?>
                    </div>

                    <?php if (!empty($rider['email'])): ?>
                        <div class="row">
                            <strong>Email:</strong>
                            <?php echo htmlspecialchars($rider['email']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($rider['licence_number'])): ?>
                        <div class="row">
                            <strong>Club:</strong>
                            <?php echo htmlspecialchars($rider['licence_number']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <strong>Emergency Contact:</strong>
                        <?php echo htmlspecialchars($rider['emergency_contact_name']); ?>
                    </div>

                    <div class="row">
                        <strong>Emergency Phone:</strong>
                        <?php echo htmlspecialchars($rider['emergency_contact_phone']); ?>
                    </div>

                    <?php if (!empty($rider['medical_notes'])): ?>
                        <div class="medical">
                            <strong>Medical Notes:</strong><br>
                            <?php echo nl2br(htmlspecialchars($rider['medical_notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <div class="row" style="margin-top: 14px; color: #64748b;">
                        Registered:
                        <?php
                        echo date(
                            "D j M Y H:i",
                            strtotime($rider['registered_at'])
                        );
                        ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">
                No riders registered yet.
            </div>

        <?php endif; ?>

    </div>

</div>

<section class="print-sheet" aria-hidden="true">
    <div class="print-title">
        <div>
            <h1>Inter-Club Youth Spins — Rider Sheet</h1>
            <div class="age-group"><?php echo htmlspecialchars($age_group); ?></div>
        </div>
        <div><strong>Registered:</strong> <?php echo $total_riders; ?><?php if (!empty($spin['max_riders'])): ?> / <?php echo (int)$spin['max_riders']; ?><?php endif; ?></div>
    </div>

    <div class="print-summary">
        <div><strong>Spin:</strong> <?php echo htmlspecialchars($spin['title']); ?></div>
        <div><strong>Date:</strong> <?php echo date("D j M Y", strtotime($spin['spin_date'])); ?></div>
        <div><strong>Start:</strong> <?php echo date("H:i", strtotime($spin['start_time'])); ?></div>
        <div><strong>Meeting Point:</strong> <?php echo htmlspecialchars($spin['meeting_point']); ?></div>
        <div><strong>Leader:</strong> <?php echo htmlspecialchars($spin['leader_name']); ?></div>
        <div><strong>Leader Mobile:</strong> <?php echo !empty($spin['leader_phone']) ? htmlspecialchars($spin['leader_phone']) : '—'; ?></div>
    </div>

    <?php if ($total_riders > 0): ?>
        <table class="print-riders">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-present">Present</th>
                    <th class="col-rider">Rider</th>
                    <th class="col-club">Club</th>
                    <th class="col-mobile">Mobile</th>
                    <th class="col-ice">Emergency Contact</th>
                    <th class="col-ice-phone">Emergency Phone</th>
                    <th class="col-medical">Medical / Important Information</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riders as $index => $rider): ?>
                    <tr>
                        <td class="col-num"><?php echo $index + 1; ?></td>
                        <td class="col-present"><span class="present-box"></span></td>
                        <td><?php echo htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']); ?></td>
                        <td><?php echo !empty($rider['licence_number']) ? htmlspecialchars($rider['licence_number']) : '—'; ?></td>
                        <td><?php echo htmlspecialchars($rider['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($rider['emergency_contact_name']); ?></td>
                        <td><?php echo htmlspecialchars($rider['emergency_contact_phone']); ?></td>
                        <td><?php echo !empty(trim((string)$rider['medical_notes'])) ? nl2br(htmlspecialchars($rider['medical_notes'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No riders registered.</p>
    <?php endif; ?>

    <div class="print-confidential">
        CONFIDENTIAL — FOR SPIN LEADER USE ONLY. Contains personal and emergency information. Keep secure during the spin and destroy securely afterwards.
    </div>
</section>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
<script>
if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/ci-youths/service-worker.js");
}
</script>

</body>
</html>