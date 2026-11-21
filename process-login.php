<?php
// process-login.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_POST['login_id'])) {
    header('Location: index.php');
    exit;
}

$login_id = trim($_POST['login_id']);
$password = $_POST['password'] ?? '';

$sql = "SELECT user_id, first_name, last_name, password_hash, role
        FROM user_account
        WHERE (email = ? OR admission_no = ? OR identity_ref = ?)
          AND account_status = 'ACTIVE'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'sss', $login_id, $login_id, $login_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($user && $user['password_hash'] && password_verify($password, $user['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role']      = $user['role'];
    header('Location: ' . role_home($user['role']));
    exit;
}

header('Location: index.php?error=login');
exit;