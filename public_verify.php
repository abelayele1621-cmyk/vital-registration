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

$identifier = trim($_GET['identifier'] ?? $_POST['identifier'] ?? '');
$channel = trim($_GET['channel'] ?? $_POST['channel'] ?? '');
$allowedChannels = ['phone', 'email'];
$channel = in_array($channel, $allowedChannels, true) ? $channel : 'phone';

if ($identifier === '') {
    header('Location: public_login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        if ($code !== '' && verifyOtp($conn, $identifier, $code)) {
            citizenLogin($identifier, $channel);
            header('Location: public_dashboard.php');
            exit;
        }
        $error = 'That code is incorrect or has expired. Please try again or request a new one.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Enter Login Code</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content" style="max-width:440px;">
  <h2>Enter Your Code</h2>
  <p>We sent a 6-digit code to <strong><?php echo htmlspecialchars($identifier); ?></strong> via <?php echo $channel === 'phone' ? 'SMS' : 'email'; ?>.</p>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>

  <form method="POST">
    <?php csrf_field(); ?>
    <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($identifier); ?>">
    <input type="hidden" name="channel" value="<?php echo htmlspecialchars($channel); ?>">
    <label>6-Digit Code</label>
    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus>
    <button type="submit">Verify &amp; Log In</button>
  </form>

  <p class="field-hint">
    Didn't get a code? <a href="public_login.php">Start over</a> to request a new one
    (you can request a new code every <?php echo OTP_RESEND_COOLDOWN_SECONDS; ?> seconds).
  </p>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
