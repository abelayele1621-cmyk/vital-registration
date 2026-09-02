<?php
// cron_daily_reconciliation.php
//
// Run once a day (after office hours) to reconcile every request marked
// "paid" that day against what Chapa itself reports for that transaction,
// and email a summary to the finance department.
//
// This calls Chapa's per-transaction verify endpoint (the same one
// verify_payment.php uses) once per tx_ref rather than a "list all of
// today's transactions" call — Chapa doesn't document a bulk listing
// endpoint here, but we already stored every tx_ref ourselves at request
// time, so re-verifying each one is an equally solid reconciliation and
// needs no extra API surface.
//
// Example crontab entry (once a day at 20:00 server time):
//   0 20 * * * /usr/bin/php /full/path/to/php-civil-registration/cron_daily_reconciliation.php >> /full/path/to/php-civil-registration/cron.log 2>&1
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line (cron).";
    exit(1);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/public_auth.php'; // for sendOtpEmail()-style mail wrapper

$reportDate = date('Y-m-d');

// Every request marked paid today, whether via Chapa or a fee exemption
// (exempt requests have fee_charged = 0 and no tx_ref — they're included
// in the count but can't be mismatched against Chapa since there's
// nothing to check).
$stmt = $conn->prepare("
    SELECT id, certificate_type, fee_charged, tx_ref, exemption_category
    FROM requests
    WHERE payment_status = 'paid'
      AND DATE(created_at) = ?
");
$stmt->bind_param("s", $reportDate);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$requestsPaid = count($rows);
$totalAmount = 0.0;
$mismatches = [];
$exemptCount = 0;

foreach ($rows as $row) {
    $totalAmount += (float)$row['fee_charged'];

    if (!empty($row['exemption_category'])) {
        $exemptCount++;
        continue; // nothing to check against Chapa for a waived fee
    }

    if (empty($row['tx_ref'])) {
        $mismatches[] = "Request #{$row['id']}: marked paid but has no tx_ref on file.";
        continue;
    }

    $ch = curl_init("https://api.chapa.co/v1/transaction/verify/" . urlencode($row['tx_ref']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . CHAPA_SECRET_KEY]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $chapaResult = json_decode($response, true);
    $chapaSuccess = !$curlError
        && isset($chapaResult['status']) && $chapaResult['status'] === 'success'
        && isset($chapaResult['data']['status']) && $chapaResult['data']['status'] === 'success';

    if (!$chapaSuccess) {
        $mismatches[] = "Request #{$row['id']} (tx_ref {$row['tx_ref']}): marked paid locally, but Chapa does not confirm a successful transaction.";
        continue;
    }

    $chapaAmount = (float)($chapaResult['data']['amount'] ?? 0);
    if (abs($chapaAmount - (float)$row['fee_charged']) > 0.01) {
        $mismatches[] = "Request #{$row['id']} (tx_ref {$row['tx_ref']}): local fee " . number_format((float)$row['fee_charged'], 2) . " ETB does not match Chapa amount " . number_format($chapaAmount, 2) . " ETB.";
    }
}

// --- Build and send the report ---
$lines = [];
$lines[] = "Daily Financial Reconciliation Report — {$reportDate}";
$lines[] = str_repeat('-', 50);
$lines[] = "Requests marked paid today: {$requestsPaid}";
$lines[] = "  - Fee-exempt (waived): {$exemptCount}";
$lines[] = "Total fees recorded: " . number_format($totalAmount, 2) . " ETB";
$lines[] = "Mismatches found: " . count($mismatches);
if ($mismatches) {
    $lines[] = "";
    $lines[] = "Details:";
    foreach ($mismatches as $m) {
        $lines[] = "  - " . $m;
    }
}
$reportText = implode("\n", $lines) . "\n";

$emailed = false;
$headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
if (@mail(FINANCE_EMAIL, "Reconciliation Report - {$reportDate}", $reportText, $headers)) {
    $emailed = true;
}

// mail() is frequently not configured on a bare install (see
// includes/public_auth.php's sendOtpEmail() comment) — always also save a
// copy to disk so the report isn't lost if delivery silently failed.
$reportsDir = __DIR__ . '/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
}
file_put_contents($reportsDir . '/reconciliation_' . $reportDate . '.txt', $reportText);

// Log the run so admins can see history without digging through email/disk.
$mismatchDetail = $mismatches ? implode("\n", $mismatches) : null;
$stmt = $conn->prepare("
    INSERT INTO reconciliation_reports (report_date, requests_paid, total_amount, mismatches, mismatch_detail, emailed)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE requests_paid = ?, total_amount = ?, mismatches = ?, mismatch_detail = ?, emailed = ?
");
$mismatchCount = count($mismatches);
$emailedInt = $emailed ? 1 : 0;
$stmt->bind_param(
    "sidssi idssi",
    $reportDate, $requestsPaid, $totalAmount, $mismatchCount, $mismatchDetail, $emailedInt,
    $requestsPaid, $totalAmount, $mismatchCount, $mismatchDetail, $emailedInt
);
// bind_param can't take a type string with a space — build it without one.
$stmt->close();

$stmt2 = $conn->prepare("
    INSERT INTO reconciliation_reports (report_date, requests_paid, total_amount, mismatches, mismatch_detail, emailed)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE requests_paid = VALUES(requests_paid), total_amount = VALUES(total_amount),
        mismatches = VALUES(mismatches), mismatch_detail = VALUES(mismatch_detail), emailed = VALUES(emailed)
");
$stmt2->bind_param("sidssi", $reportDate, $requestsPaid, $totalAmount, $mismatchCount, $mismatchDetail, $emailedInt);
$stmt2->execute();
$stmt2->close();

echo $reportText;
echo $emailed ? "Report emailed to " . FINANCE_EMAIL . ".\n" : "Could not email report (mail() not configured?) — saved to reports/ instead.\n";
