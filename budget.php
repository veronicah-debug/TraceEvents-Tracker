<?php
// budget.php - cost lines for one event, and the estimate worked out from them
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['ORGANIZER', 'MANAGEMENT'], true)) {
    header('Location: index.php?error=access');
    exit;
}
$uid      = $_SESSION['user_id'];
$role     = $_SESSION['role'];
$event_id = (int) ($_POST['event_id'] ?? $_GET['id'] ?? 0);

function ksh($amount) {
    return 'KSh ' . number_format($amount, 2);
}

// load the event: an organizer may open only their own, management may open any
$stmt = mysqli_prepare($conn, 'SELECT created_by, event_name, capacity, ticket_price FROM event WHERE event_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$event || ($role === 'ORGANIZER' && $event['created_by'] != $uid)) {
    header('Location: ' . role_home($role));
    exit;
}
$price = (float) $event['ticket_price'];

// the expected attendance saved earlier (0 if none yet)
function get_expected($conn, $event_id) {
    $stmt = mysqli_prepare($conn, 'SELECT expected_attendance FROM budget_estimate WHERE event_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row ? (int) $row['expected_attendance'] : 0;
}

// recalculate the estimate from the cost lines and save it in budget_estimate
function save_estimate($conn, $event_id, $uid, $price, $expected) {
    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(estimated_cost), 0) AS total FROM budget_item WHERE event_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $total = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];

    $income     = $price * $expected;
    $balance    = $income - $total;
    $break_even = ($price > 0) ? (int) ceil($total / $price) : 0;

    $stmt = mysqli_prepare($conn, 'SELECT budget_estimate_id FROM budget_estimate WHERE event_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    mysqli_stmt_execute($stmt);
    $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($exists) {
        $stmt = mysqli_prepare($conn, 'UPDATE budget_estimate SET prepared_by = ?, expected_attendance = ?, ticket_price = ?,
                total_cost = ?, expected_income = ?, break_even_attendance = ?, projected_balance = ?, estimate_date = NOW()
                WHERE event_id = ?');
        mysqli_stmt_bind_param($stmt, 'iidddidi', $uid, $expected, $price, $total, $income, $break_even, $balance, $event_id);
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO budget_estimate (event_id, prepared_by, expected_attendance, ticket_price,
                total_cost, expected_income, break_even_attendance, projected_balance, estimate_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        mysqli_stmt_bind_param($stmt, 'iiidddid', $event_id, $uid, $expected, $price, $total, $income, $break_even, $balance);
    }
    mysqli_stmt_execute($stmt);
}

$categories = enum_values($conn, 'budget_item', 'item_category');

// ADD a cost line (organizers only)
if ($role === 'ORGANIZER' && isset($_POST['item_name'])) {
    $name = trim($_POST['item_name']);
    $cat  = $_POST['item_category'];
    $cost = (float) $_POST['estimated_cost'];
    if ($name !== '' && $cost > 0 && in_array($cat, $categories, true)) {
        $stmt = mysqli_prepare($conn, 'INSERT INTO budget_item (event_id, entered_by, item_name, item_category, estimated_cost)
                                       VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iissd', $event_id, $uid, $name, $cat, $cost);
        mysqli_stmt_execute($stmt);
        save_estimate($conn, $event_id, $uid, $price, get_expected($conn, $event_id));
    }
    header('Location: budget.php?id=' . $event_id);   // reload: a refresh cannot re-send the form
    exit;
}

// SAVE expected attendance (organizers only)
if ($role === 'ORGANIZER' && isset($_POST['expected_attendance'])) {
    $expected = max(0, (int) $_POST['expected_attendance']);
    save_estimate($conn, $event_id, $uid, $price, $expected);
    header('Location: budget.php?id=' . $event_id);
    exit;
}

// the lines, adding up the total as we go
$stmt = mysqli_prepare($conn, 'SELECT item_category, item_name, estimated_cost FROM budget_item
                               WHERE event_id = ? ORDER BY budget_item_id');
mysqli_stmt_bind_param($stmt, 'i', $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$lines = [];
$total_cost = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $lines[] = $row;
    $total_cost += $row['estimated_cost'];
}

// THE ESTIMATE
$expected   = get_expected($conn, $event_id);
$income     = $price * $expected;
$balance    = $income - $total_cost;
$break_even = ($price > 0) ? (int) ceil($total_cost / $price) : null;

if ($break_even === null) {
    $verdict = 'Free event: there is no ticket income, so the costs must be covered by other funds.';
} elseif ($break_even > $event['capacity']) {
    $verdict = 'Even a full house would not cover the costs.';
} elseif ($break_even > $expected) {
    $verdict = 'Expected attendance is below break-even: a deficit is projected.';
} else {
    $verdict = 'Expected attendance covers the costs.';
}

$page_title = 'Budget';
include 'includes/header.php';
?>
<main>
  <section>
    <h2>Budget: <?php echo e($event['event_name']); ?></h2>
    <table>
      <caption>Cost lines</caption>
      <thead><tr><th>Category</th><th>Item</th><th>Amount</th></tr></thead>
      <tbody>
      <?php
      if (count($lines) === 0) {
          echo '<tr><td colspan="3">No cost lines yet.</td></tr>';
      }
      foreach ($lines as $line) {
          echo '<tr><td>' . e($line['item_category']) . '</td><td>' . e($line['item_name']) . '</td><td>'
             . ksh($line['estimated_cost']) . '</td></tr>';
      }
      ?>
      </tbody>
    </table>
  </section>

  <section>
    <h2>The estimate</h2>
    <table>
      <tbody>
        <tr><th>Total cost</th><td><?php echo ksh($total_cost); ?></td></tr>
        <tr><th>Ticket price</th><td><?php echo ksh($price); ?></td></tr>
        <tr><th>Expected attendance</th><td><?php echo $expected; ?></td></tr>
        <tr><th>Expected ticket income</th><td><?php echo ksh($income); ?></td></tr>
        <tr><th>Projected surplus or deficit</th>
            <td><?php echo ($balance >= 0 ? '+' : '-') . ksh(abs($balance)); ?></td></tr>
        <tr><th>Break-even attendance</th>
            <td><?php echo ($break_even === null) ? 'Free event' : $break_even . ' tickets'; ?></td></tr>
      </tbody>
    </table>
    <p><strong><?php echo $verdict; ?></strong></p>
  </section>

  <?php if ($role === 'ORGANIZER') { ?>
  <section>
    <h2>Add a cost line</h2>
    <form action="budget.php" method="post">
      <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
      <label for="item_category">Category</label>
      <select id="item_category" name="item_category">
        <?php foreach ($categories as $c) { echo '<option value="' . e($c) . '">' . e($c) . '</option>'; } ?>
      </select>
      <label for="item_name">Item</label>
      <input id="item_name" name="item_name" type="text" required>
      <label for="estimated_cost">Amount (KSh)</label>
      <input id="estimated_cost" name="estimated_cost" type="number" min="0.01" step="0.01" required>
      <button type="submit">Add line</button>
    </form>
  </section>

  <section>
    <h2>Expected attendance</h2>
    <form action="budget.php" method="post">
      <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
      <label for="expected_attendance">How many people do you expect?</label>
      <input id="expected_attendance" name="expected_attendance" type="number" min="0" value="<?php echo $expected; ?>" required>
      <button type="submit">Save estimate</button>
    </form>
  </section>
  <?php } ?>

  <p><a href="<?php echo role_home($role); ?>">Back</a></p>
</main>
<?php include 'includes/footer.php'; ?>