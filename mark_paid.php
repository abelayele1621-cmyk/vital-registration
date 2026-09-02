<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
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

if ($id) {
    $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, certificate_type, applicant_phone FROM requests WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['applicant_phone'])) {
        $message = "Debre Birhan Civil Registration: Payment received for your {$row['certificate_type']} certificate request (ID: {$row['id']}). It is now under review.";
        sendAndLogSMS($conn, $id, $row['applicant_phone'], $message);
    }

    $adminUser = $_SESSION['admin_username'] ?? 'admin';
    $stmt = $conn->prepare("INSERT INTO audit_log (request_id, admin_username, action, created_at) VALUES (?, ?, 'marked_paid', NOW())");
    $stmt->bind_param("is", $id, $adminUser);
    $stmt->execute();
    $stmt->close();
}

header('Location: admin.php');
exit;
