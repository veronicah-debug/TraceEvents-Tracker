<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('STUDENT');
$page_title = 'My Tickets';
include 'includes/header.php';

$uid = $_SESSION['user_id'];
$sql = "SELECT t.qr_reference, t.ticket_status, e.event_name, e.venue, e.event_date, e.start_time,
               tt.type_name, a.check_in_time
        FROM registration r
        JOIN ticket t       ON t.registration_id = r.registration_id
        JOIN event e        ON e.event_id = r.event_id
        JOIN ticket_type tt ON tt.ticket_type_id = r.ticket_type_id
        LEFT JOIN attendance a ON a.ticket_id = t.ticket_id AND a.attendance_status = 'PRESENT'
        WHERE r.student_id = ?
        ORDER BY e.event_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$count = mysqli_num_rows($result);
?>
<main>
  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'registered') { ?>
    <p><strong>Ticket confirmed. Show the code below at the gate.</strong></p>
  <?php } ?>
  <p>Tickets held: <?php echo $count; ?></p>

  <table>
    <caption>Your tickets</caption>
    <thead>
      <tr><th>Ticket code</th><th>Event</th><th>Type</th><th>When</th><th>Venue</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php
    if ($count === 0) {
        echo '<tr><td colspan="6">No tickets yet. <a href="events.php">Browse events</a>.</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['ticket_status'] === 'CANCELLED') {
                $status = 'Cancelled';
            } elseif ($row['ticket_status'] === 'USED' || $row['check_in_time'] !== null) {
                $status = 'Attended';
            } elseif ($row['event_date'] < date('Y-m-d')) {
                $status = 'Missed';
            } else {
                $status = 'Valid';
            }
            echo '<tr>';
            echo '<td>' . e($row['qr_reference']) . '</td>';
            echo '<td>' . e($row['event_name']) . '</td>';
            echo '<td>' . e($row['type_name']) . '</td>';
            echo '<td>' . date('D j M Y', strtotime($row['event_date'])) . ', ' . date('g:i A', strtotime($row['start_time'])) . '</td>';
            echo '<td>' . e($row['venue']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
    }
    ?>
    </tbody>
  </table>
</main>
<?php include 'includes/footer.php'; ?>
