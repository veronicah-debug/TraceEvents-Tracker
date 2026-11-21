<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function role_home($role) {
    $homes = [
        'STUDENT'    => 'my-tickets.php',
        'ORGANIZER'  => 'organizer.php',
        'MANAGEMENT' => 'management.php',
    ];
    return $homes[$role];
}

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        header('Location: index.php?error=access');
        exit;
    }
}

function e($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
function enum_values($conn, $table, $column) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    $row = mysqli_fetch_assoc($res);
    preg_match_all("/'([^']+)'/", $row['Type'], $m);
    return $m[1];
}