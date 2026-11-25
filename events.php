<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page_title = 'Events';
include 'includes/header.php';

$result = mysqli_query($conn, "SELECT event_id, event_name, description, venue, event_date, start_time, registration_deadline
                               FROM event
                               WHERE event_status = 'APPROVED'
                               ORDER BY event_date, start_time");
$count = mysqli_num_rows($result);

// ticket types of one event, with how many are already taken
$type_stmt = mysqli_prepare($conn, "SELECT tt.ticket_type_id, tt.type_name, tt.ticket_price, tt.quantity,
        COUNT(r.registration_id) AS sold
    FROM ticket_type tt
    LEFT JOIN registration r ON r.ticket_type_id = tt.ticket_type_id AND r.registration_status <> 'CANCELLED'
    WHERE tt.event_id = ? AND tt.active = 1
    GROUP BY tt.ticket_type_id");

$messages = [
    'soldout'     => 'Sorry, that ticket type is sold out.',
    'duplicate'   => 'You already have a ticket for that event.',
    'unavailable' => 'That event is not open for registration.',
];
?>
<main>
  <p><?php echo $count; ?> events.</p>
  <?php if (isset($_GET['msg']) && isset($messages[$_GET['msg']])) { ?>
    <p><strong><?php echo $messages[$_GET['msg']]; ?></strong></p>
  <?php } ?>
<?php
if ($count === 0) {
    echo '<p>No events right now.</p>';
}

while ($row = mysqli_fetch_assoc($result)) {
    $open = strtotime($row['registration_deadline']) >= time();

    echo '<section>';
    echo '<h2>' . e($row['event_name']) . '</h2>';
    echo '<p>' . e($row['description']) . '</p>';
    echo '<p>' . date('D j M Y', strtotime($row['event_date']))
       . ', ' . date('g:i A', strtotime($row['start_time']))
       . ' at ' . e($row['venue']) . '</p>';
    echo '<p>Registration closes: ' . date('j M Y, g:i A', strtotime($row['registration_deadline'])) . '</p>';

    $eid = $row['event_id'];
    mysqli_stmt_bind_param($type_stmt, 'i', $eid);
    mysqli_stmt_execute($type_stmt);
    $types = mysqli_stmt_get_result($type_stmt);

    while ($t = mysqli_fetch_assoc($types)) {
        $left  = $t['quantity'] - $t['sold'];
        $price = ($t['ticket_price'] > 0) ? 'KSh ' . number_format($t['ticket_price'], 2) : 'Free';
        echo '<p>' . e($t['type_name']) . ': ' . $price . ' | ' . $left . ' of ' . $t['quantity'] . ' left</p>';

        if ($role === 'STUDENT' && $open && $left > 0) {
            echo '<form action="register-ticket.php" method="post">';
            echo '<input type="hidden" name="ticket_type_id" value="' . (int) $t['ticket_type_id'] . '">';
            echo '<button type="submit">Get my ticket</button>';
            echo '</form>';
        } elseif ($role === null) {
            echo '<p><a href="index.php">Log in</a> to get a ticket.</p>';
        } elseif (!$open) {
            echo '<p><strong>Registration closed</strong></p>';
        } elseif ($left <= 0) {
            echo '<p><strong>Sold out</strong></p>';
        }
    }
    echo '</section>';
}
?>
</main>
<?php include 'includes/footer.php'; ?>
