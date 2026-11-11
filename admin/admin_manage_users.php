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

$logged_in_user_id = $_SESSION['user_id'] ?? $_SESSION['spin_admin_user_id'] ?? 0;
$logged_in_role = $_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '';
$logged_in_name = $_SESSION['user_name'] ?? $_SESSION['spin_admin_name'] ?? '';

if ($logged_in_role !== 'admin') {
    die("Access denied. Admins only.");
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function clean_text_field($value) {
    $value = trim($value);

    // Remove invisible Unicode direction/control characters
    $value = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);

    return trim($value);
}

function clean_phone_field($value) {
    $value = trim($value);

    // Remove invisible Unicode direction/control characters
    $value = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $value);

    // Keep normal phone-number characters only
    $value = preg_replace('/[^0-9+\-\s().]/', '', $value);

    return trim($value);
}

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | Create User
    |--------------------------------------------------------------------------
    */

    if ($action === "create_user") {

        $name = clean_text_field($_POST["name"] ?? "");
        $email = strtolower(clean_text_field($_POST["email"] ?? ""));
        $password = trim($_POST["password"] ?? "");
        $phone = clean_phone_field($_POST["phone"] ?? "");
        $ice_name = clean_text_field($_POST["ice_name"] ?? "");
        $ice_phone = clean_phone_field($_POST["ice_phone"] ?? "");
        $role = clean_text_field($_POST["role"] ?? "leader");
        $active = isset($_POST["active"]) ? 1 : 0;

        if (!in_array($role, ['admin', 'leader'])) {
            $role = 'leader';
        }

        if ($name === "" || $email === "" || $password === "") {
            $message = "<div class='alert error'>Name, email and password are required.</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert error'>Please enter a valid email address.</div>";
        } else {

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO CIY_spin_users
                (
                    name,
                    email,
                    password_hash,
                    phone,
                    ice_name,
                    ice_phone,
                    role,
                    active
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param(
                "sssssssi",
                $name,
                $email,
                $password_hash,
                $phone,
                $ice_name,
                $ice_phone,
                $role,
                $active
            );

            if ($stmt->execute()) {
                $message = "<div class='alert success'>User created successfully.</div>";
            } else {
                $message = "<div class='alert error'>Error creating user: " . htmlspecialchars($stmt->error) . "</div>";
            }

            $stmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update User
    |--------------------------------------------------------------------------
    */

    if ($action === "update_user") {

        $user_id = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;

        $name = clean_text_field($_POST["name"] ?? "");
        $email = strtolower(clean_text_field($_POST["email"] ?? ""));
        $password = trim($_POST["password"] ?? "");
        $phone = clean_phone_field($_POST["phone"] ?? "");
        $ice_name = clean_text_field($_POST["ice_name"] ?? "");
        $ice_phone = clean_phone_field($_POST["ice_phone"] ?? "");
        $role = clean_text_field($_POST["role"] ?? "leader");
        $active = isset($_POST["active"]) ? 1 : 0;

        if ($user_id <= 0) {
            $message = "<div class='alert error'>Invalid user ID.</div>";
        } elseif ($name === "" || $email === "") {
            $message = "<div class='alert error'>Name and email are required.</div>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert error'>Please enter a valid email address.</div>";
        } else {

            if (!in_array($role, ['admin', 'leader'])) {
                $role = 'leader';
            }

            if ($password !== "") {

                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    UPDATE CIY_spin_users
                    SET
                        name = ?,
                        email = ?,
                        password_hash = ?,
                        phone = ?,
                        ice_name = ?,
                        ice_phone = ?,
                        role = ?,
                        active = ?
                    WHERE user_id = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param(
                    "sssssssii",
                    $name,
                    $email,
                    $password_hash,
                    $phone,
                    $ice_name,
                    $ice_phone,
                    $role,
                    $active,
                    $user_id
                );

            } else {

                $stmt = $conn->prepare("
                    UPDATE CIY_spin_users
                    SET
                        name = ?,
                        email = ?,
                        phone = ?,
                        ice_name = ?,
                        ice_phone = ?,
                        role = ?,
                        active = ?
                    WHERE user_id = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    die("Prepare failed: " . $conn->error);
                }

                $stmt->bind_param(
                    "ssssssii",
                    $name,
                    $email,
                    $phone,
                    $ice_name,
                    $ice_phone,
                    $role,
                    $active,
                    $user_id
                );
            }

            if ($stmt->execute()) {
                $message = "<div class='alert success'>User updated successfully.</div>";
            } else {
                $message = "<div class='alert error'>Error updating user: " . htmlspecialchars($stmt->error) . "</div>";
            }

            $stmt->close();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete User
    |--------------------------------------------------------------------------
    */

    if ($action === "delete_user") {

        $user_id = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;

        if ($user_id <= 0) {
            $message = "<div class='alert error'>Invalid user ID.</div>";
        } elseif ($user_id === (int)$logged_in_user_id) {
            $message = "<div class='alert error'>You cannot delete your own logged-in account.</div>";
        } else {

            $stmt = $conn->prepare("
                DELETE FROM CIY_spin_users
                WHERE user_id = ?
                LIMIT 1
            ");

            if (!$stmt) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $message = "<div class='alert success'>User deleted successfully.</div>";
            } else {
                $message = "<div class='alert error'>Error deleting user: " . htmlspecialchars($stmt->error) . "</div>";
            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load Existing Users
|--------------------------------------------------------------------------
*/

$users = $conn->query("
    SELECT 
        user_id,
        name,
        email,
        phone,
        ice_name,
        ice_phone,
        role,
        active
    FROM CIY_spin_users
    ORDER BY 
        CASE role
            WHEN 'admin' THEN 1
            WHEN 'leader' THEN 2
            ELSE 3
        END,
        name ASC
");

if (!$users) {
    die("User query failed: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Leaders & Admins</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/css/style.css?v=20">
<link rel="manifest" href="/clubspins/manifest.json">
<meta name="theme-color" content="#00CFA9">
<link rel="apple-touch-icon" href="/clubspins/assets/images/icon-192.png">

<style>
.page{
    max-width:860px;
    margin:0 auto;
    padding:20px 16px 40px;
}

.card{
    background:#fff;
    border-radius:18px;
    padding:22px;
    border-top:5px solid #00CFA9;
    box-shadow:0 8px 24px rgba(63,24,83,.10);
    margin-bottom:20px;
}

.top-link{
    display:inline-block;
    margin-bottom:16px;
    color:#00CFA9;
    font-weight:800;
    text-decoration:none;
}

.admin-user-bar{
    margin-bottom:20px;
    font-size:14px;
    color:#475569;
    font-weight:700;
    text-align:right;
}

label{
    display:block;
    font-weight:800;
    margin-top:14px;
    margin-bottom:6px;
    color:#111827;
}

input,
select{
    width:100%;
    padding:13px;
    border-radius:10px;
    border:1px solid #cbd5e1;
    font-size:16px;
    box-sizing:border-box;
    background:#fff;
}

.alert{
    padding:14px;
    border-radius:12px;
    margin-bottom:20px;
    font-weight:800;
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

.user-card{
    background:#fff;
    border-radius:18px;
    padding:18px;
    border:1px solid #e2e8f0;
    margin-bottom:16px;
}

.user-head{
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:flex-start;
    margin-bottom:10px;
}

.user-title{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.user-name{
    font-weight:900;
    color:#164B38;
    font-size:20px;
}

.user-meta{
    color:#64748b;
    font-size:14px;
    line-height:1.5;
}

.role-badge{
    display:inline-block;
    padding:5px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
}

.role-admin{
    background:#D9F7EE;
    color:#00CFA9;
}

.role-leader{
    background:#eef2ff;
    color:#4338ca;
}

.status-active{
    color:#166534;
    font-weight:800;
}

.status-inactive{
    color:#991b1b;
    font-weight:800;
}

.edit-panel{
    display:none;
    margin-top:16px;
    padding-top:16px;
    border-top:1px solid #e2e8f0;
}

.actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-top:18px;
}

.actions form{
    margin:0;
    width:100%;
}

button,
.action-btn{
    width:100%;
    min-width:0;
    height:52px;
    padding:0 14px;
    border:none;
    border-radius:999px;
    font-weight:800;
    font-size:15px;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    box-sizing:border-box;
    text-align:center;
    text-decoration:none;
    font-family:inherit;
}

.primary-btn{
    background:#00CFA9;
    color:#fff;
}

.primary-btn:hover{
    background:#164B38;
}

.secondary-btn{
    background:#eef2ff;
    color:#4338ca;
}

.delete-btn{
    background:#fee2e2;
    color:#991b1b;
}

.small-note{
    font-size:13px;
    color:#64748b;
    line-height:1.4;
    margin-top:6px;
}

.checkbox-label{
    display:flex;
    gap:10px;
    align-items:center;
    margin-top:14px;
    font-weight:700;
}

.checkbox-label input{
    width:auto;
}

.logo{
    width:90px;
    height:auto;
    margin-bottom:14px;
}

@media(max-width:640px){
    .user-head{
        display:block;
    }

    .actions{
        grid-template-columns:1fr 1fr;
    }
}

@media(max-width:360px){
    .actions{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<header class="app-header">
    <img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos" class="logo">
    <h1>Manage Leaders & Admins</h1>
    <p>ClubSpin Register</p>
</header>

<div class="page">

    <a class="top-link" href="index.php">← Back to Dashboard</a>

    <div class="admin-user-bar">
        <?php echo htmlspecialchars($logged_in_name); ?> - Role: <?php echo ucfirst(htmlspecialchars($logged_in_role)); ?>
    </div>

    <?php echo $message; ?>

    <div class="card">
        <h2>Existing Users</h2>

        <?php if ($users->num_rows > 0): ?>

            <?php while ($user = $users->fetch_assoc()): ?>

                <div class="user-card">

                    <div class="user-head">
                        <div>
                            <div class="user-title">
                                <span class="user-name">
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </span>

                                <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </div>

                            <div class="user-meta">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </div>

                            <?php if (!empty($user['phone'])): ?>
                                <div class="user-meta">
                                    Phone: <?php echo htmlspecialchars($user['phone']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($user['ice_name']) || !empty($user['ice_phone'])): ?>
                                <div class="user-meta">
                                    ICE:
                                    <?php echo htmlspecialchars($user['ice_name']); ?>

                                    <?php if (!empty($user['ice_phone'])): ?>
                                        - <?php echo htmlspecialchars($user['ice_phone']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="user-meta">
                                Status:
                                <?php if ((int)$user['active'] === 1): ?>
                                    <span class="status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-inactive">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="actions">

                        <button 
                            type="button"
                            class="secondary-btn"
                            onclick="toggleEditPanel('editUser<?php echo (int)$user['user_id']; ?>')"
                        >
                            Modify
                        </button>

                        <form 
                            method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this user?');"
                        >
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">

                            <button 
                                type="submit"
                                class="delete-btn"
                                <?php if ((int)$user['user_id'] === (int)$logged_in_user_id) echo 'disabled'; ?>
                            >
                                Delete
                            </button>
                        </form>

                    </div>

                    <div 
                        class="edit-panel"
                        id="editUser<?php echo (int)$user['user_id']; ?>"
                    >
                        <form method="POST">

                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">

                            <label>Name *</label>
                            <input 
                                type="text" 
                                name="name" 
                                value="<?php echo htmlspecialchars($user['name']); ?>" 
                                required
                            >

                            <label>Email *</label>
                            <input 
                                type="email" 
                                name="email" 
                                value="<?php echo htmlspecialchars($user['email']); ?>" 
                                required
                            >

                            <label>New Password</label>
                            <input 
                                type="password" 
                                name="password" 
                                placeholder="Leave blank to keep current password"
                            >

                            <label>Phone</label>
                            <input 
                                type="text" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                            >

                            <label>ICE Contact Name</label>
                            <input 
                                type="text" 
                                name="ice_name" 
                                value="<?php echo htmlspecialchars($user['ice_name'] ?? ''); ?>"
                            >

                            <label>ICE Contact Phone</label>
                            <input 
                                type="text" 
                                name="ice_phone" 
                                value="<?php echo htmlspecialchars($user['ice_phone'] ?? ''); ?>"
                            >

                            <label>Role</label>
                            <select name="role">

                                <option 
                                    value="leader"
                                    <?php if ($user['role'] === 'leader') echo 'selected'; ?>
                                >
                                    Leader
                                </option>

                                <option 
                                    value="admin"
                                    <?php if ($user['role'] === 'admin') echo 'selected'; ?>
                                >
                                    Admin
                                </option>

                            </select>

                            <label class="checkbox-label">
                                <input 
                                    type="checkbox" 
                                    name="active" 
                                    <?php if ((int)$user['active'] === 1) echo 'checked'; ?>
                                >
                                Active
                            </label>

                            <button type="submit" class="primary-btn">
                                Save Changes
                            </button>

                        </form>
                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No users found.</p>

        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Create User</h2>

        <form method="POST">

            <input type="hidden" name="action" value="create_user">

            <label>Name *</label>
            <input type="text" name="name" required>

            <label>Email *</label>
            <input type="email" name="email" required>

            <label>Temporary Password *</label>
            <input type="password" name="password" required>

            <label>Phone</label>
            <input type="text" name="phone">

            <label>ICE Contact Name</label>
            <input type="text" name="ice_name">

            <label>ICE Contact Phone</label>
            <input type="text" name="ice_phone">

            <label>Role</label>
            <select name="role">
                <option value="leader">Leader</option>
                <option value="admin">Admin</option>
            </select>

            <label class="checkbox-label">
                <input type="checkbox" name="active" checked>
                Active
            </label>

            <button type="submit" class="primary-btn">
                Create User
            </button>

        </form>
    </div>

</div>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function toggleEditPanel(id) {
    var panel = document.getElementById(id);

    if (!panel) {
        return;
    }

    if (panel.style.display === "block") {
        panel.style.display = "none";
    } else {
        panel.style.display = "block";
    }
}

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("/clubspins/service-worker.js?v=5");
}
</script>

</body>
</html>