<?php
// Serves an applicant's uploaded supporting ID document. Admin-only —
// uploads/ itself is blocked from direct web access.
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$stmt = $conn->prepare("SELECT applicant_id_document FROM requests WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['applicant_id_document'])) {
    http_response_code(404);
    echo "No document on file for this request.";
    exit;
}

$path = __DIR__ . '/uploads/' . $row['applicant_id_document'];
if (!file_exists($path)) {
    http_response_code(404);
    echo "Document not found.";
    exit;
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="id_document_' . $id . '.' . $ext . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
