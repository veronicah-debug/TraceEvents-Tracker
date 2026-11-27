<?php
// includes/header.php
$pageTitle = $pageTitle ?? 'Trace Events Tracker';
$currentRole = $_SESSION['role'] ?? null;
$currentName = $_SESSION['full_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle) ?> | Trace Events Tracker</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<header class="site-header">
  <div class="container nav-bar">
    <a href="index.php" class="brand">
      <img src="Images/strathmore-logo.png" alt="Strathmore University" class="brand-logo"
           onerror="this.style.display='none';" />
      <span>Trace Events Tracker</span>
    </a>

    <button type="button" class="nav-toggle" id="navToggle" aria-label="Toggle navigation">☰</button>

    <nav class="nav-links" id="navLinks">
      <a href="index.php">Home</a>
      <a href="events.php">Events</a>
      <?php if ($currentRole === 'STUDENT'): ?>
        <a href="my-tickets.php">My Tickets</a>
      <?php elseif ($currentRole === 'ORGANIZER'): ?>
        <a href="organizer.php">Organizer Portal</a>
      <?php elseif ($currentRole === 'MANAGEMENT'): ?>
        <a href="management.php">Management Portal</a>
      <?php endif; ?>

      <?php if ($currentName): ?>
        <span class="nav-user"><?= e($currentName) ?> (<?= e($currentRole) ?>)</span>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Log out</a>
      <?php else: ?>
        <a href="index.php#login-box" class="btn btn-sm btn-gold">Log in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="page-main">