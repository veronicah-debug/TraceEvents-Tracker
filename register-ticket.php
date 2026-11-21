<?php
// register-ticket.php - creates a registration and its ticket for the logged-in student
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('STUDENT');

if (!isset($_POST['ticket_type_id'])) {
    header('Location: events.php');
    exit;
}

$type_id    = (int) $_POST['ticket_type_id'];
$student_id = $_SESSION['user_id'];          // from the session, never from the form

// 1. Is this ticket type open, and are there tickets left?
$sql = "SELECT tt.event_id, tt.quantity, tt.active, e.event_status, e.registration_deadline,
               COUNT(r.registration_id) AS sold
        FROM ticket_type tt
        JOIN event e ON e.event_id = tt.event_id
        LEFT JOIN registration r ON r.ticket_type_id = tt.ticket_type_id AND r.registration_status <> 'CANCELLED'
        WHERE tt.ticket_type_id = ?
        GROUP BY tt.ticket_type_id";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $type_id);
mysqli_stmt_execute($stmt);
$tt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$tt || !$tt['active'] || $tt['event_status'] !== 'APPROVED'
    || strtotime($tt['registration_deadline']) < time()) {
    header('Location: events.php?msg=unavailable');
    exit;
}
if ($tt['sold'] >= $tt['quantity']) {
    header('Location: events.php?msg=soldout');
    exit;
}
$event_id = (int) $tt['event_id'];

// 2. Already registered for this event?
$stmt = mysqli_prepare($conn, "SELECT registration_id FROM registration
                               WHERE student_id = ? AND event_id = ? AND registration_status <> 'CANCELLED'");
mysqli_stmt_bind_param($stmt, 'ii', $student_id, $event_id);
mysqli_stmt_execute($stmt);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
    header('Location: events.php?msg=duplicate');
    exit;
}

// 3. Insert the registration and the ticket together (all or nothing)
try {
    mysqli_begin_transaction($conn);

    $stmt = mysqli_prepare($conn, "INSERT INTO registration (student_id, event_id, ticket_type_id, registration_date, registration_status)
                                   VALUES (?, ?, ?, NOW(), 'CONFIRMED')");
    mysqli_stmt_bind_param($stmt, 'iii', $student_id, $event_id, $type_id);
    mysqli_stmt_execute($stmt);
    $registration_id = mysqli_insert_id($conn);

    $stmt = mysqli_prepare($conn, "INSERT INTO ticket (registration_id, qr_reference, issued_at, ticket_status)
                                   VALUES (?, UUID(), NOW(), 'ISSUED')");
    mysqli_stmt_bind_param($stmt, 'i', $registration_id);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($conn);
    if ($ex->getCode() == 1062) {            // duplicate entry
        header('Location: events.php?msg=duplicate');
        exit;
    }
    throw $ex;
}

header('Location: my-tickets.php?msg=registered');
exit;