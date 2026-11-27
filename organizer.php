<?php
// organizer.php
declare(strict_types=1);
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_role('ORGANIZER');

$userId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Fetch clubs this organizer belongs to
$clubStmt = $conn->prepare(
    "SELECT c.club_id, c.club_name
     FROM club_member cm
     JOIN club c ON c.club_id = cm.club_id
     WHERE cm.user_id = ? AND cm.active = 1 AND c.status = 'ACTIVE'"
);
$clubStmt->bind_param('i', $userId);
$clubStmt->execute();
$clubs = $clubStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$clubStmt->close();

$clubIds = array_column($clubs, 'club_id');

// Helper to handle safe event place/banner image upload
function handle_event_image_upload(array $file, string &$errOut): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // Optional upload; fallback image will be used
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errOut = 'Image upload failed. Please try another file.';
        return null;
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        $errOut = 'Event place image must be 3 MB or smaller.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        $errOut = 'Only JPG, PNG, or WEBP images are allowed for event places.';
        return null;
    }

    $uploadDir = __DIR__ . '/Images/events';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = 'place_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $targetAbs = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
        $errOut = 'Could not save uploaded venue image.';
        return null;
    }

    return 'Images/events/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_event') {
        $clubId      = (int)($_POST['club_id'] ?? 0);
        $eventName   = trim($_POST['event_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category    = $_POST['event_category'] ?? 'OTHER';
        $venue       = trim($_POST['venue'] ?? '');
        $eventDate   = trim($_POST['event_date'] ?? '');
        $startTime   = trim($_POST['start_time'] ?? '');
        $capacity    = (int)($_POST['capacity'] ?? 0);
        $ticketPrice = (float)($_POST['ticket_price'] ?? 0);

        if (!in_array($clubId, $clubIds, true)) {
            $error = 'Your account is not linked to this club, so you cannot create events for it.';
        } elseif ($eventName === '' || $venue === '' || $eventDate === '' || $startTime === '' || $capacity <= 0 || $ticketPrice < 0) {
            $error = 'Please fill every field with valid values.';
        } else {
            $uploadedPath = handle_event_image_upload($_FILES['place_image'] ?? [], $error);
            if ($error === '') {
                $stmt = $conn->prepare(
                    "INSERT INTO event
                     (club_id, created_by, event_name, description, event_category, venue, image_path,
                      event_date, start_time, capacity, ticket_price, event_status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING_APPROVAL')"
                );
                $stmt->bind_param(
                    'iisssssssid',
                    $clubId,
                    $userId,
                    $eventName,
                    $description,
                    $category,
                    $venue,
                    $uploadedPath,
                    $eventDate,
                    $startTime,
                    $capacity,
                    $ticketPrice
                );
                $stmt->execute();
                $newEventId = (int)$conn->insert_id;
                $stmt->close();

                // Create default ticket type so students can register once approved
                $ttStmt = $conn->prepare(
                    "INSERT INTO ticket_type (event_id, type_name, ticket_price, quantity, description)
                     VALUES (?, 'General Admission', ?, ?, 'Standard admission')"
                );
                $ttStmt->bind_param('idi', $newEventId, $ticketPrice, $capacity);
                $ttStmt->execute();
                $ttStmt->close();

                log_access($conn, $userId, 'CREATE_EVENT', 'EVENT', $newEventId, 'SUCCESS');
                $message = 'Event saved. It is waiting for management approval.';
            }
        }
    } elseif ($action === 'update_place_image') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $newVenue = trim($_POST['venue'] ?? '');

        $ownStmt = $conn->prepare("SELECT event_id FROM event WHERE event_id = ? AND created_by = ?");
        $ownStmt->bind_param('ii', $eventId, $userId);
        $ownStmt->execute();
        $owns = (bool)$ownStmt->get_result()->fetch_assoc();
        $ownStmt->close();

        if (!$owns) {
            $error = 'You can only update events you created.';
        } else {
            $uploadedPath = handle_event_image_upload($_FILES['place_image'] ?? [], $error);
            if ($error === '') {
                if ($uploadedPath !== null) {
                    $uStmt = $conn->prepare("UPDATE event SET venue = ?, image_path = ? WHERE event_id = ?");
                    $uStmt->bind_param('ssi', $newVenue, $uploadedPath, $eventId);
                } else {
                    $uStmt = $conn->prepare("UPDATE event SET venue = ? WHERE event_id = ?");
                    $uStmt->bind_param('si', $newVenue, $eventId);
                }
                $uStmt->execute();
                $uStmt->close();
                $message = 'Event place and image updated.';
            }
        }
    } elseif ($action === 'add_budget_item') {
        $eventId  = (int)($_POST['event_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $itemCat  = $_POST['item_category'] ?? 'OTHER';
        $cost     = (float)($_POST['estimated_cost'] ?? -1);

        if ($eventId <= 0 || $itemName === '' || $cost < 0) {
            $error = 'Enter a valid item name and non-negative cost.';
        } else {
            $bStmt = $conn->prepare(
                "INSERT INTO budget_item (event_id, entered_by, item_name, item_category, estimated_cost)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $bStmt->bind_param('iissd', $eventId, $userId, $itemName, $itemCat, $cost);
            $bStmt->execute();
            $bStmt->close();
            $message = 'Budget item added.';
        }
    } elseif ($action === 'estimate_budget') {
        $eventId  = (int)($_POST['event_id'] ?? 0);
        $expected = (int)($_POST['expected_attendance'] ?? -1);

        $evStmt = $conn->prepare("SELECT ticket_price FROM event WHERE event_id = ? AND created_by = ?");
        $evStmt->bind_param('ii', $eventId, $userId);
        $evStmt->execute();
        $evRow = $evStmt->get_result()->fetch_assoc();
        $evStmt->close();

        if (!$evRow || $expected < 0) {
            $error = 'Invalid event or expected attendance.';
        } else {
            $price = (float)$evRow['ticket_price'];
            $costStmt = $conn->prepare("SELECT COALESCE(SUM(estimated_cost), 0) AS total_cost FROM budget_item WHERE event_id = ?");
            $costStmt->bind_param('i', $eventId);
            $costStmt->execute();
            $totalCost = (float)($costStmt->get_result()->fetch_assoc()['total_cost'] ?? 0);
            $costStmt->close();

            $expectedIncome = $price * $expected;
            $projectedBalance = $expectedIncome - $totalCost;
            $breakEven = ($price > 0) ? (int)ceil($totalCost / $price) : null;

            $insStmt = $conn->prepare(
                "INSERT INTO budget_estimate
                 (event_id, prepared_by, expected_attendance, ticket_price, total_cost,
                  expected_income, break_even_attendance, projected_balance)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insStmt->bind_param(
                'iiidddid',
                $eventId,
                $userId,
                $expected,
                $price,
                $totalCost,
                $expectedIncome,
                $breakEven,
                $projectedBalance
            );
            $insStmt->execute();
            $insStmt->close();
            $message = 'Budget estimate saved.';
        }
    } elseif ($action === 'verify_ticket') {
        $qrRef = trim($_POST['qr_reference'] ?? '');
        $eventId = (int)($_POST['event_id'] ?? 0);

        $tStmt = $conn->prepare(
            "SELECT t.ticket_id, t.ticket_status, r.event_id, r.registration_status
             FROM ticket t
             JOIN registration r ON r.registration_id = t.registration_id
             WHERE t.qr_reference = ? AND r.event_id = ?
             LIMIT 1"
        );
        $tStmt->bind_param('si', $qrRef, $eventId);
        $tStmt->execute();
        $tkt = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();

        if (!$tkt || $tkt['ticket_status'] !== 'ISSUED' || $tkt['registration_status'] !== 'CONFIRMED') {
            $error = 'Invalid ticket. Entry denied.';
        } else {
            $conn->begin_transaction();
            $upd = $conn->prepare("UPDATE ticket SET ticket_status = 'USED' WHERE ticket_id = ?");
            $tid = (int)$tkt['ticket_id'];
            $upd->bind_param('i', $tid);
            $upd->execute();
            $upd->close();

            $att = $conn->prepare(
                "INSERT INTO attendance (ticket_id, event_id, checked_in_by, attendance_status)
                 VALUES (?, ?, ?, 'PRESENT')"
            );
            $att->bind_param('iii', $tid, $eventId, $userId);
            $att->execute();
            $att->close();
            $conn->commit();

            $message = 'Valid ticket! Attendance recorded and ticket marked USED.';
        }
    }
}

$myEvStmt = $conn->prepare(
    "SELECT e.*, c.club_name,
            COALESCE(rs.confirmed_registrations, 0) AS confirmed_registrations,
            COALESCE(ats.total_attended, 0) AS total_attended,
            COALESCE(bt.total_estimated_cost, 0) AS total_estimated_cost
     FROM event e
     JOIN club c ON c.club_id = e.club_id
     LEFT JOIN vw_event_registration_summary rs ON rs.event_id = e.event_id
     LEFT JOIN vw_event_attendance_summary ats ON ats.event_id = e.event_id
     LEFT JOIN vw_event_budget_totals bt ON bt.event_id = e.event_id
     WHERE e.created_by = ?
     ORDER BY e.created_at DESC"
);
$myEvStmt->bind_param('i', $userId);
$myEvStmt->execute();
$myEvents = $myEvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$myEvStmt->close();

$pageTitle = 'Organizer Dashboard';
include 'includes/header.php';
?>

<section class="section-pad">
  <div class="container">
    <h2>Event Organizer Dashboard</h2>

    <?php if ($message !== ''): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <!-- Create Event with Event Place Image -->
    <div class="card-panel">
      <h3>Create Event &amp; Upload Event Place Image</h3>
      <?php if (empty($clubs)): ?>
        <p class="alert alert-error">Your account is not linked to an active club yet, so you cannot create events.</p>
      <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="create_event" />
          <div class="form-grid-2">
            <div class="form-group">
              <label>Club</label>
              <select name="club_id" class="form-control" required>
                <?php foreach ($clubs as $cl): ?>
                  <option value="<?= (int)$cl['club_id'] ?>"><?= e($cl['club_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Event Name</label>
              <input type="text" name="event_name" class="form-control" required />
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="event_category" class="form-control">
                <option value="ACADEMIC">ACADEMIC</option>
                <option value="CULTURAL">CULTURAL</option>
                <option value="SOCIAL">SOCIAL</option>
                <option value="SPORTS">SPORTS</option>
                <option value="OTHER">OTHER</option>
              </select>
            </div>
            <div class="form-group">
              <label>Event Place / Venue</label>
              <input type="text" name="venue" class="form-control" placeholder="e.g. Strathmore Auditorium" required />
            </div>
            <div class="form-group">
              <label>Event Date</label>
              <input type="date" name="event_date" class="form-control" required />
            </div>
            <div class="form-group">
              <label>Start Time</label>
              <input type="time" name="start_time" class="form-control" required />
            </div>
            <div class="form-group">
              <label>Capacity</label>
              <input type="number" name="capacity" min="1" class="form-control" required />
            </div>
            <div class="form-group">
              <label>Ticket Price (KES, 0 for Free)</label>
              <input type="number" step="0.01" min="0" name="ticket_price" value="0.00" class="form-control" required />
            </div>
          </div>

          <div class="form-group">
            <label>Event Place / Venue Photo (JPG, PNG, WEBP — max 3MB)</label>
            <input type="file" name="place_image" accept="image/jpeg,image/png,image/webp" class="form-control" />
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="2" class="form-control"></textarea>
          </div>

          <button type="submit" class="btn btn-purple">Submit Event for Approval</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- My Events, Place Photos, Budget & Gate Check-In -->
    <div class="card-panel">
      <h3>My Events, Venue Photos, Budget &amp; Attendance</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Place Photo</th>
              <th>Event &amp; Venue</th>
              <th>Status</th>
              <th>Registrations / Attended</th>
              <th>Budget Cost</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myEvents as $ev): ?>
              <?php
                $eid = (int)$ev['event_id'];
                $imgSrc = resolve_event_image($ev['image_path'], $ev['venue'], $ev['event_category']);
              ?>
              <tr>
                <td><img src="<?= e($imgSrc) ?>" alt="<?= e($ev['venue']) ?>" class="thumb-sm" /></td>
                <td>
                  <strong><?= e($ev['event_name']) ?></strong><br />
                  📍 <?= e($ev['venue']) ?> &bull; <?= e($ev['event_date']) ?>
                  <?php if (!empty($ev['rejection_reason'])): ?>
                    <div class="alert alert-error" style="margin-top:6px; padding:6px 10px;">
                      Rejection reason: <?= e($ev['rejection_reason']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><?= e($ev['event_status']) ?></td>
                <td><?= (int)$ev['confirmed_registrations'] ?> reg / <?= (int)$ev['total_attended'] ?> attended</td>
                <td>KES <?= number_format((float)$ev['total_estimated_cost'], 2) ?></td>
                <td style="min-width:250px;">
                  <form method="POST" enctype="multipart/form-data" style="margin-bottom:8px;">
                    <input type="hidden" name="action" value="update_place_image" />
                    <input type="hidden" name="event_id" value="<?= $eid ?>" />
                    <input type="text" name="venue" value="<?= e($ev['venue']) ?>" class="form-control" style="margin-bottom:4px;" />
                    <input type="file" name="place_image" accept="image/jpeg,image/png,image/webp" class="form-control" style="margin-bottom:4px;" />
                    <button type="submit" class="btn btn-sm btn-outline-light" style="color:#1f1f24; border-color:#888;">Save Place / Photo</button>
                  </form>

                  <form method="POST" style="margin-bottom:8px; display:flex; gap:4px;">
                    <input type="hidden" name="action" value="verify_ticket" />
                    <input type="hidden" name="event_id" value="<?= $eid ?>" />
                    <input type="text" name="qr_reference" placeholder="QR code ref" class="form-control" required />
                    <button type="submit" class="btn btn-sm btn-gold">Check In</button>
                  </form>

                  <form method="POST" style="display:flex; gap:4px;">
                    <input type="hidden" name="action" value="estimate_budget" />
                    <input type="hidden" name="event_id" value="<?= $eid ?>" />
                    <input type="number" name="expected_attendance" min="0" placeholder="Exp. guests" class="form-control" required />
                    <button type="submit" class="btn btn-sm btn-purple">Estimate</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Budget Item -->
    <?php if (!empty($myEvents)): ?>
      <div class="card-panel">
        <h3>Add Budget Cost Item</h3>
        <form method="POST" class="form-grid-2">
          <input type="hidden" name="action" value="add_budget_item" />
          <div class="form-group">
            <label>Event</label>
            <select name="event_id" class="form-control">
              <?php foreach ($myEvents as $ev): ?>
                <option value="<?= (int)$ev['event_id'] ?>"><?= e($ev['event_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="item_category" class="form-control">
              <option value="VENUE">VENUE</option>
              <option value="TRANSPORT">TRANSPORT</option>
              <option value="FOOD">FOOD</option>
              <option value="PUBLICITY">PUBLICITY</option>
              <option value="EQUIPMENT">EQUIPMENT</option>
              <option value="ENTERTAINMENT">ENTERTAINMENT</option>
              <option value="OTHER">OTHER</option>
            </select>
          </div>
          <div class="form-group">
            <label>Item Name</label>
            <input type="text" name="item_name" class="form-control" required />
          </div>
          <div class="form-group">
            <label>Estimated Cost (KES)</label>
            <input type="number" step="0.01" min="0" name="estimated_cost" class="form-control" required />
          </div>
          <div class="form-group" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-purple">Add Budget Item</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>