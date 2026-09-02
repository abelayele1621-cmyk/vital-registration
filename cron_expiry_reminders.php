<?php
// cron_expiry_reminders.php
//
// Run this on a schedule (e.g. once a day) to text applicants whose
// approved certificate is within RENEWAL_REMINDER_WINDOW_DAYS of its
// expiry_date. Each request is only reminded once (renewal_reminder_sent).
//
// Example crontab entry (once a day at 08:00 server time):
//   0 8 * * * /usr/bin/php /full/path/to/php-civil-registration/cron_expiry_reminders.php >> /full/path/to/php-civil-registration/cron.log 2>&1
//
// Intentionally has no web-facing equivalent — running it over HTTP would
// let anyone trigger SMS sends, so this only works from the CLI.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line (cron).";
    exit(1);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/sms.php';

$windowDate = date('Y-m-d', strtotime('+' . RENEWAL_REMINDER_WINDOW_DAYS . ' days'));

$stmt = $conn->prepare("
    SELECT id, certificate_type, applicant_phone, expiry_date
    FROM requests
    WHERE request_status = 'approved'
      AND renewal_reminder_sent = 0
      AND expiry_date IS NOT NULL
      AND expiry_date <= ?
");
$stmt->bind_param("s", $windowDate);
$stmt->execute();
$result = $stmt->get_result();

$sent = 0;
$skipped = 0;

while ($row = $result->fetch_assoc()) {
    if (empty($row['applicant_phone'])) {
        $skipped++;
        continue;
    }

    $message = "Debre Birhan Civil Registration: Your {$row['certificate_type']} certificate (Request ID: {$row['id']}) expires on {$row['expiry_date']}. Please visit our office to renew it.";
    $outcome = sendAndLogSMS($conn, (int)$row['id'], $row['applicant_phone'], $message);

    if ($outcome['status'] === 'sent') {
        $update = $conn->prepare("UPDATE requests SET renewal_reminder_sent = 1 WHERE id = ?");
        $update->bind_param("i", $row['id']);
        $update->execute();
        $update->close();
        $sent++;
    } else {
        // Leave renewal_reminder_sent at 0 so a failed send is retried on
        // the next cron run.
        $skipped++;
    }
}
$stmt->close();

echo date('Y-m-d H:i:s') . " — Renewal reminders: {$sent} sent, {$skipped} skipped/failed." . PHP_EOL;
