<?php
// Handles a citizen re-uploading their ID document after staff flagged a
// request needs_revision (see add_note.php). Two ways in, mirroring the
// dual-auth pattern in download.php:
//   1. status.php — re-proves Request ID + National ID Number
//   2. public_dashboard.php — already has a logged-in citizen session
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/sms.php';

function renderResult(string $heading, string $message, bool $ok): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($heading); ?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>
<?php $assetPath = ''; include 'includes/header.php'; ?>
<div class="page-content">
  <h2><?php echo htmlspecialchars($heading); ?></h2>
  <p class="<?php echo $ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></p>
  <p><a href="status.php">&larr; Back to status page</a> &mdash; <a href="public_dashboard.php">My Dashboard</a></p>
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
<?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: status.php');
    exit;
}

$id = (int)($_POST['request_id'] ?? 0);
if (!$id) {
    renderResult('Resubmit Document', 'Invalid request.', false);
}

$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    renderResult('Resubmit Document', 'Request not found.', false);
}

// --- Authorization: either route in is acceptable ---
$authorized = false;

$idNumber = trim($_POST['applicant_id_number'] ?? '');
if ($idNumber !== '' && hash_equals($row['applicant_id_number'], $idNumber)) {
    $authorized = true;
}

$citizenChannel = $_SESSION['citizen_channel'] ?? null;
$citizenIdentifier = $_SESSION['citizen_identifier'] ?? null;
if (!$authorized && $citizenIdentifier) {
    if ($citizenChannel === 'phone' && $citizenIdentifier === $row['applicant_phone']) {
        $authorized = true;
    } elseif ($citizenChannel === 'email' && $citizenIdentifier === $row['applicant_email']) {
        $authorized = true;
    }
}

if (!$authorized) {
    renderResult('Resubmit Document', 'You are not authorized to update this request.', false);
}

if ($row['request_status'] !== 'needs_revision') {
    renderResult('Resubmit Document', 'This request is not currently awaiting a document resubmission.', false);
}

// --- File validation (same rules as the original upload in submit_request.php) ---
if (empty($_FILES['applicant_id_document']['name']) || $_FILES['applicant_id_document']['error'] !== UPLOAD_ERR_OK) {
    renderResult('Resubmit Document', 'Please choose a file to upload.', false);
}

$file = $_FILES['applicant_id_document'];
$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$maxBytes = 5 * 1024 * 1024; // 5MB

if (!in_array($ext, $allowedExt, true) || $file['size'] <= 0 || $file['size'] > $maxBytes) {
    renderResult('Resubmit Document', 'File must be a JPG, PNG, or PDF under 5MB.', false);
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$storedName = bin2hex(random_bytes(16)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
    renderResult('Resubmit Document', 'Upload failed. Please try again.', false);
}

// Clean up the old file so uploads/ doesn't accumulate orphaned documents.
$oldFile = $row['applicant_id_document'];

$stmt = $conn->prepare("UPDATE requests SET applicant_id_document = ?, request_status = 'pending' WHERE id = ?");
$stmt->bind_param("si", $storedName, $id);
$stmt->execute();
$stmt->close();

if (!empty($oldFile) && file_exists($uploadDir . $oldFile)) {
    @unlink($uploadDir . $oldFile);
}

$message = "Debre Birhan Civil Registration: Thanks — we received your updated document for request ID {$id}. It's back in the review queue.";
if (!empty($row['applicant_phone'])) {
    sendAndLogSMS($conn, $id, $row['applicant_phone'], $message);
}

renderResult('Resubmit Document', 'Your updated document was received and your request is back under review.', true);
