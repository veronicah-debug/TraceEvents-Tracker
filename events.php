<?php
// events.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';

$search = trim($_GET['q'] ?? '');
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$sql = "SELECT e.event_id, e.event_name, e.description, e.event_category, e.venue,
               e.image_path, e.event_date, e.start_time, e.end_time, e.capacity,
               e.registration_deadline, e.ticket_price, c.club_name,
               COALESCE(v.confirmed_registrations, 0) AS confirmed_registrations,
               COALESCE(v.remaining_places, e.capacity) AS remaining_places
        FROM event e
        JOIN club c ON c.club_id = e.club_id
        LEFT JOIN vw_event_registration_summary v ON v.event_id = e.event_id
        WHERE e.event_status IN ('APPROVED', 'PUBLISHED')";

$params = [];
$types = '';
if ($search !== '') {
    $sql .= " AND (e.event_name LIKE ? OR e.venue LIKE ? OR c.club_name LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}
$sql .= " ORDER BY e.event_date ASC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch active ticket types for each listed event
$ticketTypesByEvent = [];
if (!empty($events)) {
    $ttStmt = $conn->prepare(
        "SELECT ticket_type_id, event_id, type_name, ticket_price, quantity
         FROM ticket_type
         WHERE active = 1"
    );
    $ttStmt->execute();
    foreach ($ttStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $tt) {
        $ticketTypesByEvent[(int)$tt['event_id']][] = $tt;
    }
    $ttStmt->close();
}

$pageTitle = 'Events';
include 'includes/header.php';
?>

<section class="section-pad">
  <div class="container">
    <div class="section-header">
      <h2>Campus Events &amp; Venues</h2>
      <form method="GET" action="events.php" style="display:flex; gap:8px;">
        <input type="text" name="q" value="<?= e($search) ?>" class="form-control"
               placeholder="Search by event, venue, or club..." />
        <button type="submit" class="btn btn-purple">Search</button>
      </form>
    </div>

    <?php if ($flashSuccess !== ''): ?>
      <div class="alert alert-success"><?= e($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
      <div class="alert alert-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <?php if (empty($events)): ?>
      <div class="card-panel"><p>No matching approved events found.</p></div>
    <?php else: ?>
      <div class="events-grid">
        <?php foreach ($events as $ev): ?>
          <?php
            $evId = (int)$ev['event_id'];
            $imgSrc = resolve_event_image($ev['image_path'], $ev['venue'], $ev['event_category']);
            $tTypes = $ticketTypesByEvent[$evId] ?? [];
            $placesLeft = (int)$ev['remaining_places'];
          ?>
          <article class="event-card" id="event-<?= $evId ?>">
            <div class="event-media">
              <img src="<?= e($imgSrc) ?>" alt="Event place: <?= e($ev['venue']) ?>" />
              <span class="event-place-badge">📍 <?= e($ev['venue']) ?></span>
            </div>
            <div class="event-body">
              <div class="event-meta">
                <strong><?= e($ev['club_name']) ?></strong> &bull;
                <?= e($ev['event_date']) ?> (<?= e(substr($ev['start_time'], 0, 5)) ?>)
              </div>
              <h3><?= e($ev['event_name']) ?></h3>
              <p><?= e($ev['description'] ?? '') ?></p>
              <div class="event-meta">
                Places left: <strong><?= max(0, $placesLeft) ?></strong> / <?= (int)$ev['capacity'] ?>
              </div>

              <?php if (($_SESSION['role'] ?? '') === 'STUDENT'): ?>
                <?php if ($placesLeft <= 0): ?>
                  <div class="alert alert-error" style="margin-bottom:0;">Sorry, this event is fully booked.</div>
                <?php elseif (!empty($tTypes)): ?>
                  <form action="register-ticket.php" method="POST" style="margin-top:auto;">
                    <input type="hidden" name="event_id" value="<?= $evId ?>" />
                    <div class="form-group" style="margin-bottom:8px;">
                      <select name="ticket_type_id" class="form-control" required>
                        <?php foreach ($tTypes as $tt): ?>
                          <option value="<?= (int)$tt['ticket_type_id'] ?>">
                            <?= e($tt['type_name']) ?> —
                            <?= ((float)$tt['ticket_price'] <= 0) ? 'Free' : 'KES ' . number_format((float)$tt['ticket_price'], 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <button type="submit" class="btn btn-gold btn-block">Register &amp; Get Ticket</button>
                  </form>
                <?php else: ?>
                  <span class="event-meta">Registration opens once ticket types are configured.</span>
                <?php endif; ?>
              <?php elseif (empty($_SESSION['user_id'])): ?>
                <a href="index.php#login-box" class="btn btn-purple btn-sm" style="margin-top:auto;">
                  Log in as Student to Register
                </a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>