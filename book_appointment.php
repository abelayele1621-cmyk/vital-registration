<?php
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/appointments.php';

// Same lookup pattern as status.php: Request ID + National ID Number
// proves the citizen owns this request without needing a full OTP login.
$request = null;
$notFound = false;
$booked = false;
$bookingError = '';

$id = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
$id_number = trim($_POST['applicant_id_number'] ?? $_GET['applicant_id_number'] ?? '');

if ($id && $id_number) {
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ? AND applicant_id_number = ?");
    $stmt->bind_param("is", $id, $id_number);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$request) {
        $notFound = true;
    }
}

// Handle the actual booking submission (a second POST once a request has
// been looked up and a slot chosen).
if ($request && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_date'])) {
    if (!verify_csrf()) {
        $bookingError = 'Security check failed. Please try again.';
    } else {
        $purpose = in_array($_POST['purpose'] ?? '', ['pickup', 'biometric', 'affidavit'], true) ? $_POST['purpose'] : 'pickup';
        $date = trim($_POST['appointment_date'] ?? '');
        $time = trim($_POST['appointment_time'] ?? '');

        if ($purpose === 'pickup' && $request['request_status'] !== 'approved') {
            $bookingError = 'A pickup appointment can only be booked once your certificate is approved.';
        } elseif (!in_array($date, getBookableDates(), true)) {
            $bookingError = 'Please choose a valid date.';
        } elseif (!in_array($time, array_column(getAvailableSlots($conn, $request['branch_office'], $date), 'time'), true)) {
            $bookingError = 'That time slot is no longer available. Please pick another.';
        } else {
            $result = bookAppointment($conn, $id, $request['branch_office'], $date, $time, $purpose);
            if ($result['ok']) {
                $booked = true;
            } else {
                $bookingError = $result['error'];
            }
        }
    }
}

$existingAppointment = $request ? getAppointmentForRequest($conn, (int)$request['id']) : null;
$bookableDates = getBookableDates();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Book an Appointment</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content">
  <h2>Book an Office Appointment</h2>
  <p>Book a date and time to visit the office in person &mdash; for certificate pickup, biometric capture, or a sworn affidavit.</p>

  <?php if (!$request): ?>
    <form method="POST">
      <?php csrf_field(); ?>
      <label>Request ID</label>
      <input type="number" name="request_id" required value="<?php echo htmlspecialchars((string)($_POST['request_id'] ?? '')); ?>">

      <label>National ID Number</label>
      <input type="text" name="applicant_id_number" required value="<?php echo htmlspecialchars($_POST['applicant_id_number'] ?? ''); ?>">

      <button type="submit">Find My Request</button>
    </form>

    <?php if ($notFound): ?>
      <p class="error">No matching request found. Please check your Request ID and National ID Number.</p>
    <?php endif; ?>
  <?php elseif ($booked): ?>
    <p class="success">&#10003; Appointment booked for <?php echo htmlspecialchars($_POST['appointment_date']); ?> at <?php echo htmlspecialchars($_POST['appointment_time']); ?> at <?php echo htmlspecialchars($request['branch_office']); ?>.</p>
    <p>Please bring your National ID and this Request ID (#<?php echo (int)$request['id']; ?>) with you.</p>
  <?php else: ?>
    <p>Request #<?php echo (int)$request['id']; ?> &mdash; branch office: <strong><?php echo htmlspecialchars($request['branch_office']); ?></strong></p>

    <?php if ($existingAppointment): ?>
      <p class="success">You already have an appointment booked for
        <?php echo htmlspecialchars($existingAppointment['appointment_date']); ?> at
        <?php echo htmlspecialchars(substr($existingAppointment['appointment_time'], 0, 5)); ?>
        (<?php echo htmlspecialchars(ucfirst($existingAppointment['purpose'])); ?>).
        Booking a new slot below will replace it.</p>
    <?php endif; ?>

    <?php if ($bookingError): ?>
      <p class="error"><?php echo htmlspecialchars($bookingError); ?></p>
    <?php endif; ?>

    <form method="POST" id="bookingForm">
      <?php csrf_field(); ?>
      <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">
      <input type="hidden" name="applicant_id_number" value="<?php echo htmlspecialchars($request['applicant_id_number']); ?>">

      <label>Purpose</label>
      <select name="purpose" id="purposeSelect">
        <option value="pickup" <?php echo $request['request_status'] !== 'approved' ? 'disabled' : ''; ?>>Certificate Pickup<?php echo $request['request_status'] !== 'approved' ? ' (not approved yet)' : ''; ?></option>
        <option value="biometric">Biometric Capture</option>
        <option value="affidavit">Sworn Affidavit</option>
      </select>

      <label>Date</label>
      <select name="appointment_date" id="dateSelect">
        <option value="">-- select a date --</option>
        <?php foreach ($bookableDates as $d): ?>
          <option value="<?php echo $d; ?>"><?php echo date('D, M j', strtotime($d)); ?></option>
        <?php endforeach; ?>
      </select>

      <label>Time</label>
      <select name="appointment_time" id="timeSelect" required>
        <option value="">-- select a date first --</option>
      </select>

      <button type="submit" id="bookBtn" disabled>Book Appointment</button>
    </form>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<?php if ($request && !$booked): ?>
<script>
(function () {
  const dateSelect = document.getElementById('dateSelect');
  const timeSelect = document.getElementById('timeSelect');
  const bookBtn = document.getElementById('bookBtn');
  const requestId = <?php echo (int)$request['id']; ?>;
  const idNumber = <?php echo json_encode($request['applicant_id_number']); ?>;

  dateSelect.addEventListener('change', () => {
    timeSelect.innerHTML = '<option value="">Loading...</option>';
    bookBtn.disabled = true;
    if (!dateSelect.value) {
      timeSelect.innerHTML = '<option value="">-- select a date first --</option>';
      return;
    }
    const params = new URLSearchParams({ request_id: requestId, applicant_id_number: idNumber, date: dateSelect.value });
    fetch('appointment_slots.php?' + params.toString())
      .then(res => res.json())
      .then(data => {
        if (!data.slots || data.slots.length === 0) {
          timeSelect.innerHTML = '<option value="">No slots left this day</option>';
          return;
        }
        timeSelect.innerHTML = '<option value="">-- select a time --</option>' +
          data.slots.map(s => `<option value="${s.time}">${s.time} (${s.remaining} spots left)</option>`).join('');
      })
      .catch(() => { timeSelect.innerHTML = '<option value="">Could not load slots</option>'; });
  });

  timeSelect.addEventListener('change', () => {
    bookBtn.disabled = !timeSelect.value;
  });
})();
</script>
<?php endif; ?>

</body>
</html>
