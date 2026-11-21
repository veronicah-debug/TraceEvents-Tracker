<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('MANAGEMENT');
$page_title = 'Management';
include 'includes/header.php';

$sql = "SELECT e.event_id, e.event_name, e.event_date, e.capacity, e.ticket_price, e.event_status,
               COUNT(DISTINCT CASE WHEN r.registration_status <> 'CANCELLED' THEN r.registration_id END) AS sold,
               COUNT(DISTINCT CASE WHEN a.attendance_status = 'PRESENT' THEN a.attendance_id END) AS attended
        FROM event e
        LEFT JOIN registration r ON r.event_id = e.event_id
        LEFT JOIN ticket t       ON t.registration_id = r.registration_id
        LEFT JOIN attendance a   ON a.ticket_id = t.ticket_id
        GROUP BY e.event_id
        ORDER BY e.event_date DESC";
$result = mysqli_query($conn, $sql);
$count = mysqli_num_rows($result);

$total_sold = 0;
$total_attended = 0;
$total_revenue = 0;
?>
<main>
  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'approved') { ?>
    <p><strong>Event approved.</strong></p>
  <?php } ?>
  <section>
    <h2>Events at a glance</h2>
    <table>
      <caption>Registrations, attendance and expected ticket income for every event</caption>
      <thead>
        <tr><th>Event</th><th>Date</th><th>Status</th><th>Capacity</th><th>Tickets sold</th><th>Filled</th>
            <th>Checked in</th><th>Attendance</th><th>Ticket income</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php
      if ($count === 0) {
          echo '<tr><td colspan="10">No events yet.</td></tr>';
      }
      while ($row = mysqli_fetch_assoc($result)) {
          $revenue = $row['sold'] * $row['ticket_price'];
          $filled  = ($row['capacity'] > 0) ? round($row['sold'] / $row['capacity'] * 100) : 0;
          $rate    = ($row['sold'] > 0) ? round($row['attended'] / $row['sold'] * 100) . '%' : '-';

          $total_sold     += $row['sold'];
          $total_attended += $row['attended'];
          $total_revenue  += $revenue;

          echo '<tr>';
          echo '<td>' . e($row['event_name']) . '</td>';
          echo '<td>' . e($row['event_date']) . '</td>';
          echo '<td>' . e($row['event_status']) . '</td>';
          echo '<td>' . $row['capacity'] . '</td>';
          echo '<td>' . $row['sold'] . '</td>';
          echo '<td>' . $filled . '%</td>';
          echo '<td>' . $row['attended'] . '</td>';
          echo '<td>' . $rate . '</td>';
          echo '<td>KSh ' . number_format($revenue, 2) . '</td>';
          echo '<td><a href="budget.php?id=' . (int) $row['event_id'] . '">Budget</a>';
          if ($row['event_status'] === 'PENDING_APPROVAL') {
              echo ' <form action="process-approve.php" method="post" style="display:inline">'
                 . '<input type="hidden" name="event_id" value="' . (int) $row['event_id'] . '">'
                 . '<button type="submit">Approve</button></form>';
          }
          echo '</td>';
          echo '</tr>';
      }
      ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4">Totals</th>
          <td><?php echo $total_sold; ?></td>
          <td></td>
          <td><?php echo $total_attended; ?></td>
          <td></td>
          <td>KSh <?php echo number_format($total_revenue, 2); ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </section>
</main>
<?php include 'includes/footer.php'; ?>