<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('MANAGEMENT');

if (isset($_POST['event_id'])) {
    $id = (int) $_POST['event_id'];
    $stmt = mysqli_prepare($conn, "UPDATE event SET event_status = 'APPROVED'
                                   WHERE event_id = ? AND event_status = 'PENDING_APPROVAL'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
}
header('Location: management.php?msg=approved');
exit;