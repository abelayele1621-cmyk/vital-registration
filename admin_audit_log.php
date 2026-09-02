<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

$actionFilter = trim($_GET['action'] ?? '');

$sql = "SELECT * FROM audit_log";
if ($actionFilter !== '') {
    $sql .= " WHERE action = ?";
}
$sql .= " ORDER BY created_at DESC LIMIT 300";

$stmt = $conn->prepare($sql);
if ($actionFilter !== '') {
    $stmt->bind_param("s", $actionFilter);
}
$stmt->execute();
$result = $stmt->get_result();

$actionLabels = [
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'marked_paid' => 'Marked Paid',
    'note_added' => 'Note Added',
    'note_revision_requested' => 'Revision Requested',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Audit Log - Admin</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
<style>
  td.details-cell { max-width: 320px; font-size: 13px; }
  .action-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; }
  .action-approved { background:#e3f6e9; color:#1a7a3c; }
  .action-rejected { background:#fdecec; color:#b00020; }
  .action-marked_paid { background:#e8f0fd; color:#1a4d9a; }
  .action-note_added { background:#f1f1f5; color:#444; }
  .action-note_revision_requested { background:#fff4e8; color:#b3590a; }
</style>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <h2>Audit Log</h2>
  <p><a href="admin.php">&larr; Back to Requests</a></p>
  <p>A record of every approve/reject, payment override, and reviewer note — who did it and when.</p>

  <div class="filter-bar">
    <a href="admin_audit_log.php" class="<?php echo $actionFilter === '' ? 'btn' : ''; ?>">All</a>
    <a href="admin_audit_log.php?action=approved" class="<?php echo $actionFilter === 'approved' ? 'btn' : ''; ?>">Approved</a>
    <a href="admin_audit_log.php?action=rejected" class="<?php echo $actionFilter === 'rejected' ? 'btn' : ''; ?>">Rejected</a>
    <a href="admin_audit_log.php?action=marked_paid" class="<?php echo $actionFilter === 'marked_paid' ? 'btn' : ''; ?>">Marked Paid</a>
    <a href="admin_audit_log.php?action=note_revision_requested" class="<?php echo $actionFilter === 'note_revision_requested' ? 'btn' : ''; ?>">Revisions</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th><th>Request</th><th>Admin</th><th>Action</th><th>Details</th><th>When</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" style="text-align:center; padding:20px; color:#777;">No audit entries yet.</td></tr>
      <?php endif; ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="ID"><?php echo $row['id']; ?></td>
          <td data-label="Request"><?php echo $row['request_id'] ? '<a href="admin.php?q=' . (int)$row['request_id'] . '">#' . (int)$row['request_id'] . '</a>' : '—'; ?></td>
          <td data-label="Admin"><?php echo htmlspecialchars($row['admin_username']); ?></td>
          <td data-label="Action">
            <span class="action-badge action-<?php echo htmlspecialchars($row['action']); ?>">
              <?php echo htmlspecialchars($actionLabels[$row['action']] ?? $row['action']); ?>
            </span>
          </td>
          <td data-label="Details" class="details-cell"><?php echo htmlspecialchars($row['details'] ?? ''); ?></td>
          <td data-label="When"><?php echo htmlspecialchars($row['created_at']); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
