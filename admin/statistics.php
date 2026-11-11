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


/*
|--------------------------------------------------------------------------
| Logged-in user
|--------------------------------------------------------------------------
*/

$current_user_id =
    $_SESSION['user_id']
    ?? $_SESSION['spin_admin_user_id']
    ?? 0;

$current_role =
    $_SESSION['user_role']
    ?? $_SESSION['spin_admin_role']
    ?? '';

$current_name =
    $_SESSION['user_name']
    ?? $_SESSION['spin_admin_name']
    ?? 'Admin';


/*
|--------------------------------------------------------------------------
| Admin only
|--------------------------------------------------------------------------
*/

if ($current_role !== 'admin') {
    http_response_code(403);
    die('Access denied.');
}


/*
|--------------------------------------------------------------------------
| Year selector
|--------------------------------------------------------------------------
*/

$current_year = (int) date('Y');

$selected_year = isset($_GET['year'])
    ? (int) $_GET['year']
    : $current_year;

if (
    $selected_year < 2026
    || $selected_year > ($current_year + 1)
) {
    $selected_year = $current_year;
}

$start_date = $selected_year . '-01-01';
$end_date   = ($selected_year + 1) . '-01-01';


/*
|--------------------------------------------------------------------------
| Pace groups
|--------------------------------------------------------------------------
*/

$pace_labels = [
    'u14' => [
        'label' => 'Under 14',
        'full'  => 'Under 14 — 23–25 km/h',
        'class' => 'u14'
    ],
    'u16' => [
        'label' => 'Under 16',
        'full'  => 'Under 16 — 25–28 km/h',
        'class' => 'u16'
    ]
];


/*
|--------------------------------------------------------------------------
| Build one row per spin
|
| We calculate registration count first so joins do not distort
| distance, capacity or spin totals.
|--------------------------------------------------------------------------
*/

$spin_summary_sql = "

    SELECT

        s.spin_id,
        s.spin_date,
        s.pace_level,
        s.leader_name,
        s.distance_km,
        COALESCE(s.max_riders, 0) AS max_riders,

        COUNT(r.registration_id) AS rider_count

    FROM CIY_spins s

    LEFT JOIN CIY_spin_registrations r
        ON r.spin_id = s.spin_id

    WHERE s.spin_date >= ?
      AND s.spin_date < ?
      AND (s.status = 'final' OR (s.status = 'draft' AND s.spin_date < CURDATE()))

    GROUP BY

        s.spin_id,
        s.spin_date,
        s.pace_level,
        s.leader_name,
        s.distance_km,
        s.max_riders

";


/*
|--------------------------------------------------------------------------
| Headline statistics
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

    SELECT

        COUNT(*) AS total_spins,

        COALESCE(
            SUM(x.rider_count),
            0
        ) AS total_signons,

        ROUND(
            AVG(x.rider_count),
            1
        ) AS average_signons,

        ROUND(
            AVG(x.distance_km),
            1
        ) AS average_distance,

        COALESCE(
            SUM(x.max_riders),
            0
        ) AS total_capacity

    FROM (

        $spin_summary_sql

    ) x

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$headline =
    $stmt
    ->get_result()
    ->fetch_assoc();


$total_spins =
    (int) ($headline['total_spins'] ?? 0);

$total_signons =
    (int) ($headline['total_signons'] ?? 0);

$average_signons =
    (float) ($headline['average_signons'] ?? 0);

$average_distance =
    (float) ($headline['average_distance'] ?? 0);

$total_capacity =
    (int) ($headline['total_capacity'] ?? 0);


$overall_capacity_used =
    $total_capacity > 0
        ? round(
            ($total_signons / $total_capacity) * 100,
            1
        )
        : 0;


/*
|--------------------------------------------------------------------------
| Pace statistics
|--------------------------------------------------------------------------
*/

$pace_stats = [];

$stmt = $conn->prepare("

    SELECT

        x.pace_level,

        COUNT(*) AS spin_count,

        COALESCE(
            SUM(x.rider_count),
            0
        ) AS total_signons,

        ROUND(
            AVG(x.rider_count),
            1
        ) AS average_signons,

        ROUND(
            AVG(x.distance_km),
            1
        ) AS average_distance,

        COALESCE(
            SUM(x.max_riders),
            0
        ) AS total_capacity,

        MAX(
            x.rider_count
        ) AS highest_registrations

    FROM (

        $spin_summary_sql

    ) x

    GROUP BY x.pace_level

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $capacity =
        (int) ($row['total_capacity'] ?? 0);

    $signons =
        (int) ($row['total_signons'] ?? 0);

    $row['capacity_used'] =
        $capacity > 0
            ? round(
                ($signons / $capacity) * 100,
                1
            )
            : 0;

    $pace_stats[$row['pace_level']] =
        $row;
}


