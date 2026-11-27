<?php
// register-ticket.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('STUDENT');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: events.php');
    exit;
}

$studentId    = (int)$_SESSION['user_id'];
$eventId      = (int)($_POST['event_id'] ?? 0);
$ticketTypeId = (int)($_POST['ticket_type_id'] ?? 0);

if ($eventId <= 0 || $ticketTypeId <= 0) {
    $_SESSION['flash_error'] = 'Invalid event or ticket selection.';
    header('Location: events.php');
    exit;
}

try {
    $conn->begin_transaction();

    // Lock event row to prevent overbooking under concurrent requests
    $evStmt = $conn->prepare(
        "SELECT event_id, event_name, capacity, registration_deadline, event_status
         FROM event
         WHERE event_id = ?
         FOR UPDATE"
    );
    $evStmt->bind_param('i', $eventId);
    $evStmt->execute();
    $event = $evStmt->get_result()->fetch_assoc();
    $evStmt->close();

    if (!$event || !in_array($event['event_status'], ['APPROVED', 'PUBLISHED'], true)) {
        throw new RuntimeException('This event is not open for registration.');
    }

    if (!empty($event['registration_deadline']) && strtotime($event['registration_deadline']) < time()) {
        throw new RuntimeException('Registration deadline for this event has passed.');
    }

    // Check ticket type availability
    $ttStmt = $conn->prepare(
        "SELECT ticket_type_id, quantity
         FROM ticket_type
         WHERE ticket_type_id = ? AND event_id = ? AND active = 1"
    );
    $ttStmt->bind_param('ii', $ticketTypeId, $eventId);
    $ttStmt->execute();
    $ticketType = $ttStmt->get_result()->fetch_assoc();
    $ttStmt->close();

    if (!$ticketType) {
        throw new RuntimeException('Selected ticket category is unavailable.');
    }

    // Check duplicate registration for this student & event
    $dupStmt = $conn->prepare(
        "SELECT registration_id
         FROM registration
         WHERE student_id = ? AND event_id = ? AND registration_status <> 'CANCELLED'
         LIMIT 1"
    );
    $dupStmt->bind_param('ii', $studentId, $eventId);
    $dupStmt->execute();
    $dup = $dupStmt->get_result()->fetch_assoc();
    $dupStmt->close();

    if ($dup) {
        throw new RuntimeException('You are already registered for this event.');
    }

    // Check event capacity
    $cntStmt = $conn->prepare(
        "SELECT COUNT(*) AS total_reg
         FROM registration
         WHERE event_id = ? AND registration_status = 'CONFIRMED'"
    );
    $cntStmt->bind_param('i', $eventId);
    $cntStmt->execute();
    $totalReg = (int)($cntStmt->get_result()->fetch_assoc()['total_reg'] ?? 0);
    $cntStmt->close();

    if ($totalReg >= (int)$event['capacity']) {
        throw new RuntimeException('Sorry, this event is fully booked.');
    }

    // Insert registration
    $regStmt = $conn->prepare(
        "INSERT INTO registration (student_id, event_id, ticket_type_id, registration_status)
         VALUES (?, ?, ?, 'CONFIRMED')"
    );
    $regStmt->bind_param('iii', $studentId, $eventId, $ticketTypeId);
    $regStmt->execute();
    $registrationId = (int)$conn->insert_id;
    $regStmt->close();

    // Generate privacy-safe random QR reference (no personal info)
    $qrRef = 'TET-' . strtoupper(bin2hex(random_bytes(10)));

    $tktStmt = $conn->prepare(
        "INSERT INTO ticket (registration_id, qr_reference, ticket_status)
         VALUES (?, ?, 'ISSUED')"
    );
    $tktStmt->bind_param('is', $registrationId, $qrRef);
    $tktStmt->execute();
    $tktStmt->close();

    $conn->commit();
    log_access($conn, $studentId, 'REGISTER_EVENT', 'REGISTRATION', $registrationId, 'SUCCESS', 'Event ID: ' . $eventId);

    $_SESSION['flash_success'] = 'Registration confirmed! Your ticket reference is ' . $qrRef . '.';
    header('Location: my-tickets.php');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: events.php');
    exit;
}