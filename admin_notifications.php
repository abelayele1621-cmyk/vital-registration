<?php
// AJAX endpoint polled by the notification bell in the admin nav. Given a
// "since" timestamp (ISO datetime), returns how many new requests have
// come in after it, plus a short preview list — so admins see new
// submissions arrive live without refreshing admin.php.
require_once 'includes/require_login.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$since = trim($_GET['since'] ?? '');
// Validate the format loosely (YYYY-MM-DD HH:MM:SS); fall back to "1 hour
// ago" for a first-ever poll with no stored value, so a brand new admin
// session doesn't get flooded with every historical request as "new".
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $since = date('Y-m-d H:i:s', time() - 3600);
}

$stmt = $conn->prepare("
    SELECT id, applicant_name, certificate_type, created_at
    FROM requests
    WHERE created_at > ?
    ORDER BY created_at DESC
    LIMIT 8
");
$stmt->bind_param("s", $since);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($r = $res->fetch_assoc()) {
    $items[] = $r;
}
$stmt->close();

echo json_encode([
    'count' => count($items),
    'items' => $items,
    'server_time' => date('Y-m-d H:i:s'),
]);
