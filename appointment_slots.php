<?php
// AJAX endpoint used by book_appointment.php to populate the time dropdown
// once a date is picked. Re-validates the Request ID + National ID Number
// pair so this can't be used to probe another request's branch office.
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/appointments.php';

header('Content-Type: application/json');

$id = (int)($_GET['request_id'] ?? 0);
$id_number = trim($_GET['applicant_id_number'] ?? '');
$date = trim($_GET['date'] ?? '');

if (!$id || !$id_number || !in_array($date, getBookableDates(), true)) {
    echo json_encode(['slots' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT branch_office FROM requests WHERE id = ? AND applicant_id_number = ?");
$stmt->bind_param("is", $id, $id_number);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['slots' => []]);
    exit;
}

echo json_encode(['slots' => getAvailableSlots($conn, $row['branch_office'], $date)]);
