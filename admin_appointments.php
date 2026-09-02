<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$dateFilter = trim($_GET['date'] ?? '');
// Accept only a well-formed date, otherwise fall back to "all upcoming".
if ($dateFilter !== '' && !DateTime::createFromFormat('Y-m-d', $dateFilter)) {
    $dateFilter = '';
}

$sql = "
  SELECT a.*, r.applicant_name, r.person_full_name, r.certificate_type, r.applicant_phone
  FROM appointments a
  JOIN requests r ON r.id = a.request_id
  WHERE a.status = 'booked'
";
if ($dateFilter !== '') {
    $sql .= " AND a.appointment_date = ?";
} else {
    $sql .= " AND a.appointment_date >= CURDATE()";
}
$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $conn->prepare($sql);
if ($dateFilter !== '') {
    $stmt->bind_param("s", $dateFilter);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Appointments - Admin</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <h2>Upcoming Appointments</h2>

  <form class="filter-bar" method="GET" action="admin_appointments.php">
    <div>
      <label for="date">Date</label>
      <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
    </div>
    <div>
      <button type="submit">Filter</button>
    </div>
    <?php if ($dateFilter !== ''): ?>
      <div><a href="admin_appointments.php" style="padding:7px 0; display:inline-block;">Show all upcoming</a></div>
    <?php endif; ?>
  </form>

  <table>
    <thead>
      <tr>
        <th>Date</th><th>Time</th><th>Purpose</th><th>Request</th><th>Applicant</th><th>Person</th><th>Phone</th><th>Branch</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="8" style="text-align:center; padding:20px; color:#777;">No appointments found.</td></tr>
      <?php endif; ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="Date"><?php echo htmlspecialchars($row['appointment_date']); ?></td>
          <td data-label="Time"><?php echo htmlspecialchars(substr($row['appointment_time'], 0, 5)); ?></td>
          <td data-label="Purpose"><?php echo htmlspecialchars(ucfirst($row['purpose'])); ?></td>
          <td data-label="Request"><a href="admin.php?q=<?php echo (int)$row['request_id']; ?>">#<?php echo (int)$row['request_id']; ?></a></td>
          <td data-label="Applicant"><?php echo htmlspecialchars($row['applicant_name']); ?></td>
          <td data-label="Person"><?php echo htmlspecialchars($row['person_full_name']); ?></td>
          <td data-label="Phone"><?php echo htmlspecialchars($row['applicant_phone']); ?></td>
          <td data-label="Branch"><?php echo htmlspecialchars($row['branch_office']); ?></td>
        </tr>
      <?php endwhile; ?>
      <?php $stmt->close(); ?>
    </tbody>
  </table>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
