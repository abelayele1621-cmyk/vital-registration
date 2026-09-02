<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$summary = $conn->query("
    SELECT COUNT(*) AS total_sent,
           SUM(rating IS NOT NULL) AS total_responded,
           AVG(rating) AS avg_rating
    FROM satisfaction_ratings
")->fetch_assoc();

$result = $conn->query("
    SELECT sr.*, r.certificate_type, r.applicant_name
    FROM satisfaction_ratings sr
    JOIN requests r ON r.id = sr.request_id
    ORDER BY sr.requested_at DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Satisfaction Ratings - Admin</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <h2>Citizen Satisfaction Ratings</h2>

  <div class="filter-bar" style="gap:24px;">
    <div><label>Surveys Sent</label><p style="margin:4px 0 0; font-size:20px; font-weight:700;"><?php echo (int)$summary['total_sent']; ?></p></div>
    <div><label>Responses</label><p style="margin:4px 0 0; font-size:20px; font-weight:700;"><?php echo (int)$summary['total_responded']; ?></p></div>
    <div><label>Average Rating</label><p style="margin:4px 0 0; font-size:20px; font-weight:700;"><?php echo $summary['avg_rating'] !== null ? number_format((float)$summary['avg_rating'], 1) . ' / 5' : '—'; ?></p></div>
  </div>

  <table>
    <thead>
      <tr><th>Request</th><th>Applicant</th><th>Type</th><th>Rating</th><th>Feedback</th><th>Sent</th><th>Responded</th></tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="7" style="text-align:center; padding:20px; color:#777;">No surveys sent yet.</td></tr>
      <?php endif; ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="Request"><a href="admin.php?q=<?php echo (int)$row['request_id']; ?>">#<?php echo (int)$row['request_id']; ?></a></td>
          <td data-label="Applicant"><?php echo htmlspecialchars($row['applicant_name']); ?></td>
          <td data-label="Type"><?php echo htmlspecialchars(ucfirst($row['certificate_type'])); ?></td>
          <td data-label="Rating"><?php echo $row['rating'] !== null ? str_repeat('&#9733;', (int)$row['rating']) . str_repeat('&#9734;', 5 - (int)$row['rating']) : '<span style="color:#999;">Awaiting reply</span>'; ?></td>
          <td data-label="Feedback"><?php echo htmlspecialchars($row['feedback_text'] ?? ''); ?></td>
          <td data-label="Sent"><?php echo htmlspecialchars($row['requested_at']); ?></td>
          <td data-label="Responded"><?php echo htmlspecialchars($row['responded_at'] ?? '—'); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
