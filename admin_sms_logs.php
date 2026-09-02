<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['sent', 'failed'];
$statusFilter = in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : '';

$sql = "SELECT * FROM sms_logs";
if ($statusFilter !== '') {
    $sql .= " WHERE status = ?";
}
$sql .= " ORDER BY created_at DESC LIMIT 300";

$stmt = $conn->prepare($sql);
if ($statusFilter !== '') {
    $stmt->bind_param("s", $statusFilter);
}
$stmt->execute();
$result = $stmt->get_result();

$failedCount = $conn->query("SELECT COUNT(*) AS c FROM sms_logs WHERE status = 'failed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMS Delivery Logs - Admin</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
<style>
  .status-sent { color: #1a7a3c; font-weight: bold; }
  .status-failed { color: #b00020; font-weight: bold; }
  td.message-cell { max-width: 320px; font-size: 13px; }
</style>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <h2>SMS Delivery Logs</h2>

  <?php if (isset($_GET['error'])): ?>
    <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
  <?php endif; ?>
  <?php if (isset($_GET['retried'])): ?>
    <p class="success">Message resent — new status: <?php echo htmlspecialchars($_GET['retried']); ?>.</p>
  <?php endif; ?>

  <p><?php echo (int)$failedCount; ?> failed message<?php echo $failedCount == 1 ? '' : 's'; ?> currently need attention.</p>

  <div class="filter-bar">
    <a href="admin_sms_logs.php" class="<?php echo $statusFilter === '' ? 'btn' : ''; ?>">All</a>
    <a href="admin_sms_logs.php?status=sent" class="<?php echo $statusFilter === 'sent' ? 'btn' : ''; ?>">Sent</a>
    <a href="admin_sms_logs.php?status=failed" class="<?php echo $statusFilter === 'failed' ? 'btn' : ''; ?>">Failed</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>Log ID</th><th>Request</th><th>Recipient</th><th>Message</th><th>Status</th><th>Attempts</th><th>Sent At</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="8" style="text-align:center; padding:20px; color:#777;">No SMS log entries yet.</td></tr>
      <?php endif; ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="Log ID"><?php echo $row['id']; ?></td>
          <td data-label="Request"><?php echo $row['request_id'] ? '<a href="admin.php?q=' . (int)$row['request_id'] . '">#' . (int)$row['request_id'] . '</a>' : '—'; ?></td>
          <td data-label="Recipient"><?php echo htmlspecialchars($row['recipient']); ?></td>
          <td data-label="Message" class="message-cell"><?php echo htmlspecialchars($row['message']); ?></td>
          <td data-label="Status" class="status-<?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
          <td data-label="Attempts"><?php echo (int)$row['attempts']; ?></td>
          <td data-label="Sent At"><?php echo htmlspecialchars($row['created_at']); ?></td>
          <td data-label="Action">
            <?php if ($row['status'] === 'failed'): ?>
              <form action="resend_sms.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="log_id" value="<?php echo $row['id']; ?>">
                <button type="submit">Retry</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
