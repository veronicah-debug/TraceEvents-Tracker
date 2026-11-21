<?php
require_once 'includes/auth.php';
$page_title = 'Home';
include 'includes/header.php';

$messages = [
    'login'  => 'Incorrect details. Check your email or ID and your password.',
    'access' => 'Please log in with an account that can open that page.',
];
?>
  <main>
    <section>
      <h2>Discover. Register. Track.</h2>
      <p>Trace Events Tracker helps Strathmore University students discover campus events and keep track of their event registrations and tickets.</p>
    </section>

    <?php if (is_logged_in()) { ?>
      <section>
        <h2>Welcome back, <?php echo e($_SESSION['full_name']); ?></h2>
        <p><a href="<?php echo role_home($_SESSION['role']); ?>">Go to your page</a></p>
      </section>
    <?php } else { ?>
      <section class="login-card">
        <h2>Log in</h2>
        <p>Enter your details and we will take you to the right place.</p>
        <?php if (isset($_GET['error']) && isset($messages[$_GET['error']])) { ?>
          <p id="loginError"><?php echo $messages[$_GET['error']]; ?></p>
        <?php } ?>
        <form action="process-login.php" method="post">
          <label for="login_id">Admission number, email or staff ID</label>
          <input id="login_id" name="login_id" type="text" autocomplete="username" required>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="submit" class="login-btn">Log in</button>
        </form>
      </section>
    <?php } ?>

    <section>
      <h2>For more information</h2>
      <p>For more information about the events or to register, please visit our <a href="contact.php">Contact</a> page.</p>
    </section>
  </main>
<?php include 'includes/footer.php'; ?>