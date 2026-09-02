<?php
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/sms.php';
require_once 'includes/rules.php';
require_once 'includes/public_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: request.php');
    exit;
}

if (!verify_csrf()) {
    header('Location: request.php?error=' . urlencode('Security check failed. Please reload the form and try again.'));
    exit;
}

// Collect and trim form input. Values are passed to bind_param() below,
// which handles escaping — do NOT also call real_escape_string() on
// values that go through a prepared statement, or quotes/backslashes
// get double-escaped and stored wrong (e.g. "O'Brien" -> "O\'Brien").
$certificate_type       = trim($_POST['certificate_type'] ?? '');
$applicant_name         = trim($_POST['applicant_name'] ?? '');
$applicant_relationship = trim($_POST['applicant_relationship'] ?? '');
$applicant_id_number    = trim($_POST['applicant_id_number'] ?? '');
$applicant_phone        = trim($_POST['applicant_phone'] ?? '');
// Normalize to the same digits(+leading +) form the public dashboard login
// uses, so "0911223344" and "+251911223344" match consistently whether
// typed at submission time or later at login time.
$applicant_phone        = normalizeIdentifier($applicant_phone)['value'];
$applicant_email_raw    = trim($_POST['applicant_email'] ?? '');
$applicant_address      = trim($_POST['applicant_address'] ?? '');
$person_full_name       = trim($_POST['person_full_name'] ?? '');
$person_dob             = !empty($_POST['person_dob']) ? trim($_POST['person_dob']) : null;
$person_place_of_birth  = trim($_POST['person_place_of_birth'] ?? '');
$person_sex_raw         = trim($_POST['person_sex'] ?? '');
$father_name            = trim($_POST['father_name'] ?? '');
$mother_name            = trim($_POST['mother_name'] ?? '');
$person_grandfather_name = trim($_POST['person_grandfather_name'] ?? '');
$mother_nationality     = trim($_POST['mother_nationality'] ?? '');
$father_nationality     = trim($_POST['father_nationality'] ?? '');
// Type-specific fields — only meaningful for their matching
// certificate_type, but harmless (stored as NULL/blank) for the others.
$deceased_title         = trim($_POST['deceased_title'] ?? '');
$place_of_death         = trim($_POST['place_of_death'] ?? '');
$date_of_death          = !empty($_POST['date_of_death']) ? trim($_POST['date_of_death']) : null;
$spouse_name            = trim($_POST['spouse_name'] ?? '');
$marriage_date          = !empty($_POST['marriage_date']) ? trim($_POST['marriage_date']) : null;
$marriage_place         = trim($_POST['marriage_place'] ?? '');
$adoption_reg_form_number = trim($_POST['adoption_reg_form_number'] ?? '');
$birth_reg_unique_id    = trim($_POST['birth_reg_unique_id'] ?? '');
$purpose                = trim($_POST['purpose'] ?? '');
$num_copies_raw         = (int)($_POST['num_copies'] ?? 1);
$delivery_method_raw    = trim($_POST['delivery_method'] ?? '');
$sub_city               = trim($_POST['sub_city'] ?? '');
$exemption_category_raw = trim($_POST['exemption_category'] ?? '');

// Whitelist validation for fields driven by a <select> in the form —
// never trust that a POST actually came from our own dropdown.
$allowedCertTypes = ['birth', 'death', 'marriage', 'adoption'];
$certificate_type = in_array($certificate_type, $allowedCertTypes, true) ? $certificate_type : '';

$allowedSex = ['', 'male', 'female'];
$person_sex = in_array($person_sex_raw, $allowedSex, true) ? $person_sex_raw : '';

$allowedDelivery = ['pickup', 'mail'];
$delivery_method = in_array($delivery_method_raw, $allowedDelivery, true) ? $delivery_method_raw : 'pickup';

// Keep number of copies within a sane range.
$num_copies = max(1, min(20, $num_copies_raw));

// Only store the email if it's actually a valid address. Lowercased so it
// matches consistently with the public dashboard login (which also
// lowercases emails).
$applicant_email = filter_var($applicant_email_raw, FILTER_VALIDATE_EMAIL) ? strtolower($applicant_email_raw) : '';

// Exemption category must be one we recognize, or blank.
$exemption_category = isValidExemptionCategory($exemption_category_raw) ? $exemption_category_raw : '';

// Geo-routing: map the selected sub-city/kebele to its branch office.
$sub_city = $sub_city !== '' ? $sub_city : 'Other';
$branch_office = resolveBranchOffice($sub_city);

// Basic required-field validation
if (!$certificate_type || !$applicant_name || !$applicant_relationship || !$applicant_id_number || !$applicant_phone || !$person_full_name) {
    header('Location: request.php?error=' . urlencode('Please fill in all required fields.'));
    exit;
}

