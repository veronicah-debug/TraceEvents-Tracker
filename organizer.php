<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('ORGANIZER');
$page_title = 'Organizer';
include 'includes/header.php';

$messages = [
    'added'   => 'Event saved. It is waiting for management approval.',
    'invalid' => 'Please fill every field with valid values.',
    'noclub'  => 'Your account is not linked to a club yet, so you cannot create events.',
];
$categories = enum_values($conn, 'event', 'event_category');

$uid = $_SESSION['user_id'];
$sql = "SELECT e.event_id, e.event_name, e.event_date, e.capacity, e.event_status,
               COUNT(DISTINCT CASE WHEN r.registration_status <> 'CANCELLED' THEN r.registration_id END) AS sold,
               COUNT(DISTINCT CASE WHEN a.attendance_status = 'PRESENT' THEN a.attendance_id END) AS attended
        FROM event e
        LEFT JOIN registration r ON r.event_id = e.event_id
        LEFT JOIN ticket t       ON t.registration_id = r.registration_id
        LEFT JOIN attendance a   ON a.ticket_id = t.ticket_id
        WHERE e.created_by = ?
        GROUP BY e.event_id
        ORDER BY e.event_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$count = mysqli_num_rows($result);
?>
<main>
  <?php if (isset($_GET['msg']) && isset($messages[$_GET['msg']])) { ?>
    <p><strong><?php echo $messages[$_GET['msg']]; ?></strong></p>
  <?php } ?>

  <section>
    <h2>Add an event</h2>
    <form action="process-add-event.php" method="post">
      <label for="event_name">Event name</label>
      <input id="event_name" name="event_name" type="text" required>

      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" required></textarea>

      <label for="event_category">Category</label>
      <select id="event_category" name="event_category">
        <?php foreach ($categories as $c) { echo '<option value="' . e($c) . '">' . e($c) . '</option>'; } ?>
      </select>

      <label for="venue">Venue</label>
      <input id="venue" name="venue" type="text" required>

      <label for="event_date">Date</label>
      <input id="event_date" name="event_date" type="date" required>

      <label for="start_time">Start time</label>
      <input id="start_time" name="start_time" type="time" required>

      <label for="end_time">End time</label>
      <input id="end_time" name="end_time" type="time" required>

      <label for="capacity">Capacity (number of tickets)</label>
      <input id="capacity" name="capacity" type="number" min="1" required>

      <label for="registration_deadline">Registration deadline</label>
      <input id="registration_deadline" name="registration_deadline" type="datetime-local" required>

      <label for="ticket_price">Ticket price in KSh (0 = free)</label>
      <input id="ticket_price" name="ticket_price" type="number" min="0" step="0.01" value="0" required>

      <button type="submit">Save event</button>
    </form>
  </section>

  <section>
    <h2>Your events</h2>
    <table>
      <caption>Events you created</caption>
      <thead>
        <tr><th>Event</th><th>Date</th><th>Status</th><th>Tickets sold</th><th>Checked in</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php
      if ($count === 0) {
          echo '<tr><td colspan="6">You have not created any events yet.</td></tr>';
      } else {
          while ($row = mysqli_fetch_assoc($result)) {
              $id = (int) $row['event_id'];
              echo '<tr>';
              echo '<td>' . e($row['event_name']) . '</td>';
              echo '<td>' . e($row['event_date']) . '</td>';
              echo '<td>' . e($row['event_status']) . '</td>';
              echo '<td>' . $row['sold'] . ' / ' . $row['capacity'] . '</td>';
              echo '<td>' . $row['attended'] . '</td>';
              echo '<td><a href="checkin.php?id=' . $id . '">Check-in</a> | '
                 . '<a href="budget.php?id=' . $id . '">Budget</a></td>';
              echo '</tr>';
          }
      }
      ?>
      </tbody>
    </table>
  </section>
</main>
<?php include 'includes/footer.php'; ?>