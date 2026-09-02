<?php
require_once 'includes/db.php';

$result = null;
$checked = false;

// Scanning the QR code on a printed certificate links here with
// ?id=...&code=... — treat that exactly like a submitted form so the
// result shows immediately with no re-typing needed.
$isQrScan = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['code']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isQrScan) {
    $checked = true;
    $source = $isQrScan ? $_GET : $_POST;
    $id = (int)($source['id'] ?? 0);
    $code = strtoupper(trim($source['code'] ?? ''));

    if ($id && $code) {
        $stmt = $conn->prepare("SELECT id, certificate_type, person_full_name, request_status, issued_at, expiry_date, verification_code FROM requests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && $row['request_status'] === 'approved' && !empty($row['verification_code']) && hash_equals($row['verification_code'], $code)) {
            $result = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify a Certificate</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content" style="max-width:500px;">
  <h2>✅ Verify a Certificate</h2>
  <p>Enter the Certificate ID and Verification Code printed at the bottom of the certificate — or simply scan the QR code on the printed page with your phone.</p>

  <?php if ($isQrScan): ?>
    <p class="qr-scan-badge">📷 Scanned from certificate QR code — result below.</p>
  <?php endif; ?>

  <form method="POST">
    <label>Certificate ID</label>
    <input type="number" name="id" required value="<?php echo htmlspecialchars((string)($isQrScan ? ($_GET['id'] ?? '') : ($_POST['id'] ?? ''))); ?>">

    <label>Verification Code</label>
    <input type="text" name="code" required style="text-transform:uppercase;" value="<?php echo htmlspecialchars((string)($isQrScan ? ($_GET['code'] ?? '') : ($_POST['code'] ?? ''))); ?>">

    <button type="submit">Verify</button>
  </form>

  <?php if ($checked): ?>
    <?php if ($result): ?>
      <div class="result verify-valid">
        <p class="success">&#10003; This certificate is genuine.</p>
        <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($result['certificate_type'])); ?> Certificate</p>
        <p><strong>Name on Certificate:</strong> <?php echo htmlspecialchars($result['person_full_name']); ?></p>
        <p><strong>Issued:</strong> <?php echo htmlspecialchars($result['issued_at']); ?></p>
        <p><strong>Valid Until:</strong> <?php echo htmlspecialchars($result['expiry_date']); ?></p>
      </div>
    <?php else: ?>
      <div class="result verify-invalid">
        <p class="error">&#10007; No matching certificate found. Double-check the ID and code, or contact the city office.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
