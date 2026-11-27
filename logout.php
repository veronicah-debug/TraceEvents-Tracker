<?php
// logout.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    log_access($conn, (int)$_SESSION['user_id'], 'LOGOUT', 'USER', (int)$_SESSION['user_id'], 'SUCCESS');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}
session_destroy();

header('Location: index.php');
exit;