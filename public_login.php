<?php
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/sms.php';
require_once 'includes/public_auth.php';

if (citizenLoggedIn()) {
    header('Location: public_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $raw = trim($_POST['identifier'] ?? '');
        if ($raw === '') {
            $error = 'Please enter your phone number or email.';
        } else {
            $normalized = normalizeIdentifier($raw);
            $result = requestOtp($conn, $normalized['value'], $normalized['channel']);
            if ($result['ok']) {
                header('Location: public_verify.php?identifier=' . urlencode($normalized['value']) . '&channel=' . urlencode($normalized['channel']));
                exit;
            }
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Dashboard - Login</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="hero-banner">
  <h2>My Dashboard</h2>
  <p>Log in with your phone number or email to track every certificate request you've made — no password needed.</p>
</div>

<div class="page-content" style="max-width:440px;">
  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>

  <form method="POST">
    <?php csrf_field(); ?>
    <label>Phone Number or Email</label>
    <input type="text" name="identifier" placeholder="+2519xxxxxxxx or you@example.com" required autofocus>
    <button type="submit">Send Login Code</button>
  </form>

  <p class="field-hint">We'll text or email you a 6-digit code — no password needed.</p>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
