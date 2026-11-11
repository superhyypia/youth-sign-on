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

/*
|--------------------------------------------------------------------------
| Only allow POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$spin_id = isset($_POST['spin_id']) ? (int)$_POST['spin_id'] : 0;

if ($spin_id <= 0) {
    die("Invalid spin ID.");
}

/*
|--------------------------------------------------------------------------
| Load Spin First
|--------------------------------------------------------------------------
*/

$spin_stmt = $conn->prepare("
    SELECT 
        spin_id,
        title,
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
| Admins can delete any spin.
| Leaders can only delete their own spins.
|--------------------------------------------------------------------------
*/

if (!$is_admin && (int)$spin['leader_user_id'] !== (int)$current_user_id) {
    die("Access denied. You can only delete your own spins.");
}

/*
|--------------------------------------------------------------------------
| Delete Registrations First
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM CIY_spin_registrations
    WHERE spin_id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $spin_id);
$stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Delete Spin
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    DELETE FROM CIY_spins
    WHERE spin_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $spin_id);
$stmt->execute();
$stmt->close();

$conn->close();

header("Location: index.php");
exit;
?>