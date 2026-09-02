<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/sms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_sms_logs.php');
    exit;
}

if (!verify_csrf()) {
    header('Location: admin_sms_logs.php?error=' . urlencode('Security check failed. Please try again.'));
    exit;
}

$logId = (int)($_POST['log_id'] ?? 0);
if (!$logId) {
    header('Location: admin_sms_logs.php?error=' . urlencode('Invalid log entry.'));
    exit;
}

$sent = retrySMSLog($conn, $logId);
header('Location: admin_sms_logs.php?retried=' . urlencode($sent ? 'sent' : 'failed'));
exit;
