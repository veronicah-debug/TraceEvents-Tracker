<?php
// process-login.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$loginId  = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';

if ($loginId === '' || $password === '') {
    header('Location: index.php?error=empty');
    exit;
}

$stmt = $conn->prepare(
    "SELECT user_id, first_name, last_name, password_hash, role
     FROM user_account
     WHERE (email = ? OR admission_no = ? OR identity_ref = ?)
       AND account_status = 'ACTIVE'
     LIMIT 1"
);
$stmt->bind_param('sss', $loginId, $loginId, $loginId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    header('Location: index.php?error=invalid');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['user_id'];
$_SESSION['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
$_SESSION['role']      = $user['role'];

header('Location: ' . role_home($user['role']));
exit;