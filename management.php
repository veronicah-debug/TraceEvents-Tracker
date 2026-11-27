<?php
// management.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('MANAGEMENT');

$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId  = (int)($_POST['event_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $note     = trim($_POST['decision_note'] ?? '');

    if ($eventId <= 0 || !in_array($decision, ['APPROVED', 'REJECTED'], true)) {
        $error = 'Invalid approval action.';
    } elseif ($decision === 'REJECTED' && $note === '') {
        $error = 'Please enter a reason for rejecting this event.';
    } else {
        $newStatus = ($decision === 'APPROVED') ? 'APPROVED' : 'REJECTED';
        $reasonVal = ($decision === 'REJECTED') ? $note : null;

        $conn->begin_transaction();
        $upStmt = $conn->prepare(
            "UPDATE event SET event_status = ?, rejection_reason = ? WHERE event_id = ?"
        );
        $upStmt->bind_param('ssi', $newStatus, $reasonVal, $eventId);
        $upStmt->execute();
        $upStmt->close();

        $apStmt = $conn->prepare(
            "INSERT INTO event_approval (event_id, approved_by, decision, decision_note)
             VALUES (?, ?, ?, ?)"
        );
        $apStmt->bind_param('iiss', $eventId, $userId, $decision, $note);
        $apStmt->execute();
        $apStmt->close();
        $conn->commit();

        log_access($conn, $userId, $decision . '_EVENT', 'EVENT', $eventId, 'SUCCESS', $note);
        $message = 'Event marked as ' . $newStatus . '.';
    }
}

$sql = "SELECT e.event_id, e.event_name, e.venue, e.image_path, e.event_category,
               e.event_date, e.ticket_price, e.event_status, c.club_name,
               COALESCE(rs.confirmed_registrations, 0) AS confirmed_registrations,
               COALESCE(ats.total_attended, 0) AS total_attended,
               COALESCE(bt.total_estimated_cost, 0) AS total_estimated_cost,
               (SELECT expected_income FROM budget_estimate be WHERE be.event_id = e.event_id ORDER BY be.estimate_date DESC LIMIT 1) AS expected_income,
               (SELECT break_even_attendance FROM budget_estimate be WHERE be.event_id = e.event_id ORDER BY be.estimate_date DESC LIMIT 1) AS break_even_attendance,
               (SELECT projected_balance FROM budget_estimate be WHERE be.event_id = e.event_id ORDER BY be.estimate_date DESC LIMIT 1) AS projected_balance
        FROM event e
        JOIN club c ON c.club_id = e.club_id
        LEFT JOIN vw_event_registration_summary rs ON rs.event_id = e.event_id
        LEFT JOIN vw_event_attendance_summary ats ON ats.event_id = e.event_id
        LEFT JOIN vw_event_budget_totals bt ON bt.event_id = e.event_id
        ORDER BY e.event_date ASC";

$summaryRows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Management Reports & Approvals';
include 'includes/header.php';
?>

<section class="section-pad">
  <div class="container">
    <h2>Authorized Management — Approvals, Budgets &amp; Attendance</h2>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="card-panel">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Place</th>
              <th>Event &amp; Club</th>
              <th>Status</th>
              <th>Reg / Attended</th>
              <th>Budget &amp; Break-Even</th>
              <th>Approval Decision</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($summaryRows as $row): ?>
              <?php
                $eid = (int)$row['event_id'];
                $imgSrc = resolve_event_image($row['image_path'], $row['venue'], $row['event_category']);
              ?>
              <tr>
                <td><img src="<?= e($imgSrc) ?>" alt="<?= e($row['venue']) ?>" class="thumb-sm" /></td>
                <td>
                  <strong><?= e($row['event_name']) ?></strong><br />
                  <?= e($row['club_name']) ?> &bull; 📍 <?= e($row['venue']) ?>
                </td>
                <td><?= e($row['event_status']) ?></td>
                <td><?= (int)$row['confirmed_registrations'] ?> / <?= (int)$row['total_attended'] ?></td>
                <td>
                  Cost: KES <?= number_format((float)$row['total_estimated_cost'], 2) ?><br />
                  Est. Income: <?= $row['expected_income'] !== null ? 'KES ' . number_format((float)$row['expected_income'], 2) : '—' ?><br />
                  Break-even: <?= $row['break_even_attendance'] !== null ? (int)$row['break_even_attendance'] . ' attendees' : '—' ?><br />
                  Balance: <?= $row['projected_balance'] !== null ? 'KES ' . number_format((float)$row['projected_balance'], 2) : '—' ?>
                </td>
                <td style="min-width:220px;">
                  <form method="POST">
                    <input type="hidden" name="event_id" value="<?= $eid ?>" />
                    <input type="text" name="decision_note" placeholder="Reason (required if rejecting)" class="form-control" style="margin-bottom:6px;" />
                    <div style="display:flex; gap:6px;">
                      <button type="submit" name="decision" value="APPROVED" class="btn btn-sm btn-gold">Approve</button>
                      <button type="submit" name="decision" value="REJECTED" class="btn btn-sm btn-purple">Reject</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>