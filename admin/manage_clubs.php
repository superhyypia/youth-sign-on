<?php
/* CI-Youths: administrator-only club management */
require_once __DIR__ . '/auth_check.php';
if (($_SESSION['user_role'] ?? $_SESSION['spin_admin_role'] ?? '') !== 'admin') {
    http_response_code(403); exit('Access denied. Admins only.');
}
require_once __DIR__ . '/../includes/db_loader.php';
$conn = connect_to_database();
$conn->set_charset('utf8mb4');
if (empty($_SESSION['ciy_clubs_csrf'])) {
    $_SESSION['ciy_clubs_csrf'] = bin2hex(random_bytes(32));
}
$notice = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['ciy_clubs_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Invalid request token.');
    }
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['club_name'] ?? '')));
    if (in_array($action, ['add', 'rename'], true)) {
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150 || mb_strtolower($name, 'UTF-8') === 'other') {
            $error = 'Enter a valid club name (1–150 characters; Other is reserved).';
        } else {
            if ($action === 'add') {
                $stmt = $conn->prepare('INSERT INTO CIY_clubs (club_name) VALUES (?)');
                $stmt->bind_param('s', $name);
            } elseif ($id > 0) {
                $stmt = $conn->prepare('UPDATE CIY_clubs SET club_name = ? WHERE id = ?');
                $stmt->bind_param('si', $name, $id);
            } else { $error = 'Invalid club.'; }
            if (isset($stmt)) {
                if ($stmt->execute()) $notice = $action === 'add' ? 'Club added.' : 'Club renamed.';
                else $error = $stmt->errno === 1062 ? 'That club already exists.' : 'Unable to save club.';
                $stmt->close(); unset($stmt);
            }
        }
    } elseif ($action === 'toggle' && $id > 0) {
        $stmt = $conn->prepare('UPDATE CIY_clubs SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) $notice = 'Club status updated.';
        else $error = 'Club not found or could not be updated.';
        $stmt->close();
    } else $error = 'Invalid action.';
}
$clubs = [];
$result = $conn->query('SELECT id, club_name, is_active FROM CIY_clubs ORDER BY club_name ASC');
if (!$result) { http_response_code(500); exit('Clubs table missing. Run the SQL migration first.'); }
while ($row = $result->fetch_assoc()) $clubs[] = $row;
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Clubs | CI-Youths</title>
<link rel="stylesheet" href="../assets/css/style.css?v=95">
<style>
.club-page{max-width:860px;margin:0 auto;padding:20px 16px 40px}
.club-page .panel{background:var(--app-card);border:1px solid var(--app-border);border-top:5px solid var(--ci-mint);border-radius:18px;padding:22px;box-shadow:0 8px 24px rgba(22,75,56,.08);margin-bottom:20px}
.club-page h2{color:var(--ci-green-dark);margin:0 0 16px;font-size:23px}
.club-page .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;border-bottom:1px solid var(--app-border);padding:12px 0}
.club-page .row:last-child{border-bottom:0}
.club-page .name{flex:1;min-width:190px}
.club-page input[type=text]{padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;max-width:100%;font:inherit}
.club-page button{padding:11px 17px;border:0;border-radius:999px;background:var(--ci-mint);color:#fff;cursor:pointer;font:inherit;font-weight:800}
.club-page button:hover{background:var(--ci-green-dark)}
.club-page button.secondary{background:var(--ci-mint-light);color:var(--ci-green-dark)}
.club-page button.secondary:hover{background:#D5F3EB}
.club-page small,.club-page .muted{color:var(--app-muted)}
.club-page .message{padding:12px;background:var(--ci-mint-light);border-radius:10px;color:var(--ci-green-dark)}
.club-page .message.error{background:#fee2e2;color:#991b1b}
.club-page .back-link{display:inline-block;margin-top:15px;padding:10px 18px;border-radius:999px;background:#ffffff22;color:white;text-decoration:none;font-weight:800}
.club-page .back-link:hover{background:#ffffff33}
@media(max-width:600px){.club-page .row form.row{width:100%}.club-page .row form.row input{flex:1;min-width:0}.club-page .panel{padding:18px}}
</style></head><body>
<header class="app-header"><img src="../assets/images/ci-youth.png?v=9" alt="Participating cycling club logos"><h1>Manage Clubs</h1><p>CI-Youths Administration</p><a class="back-link" href="index.php">← Back to Admin Centre</a></header>
<main class="club-page"><p>Active clubs appear alphabetically in the participant registration dropdown. Deactivated clubs remain in historical records.</p>
<?php if ($notice): ?><p class="message"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error): ?><p class="message error"><?= h($error) ?></p><?php endif; ?>
<section class="panel"><h2>Add a club</h2><form method="post" class="row">
<input type="hidden" name="csrf" value="<?= h($_SESSION['ciy_clubs_csrf']) ?>"><input type="hidden" name="action" value="add">
<input class="name" type="text" name="club_name" maxlength="150" required placeholder="Club name"><button type="submit">Add Club</button></form></section>
<section class="panel"><h2>Existing clubs</h2>
<?php foreach ($clubs as $club): ?>
<div class="row"><div class="name"><strong><?= h($club['club_name']) ?></strong><br><small><?= $club['is_active'] ? 'Active' : 'Inactive' ?></small></div>
<form method="post" class="row" style="border:0;padding:0">
<input type="hidden" name="csrf" value="<?= h($_SESSION['ciy_clubs_csrf']) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$club['id'] ?>">
<input aria-label="Rename <?= h($club['club_name']) ?>" name="club_name" maxlength="150" value="<?= h($club['club_name']) ?>" required><button type="submit" class="secondary">Rename</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['ciy_clubs_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$club['id'] ?>">
<button type="submit" class="secondary"><?= $club['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></div>
<?php endforeach; ?>
<p class="muted">“Other” remains a permanent registration option and cannot be edited here.</p></section></main></body></html>