/*
|--------------------------------------------------------------------------
| Monthly activity
|--------------------------------------------------------------------------
*/

$monthly = [];

for ($month = 1; $month <= 12; $month++) {

    $monthly[$month] = [
        'spins'   => 0,
        'signons' => 0
    ];

}


$stmt = $conn->prepare("

    SELECT

        MONTH(x.spin_date) AS month_number,

        COUNT(*) AS spin_count,

        COALESCE(
            SUM(x.rider_count),
            0
        ) AS signons

    FROM (

        $spin_summary_sql

    ) x

    GROUP BY
        MONTH(x.spin_date)

    ORDER BY
        MONTH(x.spin_date)

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $month =
        (int) $row['month_number'];

    $monthly[$month] = [

        'spins' =>
            (int) $row['spin_count'],

        'signons' =>
            (int) $row['signons']

    ];

}


/*
|--------------------------------------------------------------------------
| Registrations by club
|
| In CI-Youths, licence_number stores the rider's club.
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

    SELECT
        TRIM(r.licence_number) AS club_name,
        COUNT(r.registration_id) AS registration_count

    FROM CIY_spin_registrations r

    INNER JOIN CIY_spins s
        ON s.spin_id = r.spin_id

    WHERE s.spin_date >= ?
      AND s.spin_date < ?
      AND (s.status = 'final' OR (s.status = 'draft' AND s.spin_date < CURDATE()))
      AND r.licence_number IS NOT NULL
      AND TRIM(r.licence_number) <> ''

    GROUP BY TRIM(r.licence_number)

    ORDER BY
        registration_count DESC,
        club_name ASC

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$clubs = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Ride leaders
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

    SELECT

        x.leader_name,

        COUNT(*) AS spins_led,

        COALESCE(
            SUM(x.rider_count),
            0
        ) AS total_signons,

        ROUND(
            AVG(x.rider_count),
            1
        ) AS average_registrations

    FROM (

        $spin_summary_sql

    ) x

    GROUP BY
        x.leader_name

    ORDER BY

        spins_led DESC,
        average_registrations DESC,
        x.leader_name ASC

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$leaders =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Largest spin
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

    SELECT

        x.spin_id,
        x.spin_date,
        x.pace_level,
        x.rider_count

    FROM (

        $spin_summary_sql

    ) x

    ORDER BY

        x.rider_count DESC,
        x.spin_date DESC

    LIMIT 1

");

$stmt->bind_param(
    "ss",
    $start_date,
    $end_date
);

$stmt->execute();

$largest_spin =
    $stmt
    ->get_result()
    ->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Helper function
|--------------------------------------------------------------------------
*/

function capacity_class($percentage)
{
    if ($percentage >= 85) {
        return 'capacity-high';
    }

    if ($percentage >= 60) {
        return 'capacity-medium';
    }

    return 'capacity-low';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        ClubSpin Statistics
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css?v=95"
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

    min-width: 96px;

    height: 36px;

    padding: 0 16px;

    display: flex;

    align-items: center;
    justify-content: center;

    text-decoration: none;

    background: #ffffff22;

    color: #ffffff;

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


.admin-user {

    margin-top: 10px;

    color: #D9F7EE;

    font-size: 14px;

    font-weight: 500;

}


.admin-user strong {
    color: #ffffff;
}


.page {

    max-width: 1000px;

    margin: 0 auto;

    padding: 24px 16px 50px;

}


/*
|--------------------------------------------------------------------------
| Year selector
|--------------------------------------------------------------------------
*/

.year-row {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 10px;

    margin-bottom: 26px;

}


.year-label {

    font-weight: 800;

    color: #475569;

}


.year-select {

    background: #ffffff;

    border: 1px solid #CDE8DF;

    border-radius: 999px;

    padding: 11px 18px;

    font-size: 15px;

    font-weight: 800;

    color: #164B38;

}


/*
|--------------------------------------------------------------------------
| Headline cards
|--------------------------------------------------------------------------
*/

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 14px;

    margin-bottom: 34px;

}


.stat-card {

    background: #ffffff;

    border-radius: 18px;

    padding: 22px 12px;

    text-align: center;

    box-shadow:
        0 8px 24px
        rgba(63, 24, 83, .08);

    border-top:
        5px solid #00CFA9;

}


