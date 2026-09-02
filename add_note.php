<?php
// Lets staff leave a note on a request (e.g. "photo is blurry, please
// re-upload"). If "requires_revision" is checked, the request is flagged
// needs_revision instead of being rejected outright, and the applicant is
// texted so they can fix just the one thing and resubmit.
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
$note = trim($_POST['note'] ?? '');
$requiresRevision = isset($_POST['requires_revision']) && $_POST['requires_revision'] === '1';

if (!$id || $note === '') {
    header('Location: admin.php?error=' . urlencode('A note requires both a request and text.'));
    exit;
}
if (mb_strlen($note) > 1000) {
    $note = mb_substr($note, 0, 1000);
}

$stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header('Location: admin.php?error=' . urlencode('Request not found.'));
    exit;
}

$adminUser = $_SESSION['admin_username'] ?? 'admin';

$stmt = $conn->prepare("
    INSERT INTO request_notes (request_id, note, requires_revision, created_by, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$requiresRevisionInt = $requiresRevision ? 1 : 0;
$stmt->bind_param("isis", $id, $note, $requiresRevisionInt, $adminUser);
$stmt->execute();
$stmt->close();

if ($requiresRevision) {
    $stmt = $conn->prepare("UPDATE requests SET request_status = 'needs_revision' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $message = "Debre Birhan Civil Registration: Your {$row['certificate_type']} certificate request (ID: {$row['id']}) needs a small fix before we can continue: \"{$note}\". Please check your status page to resubmit.";
    if (!empty($row['applicant_phone'])) {
        sendAndLogSMS($conn, $id, $row['applicant_phone'], $message);
    }
}

$stmt = $conn->prepare("
    INSERT INTO audit_log (request_id, admin_username, action, details, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$action = $requiresRevision ? 'note_revision_requested' : 'note_added';
$stmt->bind_param("isss", $id, $adminUser, $action, $note);
$stmt->execute();
$stmt->close();

header('Location: admin.php?noteAdded=' . $id);
exit;
