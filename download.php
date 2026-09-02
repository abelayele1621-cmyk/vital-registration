<?php
// Serves certificate PDFs. certificates/ is blocked from direct web access
// (see certificates/.htaccess) so every download must go through here,
// where we check the requester is actually allowed to see this certificate.
require_once 'includes/session.php';
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$authorized = false;

if ($isAdmin) {
    // Staff can download any certificate.
    $authorized = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Citizens must re-prove they know the Request ID + National ID Number,
    // and the request must actually be approved.
    $idNumber = $_POST['id_number'] ?? '';
    if ($idNumber) {
        $stmt = $conn->prepare("SELECT id FROM requests WHERE id = ? AND applicant_id_number = ? AND request_status = 'approved'");
        $stmt->bind_param("is", $id, $idNumber);
        $stmt->execute();
        $authorized = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if (!$authorized) {
    http_response_code(403);
    echo "You are not authorized to download this certificate.";
    exit;
}

$path = __DIR__ . '/certificates/certificate_' . $id . '.pdf';

if (!file_exists($path)) {
    http_response_code(404);
    echo "Certificate not found.";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="certificate_' . $id . '.pdf"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