.stat-number {

    display: block;

    font-size: 30px;

    line-height: 1;

    font-weight: 900;

    color: #00CFA9;

}


.stat-label {

    display: block;

    margin-top: 10px;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .05em;

    color: #64748b;

    font-weight: 800;

}


/*
|--------------------------------------------------------------------------
| Sections
|--------------------------------------------------------------------------
*/

.section-title {

    margin: 36px 0 16px;

    font-size: 27px;

    text-align: center;

    color: #111827;

}


.section-note {

    max-width: 700px;

    margin:
        -5px auto
        18px;

    text-align: center;

    color: #64748b;

    line-height: 1.5;

    font-size: 14px;

}


.panel {

    background: #ffffff;

    border-radius: 18px;

    padding: 20px;

    box-shadow:
        0 8px 24px
        rgba(63, 24, 83, .08);

}


/*
|--------------------------------------------------------------------------
| Tables
|--------------------------------------------------------------------------
*/

.stats-table {

    width: 100%;

    border-collapse: collapse;

}


.stats-table th,
.stats-table td {

    padding: 13px 10px;

    border-bottom:
        1px solid #e5e7eb;

    text-align: center;

}


.stats-table th {

    color: #475569;

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .04em;

}


.stats-table td {

    color: #1e293b;

    font-weight: 650;

}


.stats-table tr:last-child td {
    border-bottom: none;
}


.pace-name {

    text-align: left !important;

    font-weight: 850 !important;

}


