<?php
// index.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' . role_home($_SESSION['role']));
    exit;
}

$errorMsg = '';
if (isset($_GET['error']) && $_GET['error'] === 'access') {
    $errorMsg = 'Access denied. Please sign in with an authorized account.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'invalid') {
    $errorMsg = 'Invalid email/ID or password. Please try again.';
} elseif (isset($_GET['error']) && $_GET['error'] === 'empty') {
    $errorMsg = 'Please enter your university email/ID and password.';
}

$stmt = $conn->prepare(
    "SELECT event_id, event_name, description, event_category, venue, image_path,
            event_date, start_time, ticket_price
     FROM event
     WHERE event_status IN ('APPROVED', 'PUBLISHED')
     ORDER BY event_date ASC
     LIMIT 6"
);
$stmt->execute();
$upcomingEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Home';
include 'includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <h1>Discover. Register.<br /><span>Track.</span></h1>
      <p>
        Browse upcoming Strathmore campus events, check event venue details,
        track your digital ticket status, and support structured event budget planning.
      </p>
      <a href="events.php" class="btn btn-gold">Browse Events</a>
    </div>

    <div class="login-card" id="login-box">
      <h2>Log in</h2>
      <?php if ($errorMsg !== ''): ?>
        <div class="alert alert-error"><?= e($errorMsg) ?></div>
      <?php endif; ?>

      <form action="process-login.php" method="POST">
        <div class="form-group">
          <label for="login_id">Strathmore Email / Admission / ID</label>
          <input type="text" id="login_id" name="login_id" class="form-control"
                 placeholder="e.g. amina.kamau@example.test or DEMO/001" required />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="Enter your password" required />
        </div>

        <button type="submit" class="btn btn-purple btn-block">Log in</button>
      </form>
    </div>
  </div>
</section>

<section class="section-pad">
  <div class="container">
    <div class="section-header">
      <h2>Upcoming Events &amp; Venues</h2>
      <a href="events.php" class="btn btn-sm btn-purple">View All Events</a>
    </div>

    <?php if (empty($upcomingEvents)): ?>
      <div class="card-panel"><p>No approved events scheduled right now.</p></div>
    <?php else: ?>
      <div class="events-grid">
        <?php foreach ($upcomingEvents as $ev): ?>
          <?php $imgSrc = resolve_event_image($ev['image_path'], $ev['venue'], $ev['event_category']); ?>
          <article class="event-card">
            <div class="event-media">
              <img src="<?= e($imgSrc) ?>" alt="Venue: <?= e($ev['venue']) ?> - <?= e($ev['event_name']) ?>" />
              <span class="event-place-badge">📍 <?= e($ev['venue']) ?></span>
            </div>
            <div class="event-body">
              <div class="event-meta">
                <?= e($ev['event_date']) ?> &bull; <?= e(substr($ev['start_time'], 0, 5)) ?>
              </div>
              <h3><?= e($ev['event_name']) ?></h3>
              <p><?= e($ev['description'] ?? 'Campus student event.') ?></p>
              <div class="event-price">
                <?= ((float)$ev['ticket_price'] <= 0) ? 'Free Entry' : 'KES ' . number_format((float)$ev['ticket_price'], 2) ?>
              </div>
              <a href="events.php#event-<?= (int)$ev['event_id'] ?>" class="btn btn-gold btn-sm">
                View &amp; Register
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
