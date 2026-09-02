<?php
// sms_webhook.php
// Receives inbound SMS replies from the gateway (e.g. TextBee) and, if the
// sender has a pending satisfaction survey, records their 1-5 rating.
//
// IMPORTANT: TextBee's exact inbound-webhook payload shape isn't something
// this codebase has seen live — the field names below (from/sender/message)
// are the common conventions across SMS gateways, but confirm against
// TextBee's actual webhook docs/dashboard and adjust the extraction below
// if their payload uses different keys.
//
// Configure this URL in your gateway's inbound-webhook settings as:
//   https://your-domain/sms_webhook.php?secret=SMS_WEBHOOK_SECRET
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/public_auth.php'; // for normalizeEthiopianPhone()

header('Content-Type: application/json');

if (!hash_equals(SMS_WEBHOOK_SECRET, $_GET['secret'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid webhook secret.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST;

// Try the common field name variants gateways use for the sender number
// and message body.
$from = $payload['from'] ?? $payload['sender'] ?? $payload['phone'] ?? $payload['msisdn'] ?? '';
$text = $payload['message'] ?? $payload['text'] ?? $payload['body'] ?? '';

if (!$from || $text === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing sender or message text.']);
    exit;
}

$phone = normalizeEthiopianPhone((string)$from);
$text = trim((string)$text);

// Pull the first digit 1-5 out of the reply (citizens may type "5" or
// "5 stars, great service" — either works).
if (!preg_match('/[1-5]/', $text, $matches)) {
    echo json_encode(['ok' => true, 'note' => 'No 1-5 rating found in message; ignored.']);
    exit;
}
$rating = (int)$matches[0];

// Match the most recent still-open survey sent to this phone number.
$stmt = $conn->prepare("
    SELECT id FROM satisfaction_ratings
    WHERE phone = ? AND rating IS NULL
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $phone);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['ok' => true, 'note' => 'No pending survey for this number; ignored.']);
    exit;
}

$feedbackText = substr($text, 0, 255);
$stmt = $conn->prepare("
    UPDATE satisfaction_ratings
    SET rating = ?, feedback_text = ?, responded_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("isi", $rating, $feedbackText, $row['id']);
$stmt->execute();
$stmt->close();

echo json_encode(['ok' => true, 'recorded_rating' => $rating]);
