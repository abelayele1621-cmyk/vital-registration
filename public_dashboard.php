<?php
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/public_auth.php';

requireCitizenLogin();

$identifier = $_SESSION['citizen_identifier'];
$channel = $_SESSION['citizen_channel'];

// Match on whichever field this citizen logged in with. A citizen who
// used the same phone/email across multiple requests (different
// certificate types, or requests made on behalf of different people)
// sees all of them here.
if ($channel === 'phone') {
    $stmt = $conn->prepare("SELECT * FROM requests WHERE applicant_phone = ? ORDER BY created_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM requests WHERE applicant_email = ? ORDER BY created_at DESC");
}
$stmt->bind_param("s", $identifier);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Dashboard</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <div class="dashboard-topbar">
    <h2>My Dashboard</h2>
    <div class="whoami">
      Logged in as <?php echo htmlspecialchars($identifier); ?>
      &mdash; <a href="public_logout.php">Log out</a>
    </div>
  </div>

  <p><a href="request.php">+ Submit a new certificate request</a></p>

  <?php if (count($requests) === 0): ?>
    <p>No certificate requests found for this <?php echo $channel === 'phone' ? 'phone number' : 'email'; ?> yet.</p>
  <?php endif; ?>

  <?php foreach ($requests as $req): ?>
    <?php
      $borderClass = '';
      if ($req['request_status'] === 'approved') { $borderClass = 'status-approved-border'; }
      elseif ($req['request_status'] === 'rejected') { $borderClass = 'status-rejected-border'; }
      elseif ($req['request_status'] === 'needs_revision') { $borderClass = 'status-needs_revision-border'; }
    ?>
    <div class="req-card <?php echo $borderClass; ?>">
      <div class="req-card-top">
        <h3><?php echo htmlspecialchars(ucfirst($req['certificate_type'])); ?> Certificate &mdash; #<?php echo (int)$req['id']; ?></h3>
        <span class="status-<?php echo htmlspecialchars($req['request_status']); ?>">
          <?php echo htmlspecialchars(ucfirst($req['request_status'])); ?>
        </span>
      </div>
      <p class="req-meta">
        For: <?php echo htmlspecialchars($req['person_full_name']); ?>
        &mdash; Submitted <?php echo htmlspecialchars($req['created_at']); ?>
        &mdash; Payment: <?php echo htmlspecialchars($req['payment_status']); ?>
        <?php if (!empty($req['branch_office'])): ?>
          &mdash; Branch: <?php echo htmlspecialchars($req['branch_office']); ?>
        <?php endif; ?>
      </p>
      <?php if (!empty($req['expiry_date']) && $req['request_status'] === 'approved'): ?>
        <p class="req-meta">Certificate valid until <?php echo htmlspecialchars($req['expiry_date']); ?></p>
      <?php endif; ?>

      <p class="req-meta"><a href="book_appointment.php?request_id=<?php echo (int)$req['id']; ?>&applicant_id_number=<?php echo urlencode($req['applicant_id_number']); ?>">Book an office appointment for this request &rarr;</a></p>

      <?php if ($req['request_status'] === 'approved'): ?>
        <form action="download.php" method="POST" target="_blank" style="margin-top:8px;">
          <input type="hidden" name="id" value="<?php echo (int)$req['id']; ?>">
          <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($req['applicant_id_number']); ?>">
          <button type="submit" class="download-link">Download Certificate</button>
        </form>
      <?php elseif ($req['request_status'] === 'rejected'): ?>
        <p class="req-meta">This request was rejected. Contact the city office for details.</p>
      <?php elseif ($req['request_status'] === 'needs_revision'): ?>
        <?php
          $noteStmt = $conn->prepare("SELECT note FROM request_notes WHERE request_id = ? AND requires_revision = 1 ORDER BY created_at DESC LIMIT 1");
          $noteStmt->bind_param("i", $req['id']);
          $noteStmt->execute();
          $noteRow = $noteStmt->get_result()->fetch_assoc();
          $noteStmt->close();
        ?>
        <div class="revision-notice">
          <p><strong>Action needed:</strong> "<?php echo htmlspecialchars($noteRow['note'] ?? 'Please contact the city office for details.'); ?>"</p>
        </div>
        <form action="resubmit_document.php" method="POST" enctype="multipart/form-data" style="margin-top:8px;">
          <input type="hidden" name="request_id" value="<?php echo (int)$req['id']; ?>">
          <input type="file" name="applicant_id_document" accept=".jpg,.jpeg,.png,.pdf" required>
          <button type="submit">Resubmit Document</button>
        </form>
      <?php else: ?>
        <p class="req-meta">Still under review.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
