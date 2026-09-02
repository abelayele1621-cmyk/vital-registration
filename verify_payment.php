<?php
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/sms.php';

$tx_ref = $_GET['tx_ref'] ?? '';
if (!$tx_ref) {
    http_response_code(400);
    echo "Missing transaction reference.";
    exit;
}

// Look the request up by the tx_ref we generated and stored ourselves at
// submit time, rather than trusting an ID parsed out of the URL.
$stmt = $conn->prepare("SELECT id FROM requests WHERE tx_ref = ?");
$stmt->bind_param("s", $tx_ref);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo "Unknown transaction.";
    exit;
}

$requestId = (int)$row['id'];

$ch = curl_init("https://api.chapa.co/v1/transaction/verify/" . urlencode($tx_ref));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . CHAPA_SECRET_KEY
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// Confirm success AND that the amount/tx_ref Chapa reports back match what
// we expect, as defense in depth against a tampered or replayed callback.
$paymentOk = isset($result['status']) && $result['status'] === 'success'
    && isset($result['data']['status']) && $result['data']['status'] === 'success'
    && isset($result['data']['tx_ref']) && $result['data']['tx_ref'] === $tx_ref
    && isset($result['data']['amount']) && (float)$result['data']['amount'] >= (float)CERTIFICATE_FEE_ETB;

if ($paymentOk) {
    $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, certificate_type, applicant_phone FROM requests WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['applicant_phone'])) {
        $message = "Debre Birhan Civil Registration: Payment received for your {$row['certificate_type']} certificate request (ID: {$row['id']}). It is now under review.";
        sendAndLogSMS($conn, $requestId, $row['applicant_phone'], $message);
    }

    echo "Payment verified successfully.";
} else {
    echo "Payment not completed.";
}
