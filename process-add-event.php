<?php
// process-add-event.php - checks the form on the server, then inserts the event and its ticket type
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('ORGANIZER');

if (!isset($_POST['event_name'])) {
    header('Location: organizer.php');
    exit;
}

$uid         = $_SESSION['user_id'];
$name        = trim($_POST['event_name']);
$description = trim($_POST['description']);
$category    = $_POST['event_category'];
$venue       = trim($_POST['venue']);
$date        = $_POST['event_date'];
$start       = $_POST['start_time'];
$end         = $_POST['end_time'];
$capacity    = (int) $_POST['capacity'];
$deadline    = str_replace('T', ' ', $_POST['registration_deadline']);   // datetime-local gives 2026-12-01T17:00
$price       = (float) $_POST['ticket_price'];

if ($name === '' || $venue === '' || $date === '' || $start === '' || $end === '' || $deadline === ''
    || $capacity < 1 || $price < 0 || !in_array($category, enum_values($conn, 'event', 'event_category'), true)) {
    header('Location: organizer.php?msg=invalid');
    exit;
}

// which club does this organizer belong to?
$stmt = mysqli_prepare($conn, 'SELECT club_id FROM club_member WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$club = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$club) {
    header('Location: organizer.php?msg=noclub');
    exit;
}
$club_id = (int) $club['club_id'];

try {
    mysqli_begin_transaction($conn);

    $sql = "INSERT INTO event (club_id, created_by, event_name, description, event_category, venue,
                               event_date, start_time, end_time, capacity, registration_deadline,
                               ticket_price, event_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_APPROVAL')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iisssssssisd',
        $club_id, $uid, $name, $description, $category, $venue,
        $date, $start, $end, $capacity, $deadline, $price);
    mysqli_stmt_execute($stmt);
    $event_id = mysqli_insert_id($conn);

    // one default ticket type, so students can register once the event is approved
    $stmt = mysqli_prepare($conn, "INSERT INTO ticket_type (event_id, type_name, ticket_price, quantity, description, active)
                                   VALUES (?, 'General', ?, ?, 'Standard ticket', 1)");
    mysqli_stmt_bind_param($stmt, 'idi', $event_id, $price, $capacity);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($conn);
    throw $ex;
}

header('Location: organizer.php?msg=added');
exit;