.pace-sub {

    display: block;

    margin-top: 3px;

    font-size: 11px;

    color: #94a3b8;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| Pace colours
|--------------------------------------------------------------------------
*/

.pace-dot {

    width: 13px;

    height: 13px;

    border-radius: 50%;

    display: inline-block;

    margin-right: 8px;

    vertical-align: middle;

}


.u14 { background:#D9F7EE; color:#164B38; }
.u16 { background:#D8F7F1; color:#0F766E; }

.blue {
    background: #00A98F;
}


.green {
    background: #16a34a;
}


.yellow {
    background: #eab308;
}


.orange {
    background: #f97316;
}


.red {
    background: #dc2626;
}


/*
|--------------------------------------------------------------------------
| Capacity indicator
|--------------------------------------------------------------------------
*/

.capacity-pill {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 850;

}


.capacity-high {

    background: #dcfce7;

    color: #166534;

}


.capacity-medium {

    background: #fef9c3;

    color: #854d0e;

}


.capacity-low {

    background: #fee2e2;

    color: #991b1b;

}


/*
|--------------------------------------------------------------------------
| Monthly activity
|--------------------------------------------------------------------------
*/

.month-row {

    display: grid;

    grid-template-columns:
        55px 1fr 105px;

    gap: 12px;

    align-items: center;

    margin-bottom: 13px;

}


.month-name {

    font-weight: 800;

    color: #475569;

}


.month-bar-bg {

    height: 14px;

    background: #f1f5f9;

    border-radius: 999px;

    overflow: hidden;

}


.month-bar {

    height: 100%;

    background: #00CFA9;

    border-radius: 999px;

}


.month-value {

    text-align: right;

    color: #64748b;

    font-size: 13px;

    font-weight: 700;

}


/*
|--------------------------------------------------------------------------
| Lower panels
|--------------------------------------------------------------------------
*/

.two-column {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 20px;

}


/*
|--------------------------------------------------------------------------
| Back button
|--------------------------------------------------------------------------
*/

.back-row {

    text-align: center;

    margin-top: 34px;

}


.back-btn {

    display: inline-block;

    padding: 13px 22px;

    border-radius: 999px;

    background: #00CFA9;

    color: #ffffff;

    text-decoration: none;

    font-weight: 800;

}


.back-btn:hover {
    background: #164B38;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media(max-width:900px) {

    .stats-grid {
        grid-template-columns:
            repeat(3, 1fr);
    }

}


@media(max-width:760px) {

    .stats-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .two-column {
        grid-template-columns:
            1fr;
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

        margin:
            0 auto 18px;

    }


    .stats-grid {

        grid-template-columns:
            1fr 1fr;

        gap: 10px;

    }


    .stat-card {
        padding: 18px 8px;
    }


    .stat-number {
        font-size: 26px;
    }


    .stats-table th,
    .stats-table td {

        padding: 10px 5px;

        font-size: 12px;

    }


    .month-row {

        grid-template-columns:
            42px 1fr 85px;

        gap: 7px;

    }


    .panel {
        padding: 14px;
    }

}


</style>

</head>


<body>


<header class="app-header">


    <div class="header-actions">

        <a href="index.php">
            Admin
        </a>

        <a href="logout.php">
            Logout
        </a>

    </div>


    <img
        class="header-logo"
        src="../assets/images/ci-youth.png?v=9"
        alt="Participating cycling club logos"
    >


    <h1>
        Club Statistics
    </h1>


    <div class="admin-user">

        <strong>
            <?php
            echo htmlspecialchars(
                $current_name
            );
            ?>
        </strong>

        - Admin

    </div>


    <p>
        ClubSpin activity and participation
    </p>


</header>



<div class="page">


    <!-- YEAR SELECTOR -->

    <form
        method="GET"
        class="year-row"
    >

        <label
            for="year"
            class="year-label"
        >
            Year
        </label>


        <select
            id="year"
            name="year"
            class="year-select"
            onchange="this.form.submit()"
        >

            <?php
            for (
                $year = $current_year;
                $year >= 2026;
                $year--
            ):
            ?>

                <option

                    value="<?php
                    echo $year;
                    ?>"

                    <?php
                    echo (
                        $selected_year === $year
                    )
                        ? 'selected'
                        : '';
                    ?>

                >

                    <?php
                    echo $year;
                    ?>

                </option>

            <?php endfor; ?>

        </select>

    </form>



    <!-- HEADLINE STATS -->

    <div class="stats-grid">


        <div class="stat-card">

            <span class="stat-number">

                <?php
                echo $total_spins;
                ?>

            </span>

            <span class="stat-label">
                Spins
            </span>

        </div>


        <div class="stat-card">

            <span class="stat-number">

                <?php
                echo $total_signons;
                ?>

            </span>

            <span class="stat-label">
                Registrations
            </span>

        </div>


        <div class="stat-card">

            <span class="stat-number">

                <?php
                echo number_format(
                    $average_signons,
                    1
                );
                ?>

            </span>

            <span class="stat-label">
                Avg Registrations
            </span>

        </div>


        <div class="stat-card">

            <span class="stat-number">

                <?php
                echo number_format(
                    $overall_capacity_used,
                    1
                );
                ?>%

            </span>

            <span class="stat-label">
                Capacity Used
            </span>

        </div>


    </div>



    <!-- PACE GROUPS -->

    <h2 class="section-title">
        Spins by Pace Group
    </h2>


    <p class="section-note">

        Capacity used compares total rider
        registrations with the total number
        of available places offered by each
        pace group.

    </p>


    <div class="panel">


        <table class="stats-table">


            <thead>

                <tr>

                    <th style="text-align:left">
                        Group
                    </th>

                    <th>
                        Spins
                    </th>

                    <th>
                        Registrations
                    </th>

                    <th>
                        Avg
                    </th>

                    <th>
                        Capacity
                    </th>

                    <th>
                        Highest
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php
            foreach (
                $pace_labels
                as $pace => $meta
            ):
            ?>


                <?php

                $row =
                    $pace_stats[$pace]
                    ?? [];

                $spin_count =
                    (int) (
                        $row['spin_count']
                        ?? 0
                    );

                $signons =
                    (int) (
                        $row['total_signons']
                        ?? 0
                    );

                $avg =
                    (float) (
                        $row['average_signons']
                        ?? 0
                    );

                $highest =
                    (int) (
                        $row['highest_registrations']
                        ?? 0
                    );

                $capacity_used =
                    (float) (
                        $row['capacity_used']
                        ?? 0
                    );

                $cap_class =
                    capacity_class(
                        $capacity_used
                    );

                ?>


                <tr>


                    <td class="pace-name">

                        <span
                            class="pace-dot <?php
                            echo htmlspecialchars(
                                $meta['class']
                            );
                            ?>"
                        ></span>


                        <?php
                        echo htmlspecialchars(
                            $meta['label']
                        );
                        ?>


                        <span class="pace-sub">

                            <?php
                            echo htmlspecialchars(
                                $meta['full']
                            );
                            ?>

                        </span>

                    </td>


                    <td>

                        <?php
                        echo $spin_count;
                        ?>

                    </td>


                    <td>

                        <?php
                        echo $signons;
                        ?>

                    </td>


                    <td>

                        <?php
                        echo number_format(
                            $avg,
                            1
                        );
                        ?>

                    </td>


                    <td>

                        <span
                            class="capacity-pill <?php
                            echo $cap_class;
                            ?>"
                        >

                            <?php
                            echo number_format(
                                $capacity_used,
                                1
                            );
                            ?>%

                        </span>

                    </td>


                    <td>

                        <?php
                        echo $highest;
                        ?>

                    </td>


                </tr>


            <?php endforeach; ?>


            </tbody>


        </table>


    </div>



    <!-- MONTHLY ACTIVITY -->

    <h2 class="section-title">
        Monthly Activity
    </h2>


    <div class="panel">


        <?php

        $max_month_signons = 1;

        foreach (
            $monthly
            as $data
        ) {

            $max_month_signons =
                max(
                    $max_month_signons,
                    $data['signons']
                );

        }

        ?>


        <?php
        foreach (
            $monthly
            as $month => $data
        ):
        ?>


            <?php

            $bar_width =
                (
                    $data['signons']
                    /
                    $max_month_signons
                )
                * 100;

            ?>


            <div class="month-row">


                <div class="month-name">

                    <?php

                    echo date(
                        'M',
                        mktime(
                            0,
                            0,
                            0,
                            $month,
                            1
                        )
                    );

                    ?>

                </div>


                <div class="month-bar-bg">

                    <div

                        class="month-bar"

                        style="width: <?php
                        echo round(
                            $bar_width,
                            1
                        );
                        ?>%;"

                    ></div>

                </div>


                <div class="month-value">

                    <?php
                    echo $data['signons'];
                    ?>
                    registrations

                </div>


            </div>


        <?php endforeach; ?>


    </div>



    <!-- CLUBS / LEADERS -->

    <div class="two-column">


        <div>


            <h2 class="section-title">
                Registrations by Club
            </h2>

            <div class="panel">

                <table class="stats-table">

                    <thead>
                        <tr>
                            <th style="text-align:left">Club</th>
                            <th>Registrations</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php while ($club = $clubs->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align:left">
                                <?php echo htmlspecialchars($club['club_name']); ?>
                            </td>
                            <td>
                                <?php echo (int) $club['registration_count']; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>

                </table>

            </div>


        </div>



        <div>


            <h2 class="section-title">
                Ride Leaders
            </h2>


            <div class="panel">


                <table class="stats-table">


                    <thead>

                        <tr>

                            <th style="text-align:left">
                                Leader
                            </th>

                            <th>
                                Spins
                            </th>

                            <th>
                                Avg
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php
                    while (
                        $leader =
                        $leaders
                        ->fetch_assoc()
                    ):
                    ?>


                        <tr>


                            <td style="text-align:left">

                                <?php

                                echo htmlspecialchars(
                                    $leader['leader_name']
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo (int)
                                    $leader['spins_led'];

                                ?>

                            </td>


                            <td>

                                <?php

                                echo number_format(

                                    (float)
                                    $leader[
                                        'average_registrations'
                                    ],

                                    1

                                );

                                ?>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>



    <!-- EXTRA INFORMATION -->

    <h2 class="section-title">
        Year Summary
    </h2>


    <div class="panel">


        <table class="stats-table">


            <tbody>


                <tr>

                    <td style="text-align:left">
                        Average spin distance
                    </td>

                    <td>

                        <?php
                        echo number_format(
                            $average_distance,
                            1
                        );
                        ?>
                        km

                    </td>

                </tr>


                <tr>

                    <td style="text-align:left">
                        Total places offered
                    </td>

                    <td>

                        <?php
                        echo number_format(
                            $total_capacity
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td style="text-align:left">
                        Total registrations
                    </td>

                    <td>

                        <?php
                        echo number_format(
                            $total_signons
                        );
                        ?>

                    </td>

                </tr>


                <tr>

                    <td style="text-align:left">
                        Overall capacity used
                    </td>

                    <td>

                        <span
                            class="capacity-pill <?php
                            echo capacity_class(
                                $overall_capacity_used
                            );
                            ?>"
                        >

                            <?php
                            echo number_format(
                                $overall_capacity_used,
                                1
                            );
                            ?>%

                        </span>

                    </td>

                </tr>


                <?php
                if (
                    !empty($largest_spin)
                ):
                ?>


                    <tr>

                        <td style="text-align:left">
                            Highest single-spin registrations
                        </td>

                        <td>

                            <?php
                            echo (int)
                                $largest_spin[
                                    'rider_count'
                                ];
                            ?>

                            registrations

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>


        </table>


    </div>



    <div class="back-row">

        <a
            class="back-btn"
            href="index.php"
        >
            ← Back to Admin
        </a>

    </div>


</div>



<?php

include_once
    __DIR__
    . '/../includes/footer.php';

?>


<script>

if (
    "serviceWorker"
    in navigator
) {

    navigator.serviceWorker.register(
        "/clubspins/service-worker.js?v=5"
    );

}

</script>


</body>

</html>