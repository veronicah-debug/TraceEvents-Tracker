<?php
// includes/header.php - the top of every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? null;   // null = nobody logged in

// the one extra nav link each role gets: [page, label]
$role_link = [
    'STUDENT'    => ['my-tickets.php', 'My Tickets'],
    'ORGANIZER'  => ['organizer.php',  'Organizer'],
    'MANAGEMENT' => ['management.php', 'Management'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?> | Trace Events Tracker</title>
  <link rel="preconnect" href="https://googleapis.com">
  <link rel="preconnect" href="https://gstatic.com" crossorigin>
  <link href="https://googleapis.com/css2?family=Source+Serif+4:ital,opsz,wght@0,8..60,200..900;1,8..60,200..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <script src="js/main.js" defer></script>
  <link rel="icon" type="image/png" href="Images/University-Logo-Vertical-small-2.png">
</head>
<body>

  <header>
    <h1>
      <img class="logo" src="Images/University-Logo-Vertical-small-2.png" alt="Strathmore University logo">
      Trace Events Tracker
    </h1>
  
  </header>

  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="events.php">Events</a></li>
      <li><a href="contact.php">Contact</a></li>
      <?php if ($role !== null) { ?>
        <li><a href="<?php echo $role_link[$role][0]; ?>"><?php echo $role_link[$role][1]; ?></a></li>
        <li><a href="logout.php">Log out</a></li>
      <?php } ?>
    </ul>
  </nav>
