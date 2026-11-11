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

$current_role = $_SESSION['user_role']
    ?? $_SESSION['spin_admin_role']
    ?? '';

/*
|--------------------------------------------------------------------------
| Pace groups
|--------------------------------------------------------------------------
*/

$pace_labels = [
    'u14' => [
        'label' => 'Under 14 — 23–25 km/h',
        'class' => 'u14',
        'order' => 1
    ],
    'u16' => [
        'label' => 'Under 16 — 25–28 km/h',
        'class' => 'u16',
        'order' => 2
    ]
];

/*
|--------------------------------------------------------------------------
| Helpers
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

function clean_distance($distance)
{
    if ($distance === null || $distance === '') {
        return '';
    }

    return rtrim(
        rtrim((string)$distance, '0'),
        '.'
    );
}

/*
|--------------------------------------------------------------------------
| Validate a stored route URL
|--------------------------------------------------------------------------
|
| We already have some historical entries that are blank or incomplete,
| such as https://strava.app.link with no actual route attached.
|
*/

function usable_route_url($url)
{
    $url = trim((string)$url);

    if ($url === '') {
        return false;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parts = parse_url($url);

    if (empty($parts['host'])) {
        return false;
    }

    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';

    /*
     * A bare Strava app link is not an actual route.
     */
    if (
        $host === 'strava.app.link'
        && ($path === '' || $path === '/')
    ) {
        return false;
    }

    return true;
}

/*
|--------------------------------------------------------------------------
| Load all spins containing route URLs
|--------------------------------------------------------------------------
|
| We intentionally retrieve individual spins and deduplicate in PHP.
| That lets us:
|
| - retain the most recent title / description / distance
| - combine all pace groups that have used the route
| - retain a real spin_id for "Use This Route"
|
*/

$sql = "
    SELECT
        spin_id,
        title,
        spin_date,
        distance_km,
        pace_level,
        description,
        strava_url,
        status
    FROM CIY_spins
    WHERE strava_url IS NOT NULL
      AND TRIM(strava_url) <> ''
      AND status = 'final'
    ORDER BY spin_date DESC, spin_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load route library: " . $conn->error);
}

/*
|--------------------------------------------------------------------------
| Build the route library
|--------------------------------------------------------------------------
*/

$routes = [];

while ($spin = $result->fetch_assoc()) {

    $route_url = trim($spin['strava_url']);

    if (!usable_route_url($route_url)) {
        continue;
    }

    /*
     * Exact URL is our route identifier.
     */
    $route_key = $route_url;

    /*
     * First occurrence is the most recent because of SQL ordering.
     */
    if (!isset($routes[$route_key])) {

        $routes[$route_key] = [
            'spin_id' => (int)$spin['spin_id'],
            'title' => $spin['title'],
            'spin_date' => $spin['spin_date'],
            'distance_km' => $spin['distance_km'],
            'description' => $spin['description'],
            'route_url' => $route_url,
            'paces' => []
        ];
    }

    $pace = trim($spin['pace_level'] ?? '');

    if (
        $pace !== ''
        && isset($pace_labels[$pace])
    ) {
        $routes[$route_key]['paces'][$pace] = true;
    }
}

/*
|--------------------------------------------------------------------------
| Convert associative route collection to normal array
|--------------------------------------------------------------------------
*/

$routes = array_values($routes);

/*
|--------------------------------------------------------------------------
| Most recently used first
|--------------------------------------------------------------------------
*/

usort($routes, function ($a, $b) {

    $date_compare = strcmp(
        $b['spin_date'],
        $a['spin_date']
    );

    if ($date_compare !== 0) {
        return $date_compare;
    }

    return strcmp(
        $a['title'],
        $b['title']
    );
});

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Route Library | ClubSpin</title>

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

.page{
    max-width:860px;
    margin:0 auto;
    padding:20px 16px 40px;
}

/* ---------------------------------------------------------
   Intro
--------------------------------------------------------- */

.library-intro{
    text-align:center;
    margin-bottom:24px;
}

.library-intro h2{
    margin:0 0 8px;
    font-size:29px;
    color:#111827;
}

.library-intro p{
    max-width:620px;
    margin:0 auto;
    color:#64748b;
    font-size:15px;
    line-height:1.55;
}

/* ---------------------------------------------------------
   Back button
--------------------------------------------------------- */

.library-actions{
    display:flex;
    justify-content:center;
    margin-bottom:26px;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 18px;
    border-radius:999px;
    background:#f1f5f9;
    color:#334155;
    text-decoration:none;
    font-size:14px;
    font-weight:800;
}

.back-btn:hover{
    background:#e2e8f0;
}

/* ---------------------------------------------------------
   Route list
--------------------------------------------------------- */

.route-list{
    display:grid;
    gap:16px;
}

.route-card{
    background:#fff;
    border-radius:18px;
    padding:20px;
    box-shadow:0 8px 24px rgba(63,24,83,.08);
    border-top:5px solid #00CFA9;
}

.route-card h2{
    margin:0 0 12px;
    font-size:23px;
    line-height:1.3;
    color:#111827;
}

/* ---------------------------------------------------------
   Route meta
--------------------------------------------------------- */