// --- Duplicate / fraud check ---
// Block a resubmission of the same person's certificate by the same
// applicant (matched on ID number or phone) within the lookback window,
// unless their earlier request was rejected (which clears the way to
// try again).
$dupStmt = $conn->prepare("
    SELECT id FROM requests
    WHERE (applicant_id_number = ? OR applicant_phone = ?)
      AND certificate_type = ?
      AND person_full_name = ?
      AND request_status != 'rejected'
      AND created_at >= (NOW() - INTERVAL " . (int)DUPLICATE_CHECK_WINDOW_DAYS . " DAY)
    LIMIT 1
");
$dupStmt->bind_param("ssss", $applicant_id_number, $applicant_phone, $certificate_type, $person_full_name);
$dupStmt->execute();
$dupRow = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();

if ($dupRow) {
    header('Location: request.php?error=' . urlencode(
        'A matching request (ID: ' . $dupRow['id'] . ') was already submitted recently. ' .
        'Please check its status on the status page instead of submitting again.'
    ));
    exit;
}

// --- Optional supporting ID document upload (Step 2 of the wizard) ---
// Stored under uploads/ with a random name; the folder is locked down via
// .htaccess so uploaded files can't be executed even if someone sneaks a
// script past the extension/MIME check below.
$applicant_id_document = null;
if (!empty($_FILES['applicant_id_document']['name']) && $_FILES['applicant_id_document']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['applicant_id_document'];
    $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxBytes = 5 * 1024 * 1024; // 5MB

    if (in_array($ext, $allowedExt, true) && $file['size'] > 0 && $file['size'] <= $maxBytes) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
            $applicant_id_document = $storedName;
        }
    } else {
        header('Location: request.php?error=' . urlencode('ID document must be a JPG, PNG, or PDF under 5MB.'));
        exit;
    }
}

$stmt = $conn->prepare("
  INSERT INTO requests (
    certificate_type, applicant_name, applicant_relationship, applicant_id_number,
    applicant_phone, applicant_email, applicant_address, person_full_name,
    person_dob, person_place_of_birth, person_sex, father_name, mother_name,
    purpose, num_copies, delivery_method, applicant_id_document,
    sub_city, branch_office, exemption_category, fee_charged,
    person_grandfather_name, mother_nationality, father_nationality,
    deceased_title, place_of_death, date_of_death,
    spouse_name, marriage_date, marriage_place,
    adoption_reg_form_number, birth_reg_unique_id
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$fee_charged = resolveFee($exemption_category);

$stmt->bind_param(
    "ssssssssssssssisssssdsssssssssss",
    $certificate_type, $applicant_name, $applicant_relationship, $applicant_id_number,
    $applicant_phone, $applicant_email, $applicant_address, $person_full_name,
    $person_dob, $person_place_of_birth, $person_sex, $father_name, $mother_name,
    $purpose, $num_copies, $delivery_method, $applicant_id_document,
    $sub_city, $branch_office, $exemption_category, $fee_charged,
    $person_grandfather_name, $mother_nationality, $father_nationality,
    $deceased_title, $place_of_death, $date_of_death,
    $spouse_name, $marriage_date, $marriage_place,
    $adoption_reg_form_number, $birth_reg_unique_id
);

if (!$stmt->execute()) {
    header('Location: request.php?error=' . urlencode('Failed to save request. Please try again.'));
    exit;
}

$requestId = $stmt->insert_id;
$stmt->close();

// Send the "request received" SMS right away. Whether this succeeds is
// recorded on the request itself (sms_verified) so the citizen-facing
// activity timeline on status.php can show an accurate "SMS Verified" step.
$smsMessage = "Debre Birhan Civil Registration: We received your {$certificate_type} certificate request. Your Request ID is {$requestId}. Keep it to check status and download your certificate.";
$smsResult = sendAndLogSMS($conn, $requestId, $applicant_phone, $smsMessage);
$smsVerified = ($smsResult['status'] === 'sent') ? 1 : 0;

$stmt = $conn->prepare("UPDATE requests SET sms_verified = ? WHERE id = ?");
$stmt->bind_param("ii", $smsVerified, $requestId);
$stmt->execute();
$stmt->close();

// --- Fee-exempt requests skip Chapa entirely ---
// BUG FIX: this used to send exempt applicants to Chapa checkout for the
// FULL certificate fee (CERTIFICATE_FEE_ETB) regardless of $fee_charged,
// even though the wizard's review step told them "Certificate fee: 0 ETB".
// An exempt request has nothing to pay, so there's no transaction to
// initialize — mark it paid outright and send the citizen straight to the
// payment_success page. Staff can still revoke a bad-faith exemption claim
// from the admin panel (that flips payment_status back so it can't be
// approved without a real payment).
if ($fee_charged <= 0.0) {
    $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid' WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $stmt->close();

    header('Location: payment_success.php?id=' . $requestId . '&exempt=1');
    exit;
}

// Initiate Chapa payment
$tx_ref = "req-" . $requestId . "-" . time();
$email = !empty($applicant_email) ? $applicant_email : "test@example.com";

$payload = [
    "amount" => $fee_charged,
    "currency" => "ETB",
    "email" => $email,
    "first_name" => $applicant_name,
    "tx_ref" => $tx_ref,
    "callback_url" => BASE_URL . "/verify_payment.php?tx_ref=" . $tx_ref,
    "return_url" => BASE_URL . "/payment_success.php",
    "customization" => [
        "title" => "Certificate Fee",
        "description" => "Payment for $certificate_type certificate"
    ]
];

$ch = curl_init("https://api.chapa.co/v1/transaction/initialize");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . CHAPA_SECRET_KEY,
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($curlError || !isset($result['data']['checkout_url'])) {
    // Payment could not be started, but the request was saved.
    header('Location: request.php?error=' . urlencode('Request saved (ID: ' . $requestId . '), but payment could not be started. Contact the office.'));
    exit;
}

// Mark as pending payment, and remember the tx_ref so verify_payment.php
// can look the request up by it rather than trusting a client-supplied ID.
$stmt = $conn->prepare("UPDATE requests SET payment_status = 'pending_payment', tx_ref = ? WHERE id = ?");
$stmt->bind_param("si", $tx_ref, $requestId);
$stmt->execute();
$stmt->close();

// Redirect citizen to Chapa's checkout page
header('Location: ' . $result['data']['checkout_url']);
exit;
