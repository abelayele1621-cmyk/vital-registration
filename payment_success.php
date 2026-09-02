<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Successful</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content" style="text-align:center;">
  <?php if (!empty($_GET['exempt'])): ?>
    <h2 class="success">Request Submitted — Fee Waived</h2>
    <p>Thank you. Your certificate request qualifies for a fee exemption and is now being processed by the city office. No payment is required, but staff will verify the exemption before approving your certificate.</p>
  <?php else: ?>
    <h2 class="success">Payment Received</h2>
    <p>Thank you. Your certificate request is now being processed by the city office.</p>
  <?php endif; ?>
  <?php if (isset($_GET['id'])): ?>
    <p><strong>Your Request ID is: <?php echo (int)$_GET['id']; ?></strong></p>
    <p>Please save this number &mdash; you'll need it (along with your National ID Number) to check your request status.</p>
  <?php endif; ?>
  <p><a href="status.php">Check your request status</a></p>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