.route-meta{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:8px;
    margin-bottom:14px;
}

.distance-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 11px;
    border-radius:999px;
    background:#D9F7EE;
    color:#00CFA9;
    font-size:13px;
    font-weight:900;
}

.last-used{
    color:#64748b;
    font-size:13px;
    font-weight:700;
}

/* ---------------------------------------------------------
   Pace groups
--------------------------------------------------------- */

.used-by{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:7px;
    margin:12px 0 15px;
}

.used-by-label{
    color:#475569;
    font-size:13px;
    font-weight:800;
    margin-right:2px;
}

.pace-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
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

/* ---------------------------------------------------------
   Description
--------------------------------------------------------- */

.route-description{
    color:#475569;
    font-size:15px;
    line-height:1.6;
    margin:4px 0 18px;
}

/* ---------------------------------------------------------
   Buttons
--------------------------------------------------------- */

.route-actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-top:18px;
}

.route-actions a{
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:48px;
    box-sizing:border-box;
    padding:12px 14px;
    border-radius:999px;
    text-align:center;
    text-decoration:none;
    font-size:15px;
    font-weight:800;
}

.view-route{
    background:#eef2ff;
    color:#4338ca;
}

.view-route:hover{
    background:#e0e7ff;
}

.use-route{
    background:#00CFA9;
    color:#fff;
}

.use-route:hover{
    background:#164B38;
}

/* ---------------------------------------------------------
   Empty state
--------------------------------------------------------- */

.empty-state{
    background:#fff;
    border-radius:18px;
    padding:28px 20px;
    text-align:center;
    box-shadow:0 8px 24px rgba(63,24,83,.08);
}

.empty-state h3{
    margin-top:0;
    color:#111827;
}

.empty-state p{
    color:#64748b;
    margin-bottom:0;
}

/* ---------------------------------------------------------
   Mobile
--------------------------------------------------------- */

@media(max-width:600px){

    .route-actions{
        grid-template-columns:1fr;
    }

    .library-intro h2{
        font-size:26px;
    }

}

</style>

</head>

<body>

<header class="app-header">

    <img
        src="../assets/images/ci-youth.png?v=9"
        alt="Participating cycling club logos"
    >

    <h1>
        Route Library
    </h1>

    <p>
        Choose a route for your next club spin
    </p>

</header>

<div class="page">

    <div class="library-intro">

        <h2>
            Club Routes
        </h2>

        <p>
            Routes previously used on youth club spins.
            Choose one to use its distance, route link and description
            when creating your new spin.
        </p>

    </div>

    <div class="library-actions">

        <a
            href="index.php"
            class="back-btn"
        >
            ← ClubSpin Admin
        </a>

    </div>

    <?php if (!empty($routes)): ?>

        <div class="route-list">

            <?php foreach ($routes as $route): ?>

                <article class="route-card">

                    <h2>
                        <?php echo h($route['title']); ?>
                    </h2>

                    <div class="route-meta">

                        <?php if (
                            $route['distance_km'] !== null
                            && $route['distance_km'] !== ''
                        ): ?>

                            <span class="distance-pill">

                                <?php
                                echo h(
                                    clean_distance(
                                        $route['distance_km']
                                    )
                                );
                                ?>
                                km

                            </span>

                        <?php endif; ?>

                        <span class="last-used">

                            Last used
                            <?php
                            echo date(
                                "j M Y",
                                strtotime($route['spin_date'])
                            );
                            ?>

                        </span>

                    </div>

                    <?php if (!empty($route['paces'])): ?>

                        <div class="used-by">

                            <span class="used-by-label">
                                Used by:
                            </span>

                            <?php

                            $route_paces = array_keys(
                                $route['paces']
                            );

                            usort(
                                $route_paces,
                                function ($a, $b) use ($pace_labels) {

                                    return
                                        $pace_labels[$a]['order']
                                        <=>
                                        $pace_labels[$b]['order'];
                                }
                            );

                            ?>

                            <?php foreach ($route_paces as $pace): ?>

                                <span
                                    class="pace-pill pace-<?php
                                        echo h(
                                            $pace_labels[$pace]['class']
                                        );
                                    ?>"
                                >
                                    <?php
                                    echo h(
                                        $pace_labels[$pace]['label']
                                    );
                                    ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                    <?php if (
                        !empty(
                            trim(
                                $route['description'] ?? ''
                            )
                        )
                    ): ?>

                        <div class="route-description">

                            <?php
                            echo nl2br(
                                h($route['description'])
                            );
                            ?>

                        </div>

                    <?php endif; ?>

                    <div class="route-actions">

                        <a
                            class="view-route"
                            href="<?php echo h($route['route_url']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            View Route
                        </a>

                        <a
                            class="use-route"
                            href="create_spin.php?route_from_spin=<?php
                                echo (int)$route['spin_id'];
                            ?>"
                        >
                            Use This Route
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <h3>
                No routes available
            </h3>

            <p>
                Routes will appear here once club spins
                have been created with a route link.
            </p>

        </div>

    <?php endif; ?>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>

if ("serviceWorker" in navigator) {

    navigator.serviceWorker.register(
        "/clubspins/service-worker.js?v=5"
    );

}

</script>

</body>
</html>