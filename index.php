<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page_title = 'Home';
include 'includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, password_hash, role FROM user WHERE email = ? AND active = 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            if ($user['role'] === 'STUDENT') {
                header('Location: events.php');
            } elseif ($user['role'] === 'ORGANIZER') {
                header('Location: organizer.php');
            } else {
                header('Location: management.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<main>
  <section>
    <h2>Discover. Register. Track.</h2>
    <p>Welcome to the Strathmore University Trace Events Tracker. Browse upcoming campus events, register for your student tickets, and track attendance seamlessly.</p>
    <p><a href="events.php" class="btn">Browse Events</a></p>
  </section>

  <?php if ($role === null) { ?>
    <section class="login-card">
      <h2>Log in</h2>
      <?php if ($error !== '') { ?>
        <p id="loginError" class="error"><?php echo e($error); ?></p>
      <?php } ?>
      <form action="index.php" method="post" id="loginForm">
        <label for="email">Strathmore Email</label>
        <input type="email" id="email" name="email" placeholder="student@strathmore.edu" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>

        <button type="submit" class="login-btn">Log in</button>
      </form>
    </section>
  <?php } else { ?>
    <section>
      <h2>Welcome back, <?php echo e($_SESSION['full_name'] ?? 'User'); ?>!</h2>
      <p>You are logged in as <strong><?php echo e($role); ?></strong>.</p>
      <p><a href="<?php echo $role_link[$role][0]; ?>" class="btn">Go to <?php echo $role_link[$role][1]; ?></a></p>
    </section>
  <?php } ?>
</main>

<aside>
  <h3>Quick info</h3>
  <p>Ticket sales close 24 hours before each event.</p>
  <p><a href="events.php">View upcoming events</a></p>
</aside>

<?php include 'includes/footer.php'; ?>
