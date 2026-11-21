<?php
// checkin.php - the organizer types the ticket code shown by the student
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('ORGANIZER');

$uid      = $_SESSION['user_id'];
$event_id = (int) ($_POST['event_id'] ?? $_GET['id'] ?? 0);

// the event must belong to this organizer
$stmt = mysqli_prepare($conn, 'SELECT event_name FROM event WHERE event_id = ? AND created_by = ?');
mysqli_stmt_bind_param($stmt, 'ii', $event_id, $uid);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$event) {
    header('Location: organizer.php');
    exit;
}

$message = '';

if (isset($_POST['ticket_code'])) {
    // keep only letters, digits and dashes; the first 8 characters are enough
    $code = strtolower(preg_replace('/[^a-fA-F0-9-]/', '', trim($_POST['ticket_code'])));

    if (strlen($code) < 8) {
        $message = 'Type at least the first 8 characters of the ticket code.';
    } else {
        $like = $code . '%';
        $sql = "SELECT t.ticket_id, t.ticket_status, u.first_name, u.last_name
                FROM ticket t
                JOIN registration r ON r.registration_id = t.registration_id
                JOIN user_account u ON u.user_id = r.student_id
                WHERE t.qr_reference LIKE ? AND r.event_id = ?
                LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $like, $event_id);
        mysqli_stmt_execute($stmt);
        $ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$ticket) {
            $message = 'No ticket with that code for this event.';
        } elseif ($ticket['ticket_status'] === 'CANCELLED') {
            $message = 'This ticket was cancelled.';
        } elseif ($ticket['ticket_status'] === 'USED') {
            $message = 'This ticket was already checked in.';
        } else {
            try {
                mysqli_begin_transaction($conn);

                $stmt = mysqli_prepare($conn, "INSERT INTO attendance (ticket_id, event_id, checked_in_by, check_in_time, attendance_status)
                                               VALUES (?, ?, ?, NOW(), 'PRESENT')");
                mysqli_stmt_bind_param($stmt, 'iii', $ticket['ticket_id'], $event_id, $uid);
                mysqli_stmt_execute($stmt);

                $stmt = mysqli_prepare($conn, "UPDATE ticket SET ticket_status = 'USED' WHERE ticket_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $ticket['ticket_id']);
                mysqli_stmt_execute($stmt);

                mysqli_commit($conn);
                $message = 'Checked in: ' . $ticket['first_name'] . ' ' . $ticket['last_name'] . '.';
            } catch (mysqli_sql_exception $ex) {
                mysqli_rollback($conn);
                throw $ex;
            }
        }
    }
}

// everyone checked in so far
$sql = "SELECT u.first_name, u.last_name, u.admission_no, t.qr_reference, a.check_in_time
        FROM attendance a
        JOIN ticket t       ON t.ticket_id = a.ticket_id
        JOIN registration r ON r.registration_id = t.registration_id
        JOIN user_account u ON u.user_id = r.student_id
        WHERE a.event_id = ? AND a.attendance_status = 'PRESENT'
        ORDER BY a.check_in_time DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$count = mysqli_num_rows($result);

$page_title = 'Check-in';
include 'includes/header.php';
?>
<main>
  <section>
    <h2>Check-in: <?php echo e($event['event_name']); ?></h2>
    <?php if ($message !== '') { ?>
      <p><strong><?php echo e($message); ?></strong></p>
    <?php } ?>
    <form action="checkin.php" method="post">
      <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
      <label for="ticket_code">Ticket code (first 8 characters are enough)</label>
      <input id="ticket_code" name="ticket_code" type="text" required>
      <button type="submit">Check in</button>
    </form>
    <p><a href="organizer.php">Back to my events</a></p>
  </section>

  <section>
    <h2>Checked in so far (<?php echo $count; ?>)</h2>
    <table>
      <caption>Attendance for this event</caption>
      <thead><tr><th>Student</th><th>Admission no.</th><th>Ticket</th><th>Time</th></tr></thead>
      <tbody>
      <?php
      if ($count === 0) {
          echo '<tr><td colspan="4">Nobody has been checked in yet.</td></tr>';
      } else {
          while ($row = mysqli_fetch_assoc($result)) {
              echo '<tr>';
              echo '<td>' . e($row['first_name'] . ' ' . $row['last_name']) . '</td>';
              echo '<td>' . e($row['admission_no']) . '</td>';
              echo '<td>' . e(substr($row['qr_reference'], 0, 8)) . '</td>';
              echo '<td>' . e($row['check_in_time']) . '</td>';
              echo '</tr>';
          }
      }
      ?>
      </tbody>
    </table>
  </section>
</main>
<?php include 'includes/footer.php'; ?>