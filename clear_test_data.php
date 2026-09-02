<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$cleared = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes' && verify_csrf()) {
    // Delete all requests
    $conn->query("DELETE FROM requests");
    $conn->query("ALTER TABLE requests AUTO_INCREMENT = 1");

    // Delete all generated certificate PDFs
    $files = glob(__DIR__ . '/certificates/*.pdf');
    foreach ($files as $file) {
        unlink($file);
    }

    $cleared = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Clear Test Data - Admin</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content">
  <h2>Clear All Test Data</h2>

  <?php if ($cleared): ?>
    <p class="success">All test requests and certificates have been deleted. The database is now empty and ready for real use.</p>
  <?php else: ?>
    <p class="error"><strong>Warning:</strong> This will permanently delete <u>all</u> certificate requests and generated PDF certificates in the system. This cannot be undone.</p>
    <p>Only do this once you're done testing and ready to hand the site over for real use.</p>
    <form method="POST" onsubmit="return confirm('Are you absolutely sure? This deletes everything permanently.');">
      <?php csrf_field(); ?>
      <input type="hidden" name="confirm" value="yes">
      <button type="submit">Yes, Delete All Test Data</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:20px;"><a href="admin.php">&larr; Back to Admin Panel</a></p>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
