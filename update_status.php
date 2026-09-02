<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/generate_certificate.php';
require_once 'includes/sms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

if (!verify_csrf()) {
    header('Location: admin.php?error=' . urlencode('Security check failed. Please try again.'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$request_status = trim($_POST['request_status'] ?? '');

if (!$id || !in_array($request_status, ['approved', 'rejected'], true)) {
    header('Location: admin.php?error=' . urlencode('Invalid request.'));
    exit;
}

// Fetch the request first
$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: admin.php?error=' . urlencode('Request not found.'));
    exit;
}

// Enforce payment before approval
if ($request_status === 'approved' && $row['payment_status'] !== 'paid') {
    header('Location: admin.php?error=' . urlencode('Cannot approve a request that has not been paid yet.'));
    exit;
}

$stmt = $conn->prepare("UPDATE requests SET request_status = ? WHERE id = ?");
$stmt->bind_param("si", $request_status, $id);
$stmt->execute();
$stmt->close();

// Generate the certificate PDF (with digital seal + signature) if approved,
// then notify the applicant by SMS either way.
if ($request_status === 'approved') {
    generateCertificate($row, $conn);
    $message = "Debre Birhan Civil Registration: Your {$row['certificate_type']} certificate request (ID: {$row['id']}) has been APPROVED. You can download it from the status page.";
} else {
    $message = "Debre Birhan Civil Registration: Your {$row['certificate_type']} certificate request (ID: {$row['id']}) was rejected. Please contact the city office for details.";
}

if (!empty($row['applicant_phone'])) {
    sendAndLogSMS($conn, $id, $row['applicant_phone'], $message);
}

// After an approval, follow up with a short satisfaction survey. This is
// a separate message (rather than tacked onto the approval text) so a
// reply of just "5" or "3" is unambiguous when it comes back in on the
// two-way SMS webhook (see sms_webhook.php).
if ($request_status === 'approved' && !empty($row['applicant_phone'])) {
    $surveyMessage = "Debre Birhan Civil Registration: How would you rate the service for your {$row['certificate_type']} certificate (ID: {$row['id']})? Reply with a number from 1 (poor) to 5 (excellent).";
    sendAndLogSMS($conn, $id, $row['applicant_phone'], $surveyMessage);

    $stmt = $conn->prepare("INSERT INTO satisfaction_ratings (request_id, phone) VALUES (?, ?)");
    $stmt->bind_param("is", $id, $row['applicant_phone']);
    $stmt->execute();
    $stmt->close();
}

$adminUser = $_SESSION['admin_username'] ?? 'admin';
$stmt = $conn->prepare("INSERT INTO audit_log (request_id, admin_username, action, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iss", $id, $adminUser, $request_status);
$stmt->execute();
$stmt->close();

header('Location: admin.php');
exit;
