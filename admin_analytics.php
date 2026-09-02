<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

// --- Daily applications, last 30 days ---
$daily = [];
$stmt = $conn->prepare("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
");
$stmt->execute();
$res = $stmt->get_result();
$byDate = [];
while ($r = $res->fetch_assoc()) { $byDate[$r['d']] = (int)$r['c']; }
$stmt->close();
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $daily[] = ['date' => $d, 'count' => $byDate[$d] ?? 0];
}

// --- Monthly applications, last 12 months ---
$monthly = [];
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c
    FROM requests
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
");
$stmt->execute();
$res = $stmt->get_result();
$byMonth = [];
while ($r = $res->fetch_assoc()) { $byMonth[$r['m']] = (int)$r['c']; }
$stmt->close();
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthly[] = ['month' => $m, 'count' => $byMonth[$m] ?? 0];
}

// --- Revenue by day, last 30 days (paid requests only; fee_charged falls
// back to the standard fee for older rows recorded before that column
// existed, minus anything that was fee-exempt). ---
$revenue = [];
$stmt = $conn->prepare("
    SELECT DATE(created_at) AS d,
           SUM(COALESCE(fee_charged, IF(exemption_category IS NULL, ?, 0))) AS total
    FROM requests
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at)
");
$fee = (float)CERTIFICATE_FEE_ETB;
$stmt->bind_param("d", $fee);
$stmt->execute();
$res = $stmt->get_result();
$revByDate = [];
while ($r = $res->fetch_assoc()) { $revByDate[$r['d']] = (float)$r['total']; }
$stmt->close();
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $revenue[] = ['date' => $d, 'revenue' => $revByDate[$d] ?? 0];
}
$totalRevenue30d = array_sum(array_column($revenue, 'revenue'));

// --- Status breakdown (all-time) ---
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'needs_revision' => 0];
$res = $conn->query("SELECT request_status, COUNT(*) AS c FROM requests GROUP BY request_status");
while ($r = $res->fetch_assoc()) {
    if (isset($statusCounts[$r['request_status']])) {
        $statusCounts[$r['request_status']] = (int)$r['c'];
    }
}

// --- Breakdown by certificate type (all-time) ---
$typeCounts = ['birth' => 0, 'death' => 0, 'marriage' => 0, 'adoption' => 0];
$res = $conn->query("SELECT certificate_type, COUNT(*) AS c FROM requests GROUP BY certificate_type");
while ($r = $res->fetch_assoc()) {
    if (isset($typeCounts[$r['certificate_type']])) {
        $typeCounts[$r['certificate_type']] = (int)$r['c'];
    }
}

echo json_encode([
    'daily' => $daily,
    'monthly' => $monthly,
    'revenue' => $revenue,
    'total_revenue_30d' => $totalRevenue30d,
    'status_counts' => $statusCounts,
    'type_counts' => $typeCounts,
]);
