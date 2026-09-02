<?php
// AJAX endpoint used by admin.php's live search box. Returns the same rows
// admin.php would show for a given filter, as JSON, so the table can be
// re-rendered client-side without a full page reload.
require_once 'includes/require_login.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$search        = trim($_GET['q'] ?? '');
$typeFilter    = trim($_GET['type'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment'] ?? '');
$phoneSuffix   = trim($_GET['phone'] ?? '');

$allowedTypes    = ['birth', 'death', 'marriage', 'adoption'];
$allowedStatuses = ['pending', 'approved', 'rejected', 'needs_revision'];
$allowedPayments = ['unpaid', 'pending_payment', 'paid'];

$typeFilter    = in_array($typeFilter, $allowedTypes, true) ? $typeFilter : '';
$statusFilter  = in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : '';
$paymentFilter = in_array($paymentFilter, $allowedPayments, true) ? $paymentFilter : '';
$phoneSuffix   = preg_replace('/[^0-9]/', '', $phoneSuffix);

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(applicant_name LIKE ? OR person_full_name LIKE ? OR applicant_id_number LIKE ? OR id = ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = ctype_digit($search) ? (int)$search : 0;
    $types .= 'sssi';
}
if ($phoneSuffix !== '') {
    $conditions[] = "applicant_phone LIKE ?";
    $params[] = '%' . $phoneSuffix;
    $types .= 's';
}
if ($typeFilter !== '') {
    $conditions[] = "certificate_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}
if ($statusFilter !== '') {
    $conditions[] = "request_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($paymentFilter !== '') {
    $conditions[] = "payment_status = ?";
    $params[] = $paymentFilter;
    $types .= 's';
}

$sql = "SELECT id, certificate_type, applicant_name, person_full_name, applicant_phone, payment_status, request_status, created_at, applicant_id_document FROM requests";
if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id' => (int)$row['id'],
        'certificate_type' => $row['certificate_type'],
        'applicant_name' => $row['applicant_name'],
        'person_full_name' => $row['person_full_name'],
        'applicant_phone' => $row['applicant_phone'],
        'payment_status' => $row['payment_status'],
        'request_status' => $row['request_status'],
        'created_at' => $row['created_at'],
        'has_document' => !empty($row['applicant_id_document']),
    ];
}

echo json_encode(['count' => count($rows), 'rows' => $rows]);
