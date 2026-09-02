<?php
require_once 'includes/db.php';

$request = null;
$notFound = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['request_id'] ?? 0);
    $id_number = trim($_POST['applicant_id_number'] ?? '');

    if ($id && $id_number) {
        $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ? AND applicant_id_number = ?");
        $stmt->bind_param("is", $id, $id_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();

        if (!$request) {
            $notFound = true;
        }
    }
}

// Build the 5-stage timeline: Submitted -> SMS Verified -> Fee Paid ->
// Under Review -> Approved & Ready (or Rejected, shown as its own state).
function buildTimeline(array $request): array {
    $rejected = $request['request_status'] === 'rejected';
    $approved = $request['request_status'] === 'approved';
    $needsRevision = $request['request_status'] === 'needs_revision';
    $paid = $request['payment_status'] === 'paid';
    $smsOk = (int)$request['sms_verified'] === 1;

    $stages = [
        ['label' => 'Submitted',        'done' => true],
        ['label' => 'SMS Verified',     'done' => $smsOk],
        ['label' => 'Fee Paid',         'done' => $paid],
        ['label' => $needsRevision ? 'Action Needed' : 'Under Review', 'done' => $paid && !$rejected],
        ['label' => $rejected ? 'Rejected' : 'Approved & Ready', 'done' => $approved || $rejected],
    ];

    // Mark exactly one stage "current" — the first not-done stage (or the
    // last stage if everything is done / rejected).
    $currentIndex = null;
    foreach ($stages as $i => $stage) {
        if (!$stage['done']) { $currentIndex = $i; break; }
    }
    if ($currentIndex === null) { $currentIndex = count($stages) - 1; }
    $stages[$currentIndex]['current'] = true;

    return ['stages' => $stages, 'rejected' => $rejected];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Check Certificate Request Status</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content">
  <h2>Check Your Request Status</h2>
  <p>Enter your Request ID (given when you submitted) and your National ID Number to check your status.</p>

  <form method="POST" class="no-print">
    <label>Request ID</label>
    <input type="number" name="request_id" required value="<?php echo htmlspecialchars($_POST['request_id'] ?? ''); ?>">

    <label>National ID Number</label>
    <input type="text" name="applicant_id_number" required value="<?php echo htmlspecialchars($_POST['applicant_id_number'] ?? ''); ?>">

    <button type="submit">Check Status</button>
  </form>

  <?php if ($notFound): ?>
    <p class="error">No matching request found. Please check your Request ID and National ID Number.</p>
  <?php endif; ?>

  <?php if ($request): ?>
    <?php $timeline = buildTimeline($request); ?>

    <div class="timeline" role="list" aria-label="Application progress">
      <?php foreach ($timeline['stages'] as $i => $stage): ?>
        <div class="timeline-stage
          <?php echo $stage['done'] ? 'done' : ''; ?>
          <?php echo !empty($stage['current']) ? 'current' : ''; ?>
          <?php echo ($timeline['rejected'] && $i === count($timeline['stages']) - 1) ? 'rejected' : ''; ?>"
          role="listitem">
          <span class="timeline-dot"></span>
          <span class="timeline-label"><?php echo htmlspecialchars($stage['label']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="result status-result-card" style="margin-top:25px; padding:15px; border:1px solid #ccc; border-radius:5px;">
      <div class="status-print-bar">
        <button type="button" class="btn print-btn" onclick="window.print();">🖨️ Print This Status</button>
      </div>
      <p><strong>Request ID:</strong> <?php echo $request['id']; ?></p>
      <p><strong>Certificate Type:</strong> <?php echo htmlspecialchars(ucfirst($request['certificate_type'])); ?></p>
      <p><strong>Payment Status:</strong> <?php echo htmlspecialchars($request['payment_status']); ?></p>
      <p><strong>Request Status:</strong>
        <span class="status-<?php echo htmlspecialchars($request['request_status']); ?>">
          <?php echo htmlspecialchars(ucfirst($request['request_status'])); ?>
        </span>
      </p>
      <p><strong>Submitted:</strong> <?php echo $request['created_at']; ?></p>
      <?php if (!empty($request['expiry_date']) && $request['request_status'] === 'approved'): ?>
        <p><strong>Certificate Valid Until:</strong> <?php echo htmlspecialchars($request['expiry_date']); ?></p>
      <?php endif; ?>

      <?php if ($request['request_status'] === 'approved'): ?>
        <form action="download.php" method="POST" target="_blank">
          <input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>">
          <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($request['applicant_id_number']); ?>">
          <button type="submit" class="download-link">Download Your Certificate</button>
        </form>
      <?php elseif ($request['request_status'] === 'rejected'): ?>
        <p>Your request was rejected. Please contact the city office for more information.</p>
      <?php elseif ($request['request_status'] === 'needs_revision'): ?>
        <?php
          $noteStmt = $conn->prepare("SELECT note FROM request_notes WHERE request_id = ? AND requires_revision = 1 ORDER BY created_at DESC LIMIT 1");
          $noteStmt->bind_param("i", $request['id']);
          $noteStmt->execute();
          $noteRow = $noteStmt->get_result()->fetch_assoc();
          $noteStmt->close();
        ?>
        <div class="revision-notice">
          <p><strong>The city office needs one thing from you before continuing:</strong></p>
          <p>"<?php echo htmlspecialchars($noteRow['note'] ?? 'Please contact the city office for details.'); ?>"</p>
        </div>
        <form action="resubmit_document.php" method="POST" enctype="multipart/form-data" style="margin-top:12px;">
          <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
          <input type="hidden" name="applicant_id_number" value="<?php echo htmlspecialchars($request['applicant_id_number']); ?>">
          <label>Upload corrected document (JPG, PNG, or PDF, under 5MB)</label>
          <input type="file" name="applicant_id_document" accept=".jpg,.jpeg,.png,.pdf" required>
          <button type="submit">Resubmit Document</button>
        </form>
      <?php else: ?>
        <p>Your request is still being reviewed. Please check back later.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
