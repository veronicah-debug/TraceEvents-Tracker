<?php
// my-tickets.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('STUDENT');

$studentId = (int)$_SESSION['user_id'];
$flashSuccess = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$stmt = $conn->prepare(
    "SELECT v.registration_id, v.registration_status, v.ticket_id, v.qr_reference,
            v.ticket_status, v.issued_at, e.event_name, e.event_date, e.start_time,
            e.venue, e.image_path, e.event_category, tt.type_name
     FROM vw_student_ticket_status v
     JOIN event e ON e.event_id = v.event_id
     JOIN registration r ON r.registration_id = v.registration_id
     JOIN ticket_type tt ON tt.ticket_type_id = r.ticket_type_id
     WHERE v.user_id = ?
     ORDER BY v.issued_at DESC"
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'My Tickets';
include 'includes/header.php';
?>

<section class="section-pad">
  <div class="container">
    <div class="section-header">
      <h2>My Registrations &amp; Digital Tickets</h2>
      <a href="events.php" class="btn btn-sm btn-purple">Browse More Events</a>
    </div>

    <?php if ($flashSuccess !== ''): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if (empty($tickets)): ?>
      <div class="card-panel">
        <p>You have no event tickets yet. Visit <a href="events.php">Events</a> to register.</p>
      </div>
    <?php else: ?>
      <div class="events-grid">
        <?php foreach ($tickets as $t): ?>
          <?php $imgSrc = resolve_event_image($t['image_path'], $t['venue'], $t['event_category']); ?>
          <article class="event-card">
            <div class="event-media">
              <img src="<?= e($imgSrc) ?>" alt="Venue: <?= e($t['venue']) ?>" />
              <span class="event-place-badge">📍 <?= e($t['venue']) ?></span>
            </div>
            <div class="event-body">
              <h3><?= e($t['event_name']) ?></h3>
              <div class="event-meta">
                <?= e($t['event_date']) ?> &bull; <?= e(substr($t['start_time'], 0, 5)) ?><br />
                Ticket Type: <strong><?= e($t['type_name']) ?></strong>
              </div>
              <p>
                Registration: <strong><?= e($t['registration_status']) ?></strong><br />
                Ticket Status: <strong><?= e($t['ticket_status'] ?? 'PENDING') ?></strong>
              </p>
              <?php if (!empty($t['qr_reference'])): ?>
                <div style="background:#f5f0f7; padding:10px; border-radius:8px; font-family:monospace; font-size:0.88rem;">
                  QR Code Ref:<br />
                  <strong><?= e($t['qr_reference']) ?></strong>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>