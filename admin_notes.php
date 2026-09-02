<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$stmt = $conn->prepare("SELECT note, requires_revision, created_by, created_at FROM request_notes WHERE request_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];
while ($n = $result->fetch_assoc()) {
    $notes[] = [
        'note' => $n['note'],
        'requires_revision' => (bool)$n['requires_revision'],
        'created_by' => $n['created_by'],
        'created_at' => $n['created_at'],
    ];
}
$stmt->close();

echo json_encode(['notes' => $notes]);
