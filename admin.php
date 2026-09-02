<?php
require_once 'includes/require_login.php';
require_once 'includes/db.php';

// --- Search / filter (initial page load; live updates happen via
// admin_search.php + JS below without a reload) ---
$search        = trim($_GET['q'] ?? '');
$typeFilter    = trim($_GET['type'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');
$paymentFilter = trim($_GET['payment'] ?? '');
$phoneSuffix   = trim($_GET['phone'] ?? '');

$allowedTypes    = ['birth', 'death', 'marriage', 'adoption'];
$allowedStatuses = ['pending', 'approved', 'rejected', 'needs_revision'];
$allowedPayments = ['unpaid', 'pending_payment', 'paid'];

$typeFilter    = in_array($typeFilter, $allowedTypes, true) ? $typeFilter : '';
$statusFilter  = in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : '';
$paymentFilter = in_array($paymentFilter, $allowedPayments, true) ? $paymentFilter : '';
$phoneSuffix   = preg_replace('/[^0-9]/', '', $phoneSuffix);

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(applicant_name LIKE ? OR person_full_name LIKE ? OR applicant_id_number LIKE ? OR id = ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = ctype_digit($search) ? (int)$search : 0;
    $types .= 'sssi';
}
if ($phoneSuffix !== '') {
    $conditions[] = "applicant_phone LIKE ?";
    $params[] = '%' . $phoneSuffix;
    $types .= 's';
}
if ($typeFilter !== '') {
    $conditions[] = "certificate_type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}
if ($statusFilter !== '') {
    $conditions[] = "request_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($paymentFilter !== '') {
    $conditions[] = "payment_status = ?";
    $params[] = $paymentFilter;
    $types .= 's';
}

$sql = "SELECT * FROM requests";
if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$totalCount = $result->num_rows;

// Preserve the current filters when building links (e.g. pagination later).
$hasFilters = ($search !== '' || $typeFilter !== '' || $statusFilter !== '' || $paymentFilter !== '' || $phoneSuffix !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Certificate Requests</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="page-content wide">
  <h2>Certificate Requests - Admin</h2>
  <p>Logged in as <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>

  <?php if (isset($_GET['error'])): ?>
    <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
  <?php endif; ?>
  <?php if (isset($_GET['noteAdded'])): ?>
    <p class="success">Note saved for request #<?php echo (int)$_GET['noteAdded']; ?>.</p>
  <?php endif; ?>

  <button type="button" id="toggleAnalytics" class="btn">📊 Show Analytics</button>

  <div id="analyticsPanel" style="display:none; margin: 16px 0;">
    <div class="analytics-summary" id="analyticsSummary"></div>
    <div class="analytics-grid">
      <div class="analytics-card">
        <h4>Daily Applications (30 days)</h4>
        <canvas id="dailyChart" height="220"></canvas>
      </div>
      <div class="analytics-card">
        <h4>Monthly Applications (12 months)</h4>
        <canvas id="monthlyChart" height="220"></canvas>
      </div>
      <div class="analytics-card">
        <h4>Revenue by Day, ETB (30 days)</h4>
        <canvas id="revenueChart" height="220"></canvas>
      </div>
      <div class="analytics-card">
        <h4>Status Breakdown (all-time)</h4>
        <canvas id="statusChart" height="220"></canvas>
      </div>
      <div class="analytics-card">
        <h4>By Certificate Type (all-time)</h4>
        <canvas id="typeChart" height="220"></canvas>
      </div>
    </div>
  </div>

  <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars(csrf_token()); ?>">

  <form class="filter-bar" method="GET" action="admin.php" id="filterForm">
    <div>
      <label for="q">Search</label>
      <input type="text" id="q" name="q" placeholder="Name, ID number, or request ID" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
    </div>
    <div>
      <label for="phone">Phone suffix</label>
      <input type="text" id="phone" name="phone" placeholder="last digits" value="<?php echo htmlspecialchars($phoneSuffix); ?>" autocomplete="off">
    </div>
    <div>
      <label for="type">Type</label>
      <select id="type" name="type">
        <option value="">All</option>
        <option value="birth" <?php echo $typeFilter === 'birth' ? 'selected' : ''; ?>>Birth</option>
        <option value="death" <?php echo $typeFilter === 'death' ? 'selected' : ''; ?>>Death</option>
        <option value="marriage" <?php echo $typeFilter === 'marriage' ? 'selected' : ''; ?>>Marriage</option>
        <option value="adoption" <?php echo $typeFilter === 'adoption' ? 'selected' : ''; ?>>Adoption</option>
      </select>
    </div>
    <div>
      <label for="status">Request Status</label>
      <select id="status" name="status">
        <option value="">All</option>
        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        <option value="needs_revision" <?php echo $statusFilter === 'needs_revision' ? 'selected' : ''; ?>>Needs Revision</option>
      </select>
    </div>
    <div>
      <label for="payment">Payment</label>
      <select id="payment" name="payment">
        <option value="">All</option>
        <option value="unpaid" <?php echo $paymentFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
        <option value="pending_payment" <?php echo $paymentFilter === 'pending_payment' ? 'selected' : ''; ?>>Pending Payment</option>
        <option value="paid" <?php echo $paymentFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
      </select>
    </div>
    <div>
      <button type="submit">Filter</button>
    </div>
    <?php if ($hasFilters): ?>
      <div>
        <a href="admin.php" style="padding: 7px 0; display: inline-block;" id="clearFiltersLink">Clear filters</a>
      </div>
    <?php endif; ?>
  </form>

  <p class="result-count" id="resultCount"><?php echo $totalCount; ?> request<?php echo $totalCount === 1 ? '' : 's'; ?> found<?php echo $hasFilters ? ' matching your filters' : ''; ?>.</p>

  <div class="bulk-bar" id="bulkBar" style="display:none;">
    <span id="bulkCount">0 selected</span>
    <button type="button" class="bulk-action" data-action="approve">Approve Selected</button>
    <button type="button" class="bulk-action" data-action="reject">Reject Selected</button>
    <button type="button" class="bulk-action" data-action="mark_paid">Mark Paid Selected</button>
  </div>

  <table id="requestsTable">
    <thead>
      <tr>
        <th><input type="checkbox" id="selectAll" title="Select all"></th>
        <th>ID</th><th>Type</th><th>Applicant</th><th>Person</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th>
      </tr>
    </thead>
    <tbody id="requestsBody">
      <?php if ($totalCount === 0): ?>
        <tr><td colspan="9" style="text-align:center; padding: 20px; color:#777;">No matching requests.</td></tr>
      <?php endif; ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="Select"><input type="checkbox" class="row-select" value="<?php echo $row['id']; ?>"></td>
          <td data-label="ID"><?php echo $row['id']; ?></td>
          <td data-label="Type"><?php echo htmlspecialchars($row['certificate_type']); ?></td>
          <td data-label="Applicant"><?php echo htmlspecialchars($row['applicant_name']); ?></td>
          <td data-label="Person"><?php echo htmlspecialchars($row['person_full_name']); ?></td>
          <td data-label="Payment"><?php echo htmlspecialchars($row['payment_status']); ?></td>
          <td data-label="Status" class="status-<?php echo htmlspecialchars($row['request_status']); ?>">
            <?php echo htmlspecialchars($row['request_status']); ?>
          </td>
          <td data-label="Date"><?php echo $row['created_at']; ?></td>
          <td data-label="Action">
            <form class="inline" action="update_status.php" method="POST">
              <?php csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
              <input type="hidden" name="request_status" value="approved">
              <button type="submit">Approve</button>
            </form>
            <form class="inline" action="update_status.php" method="POST">
              <?php csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
              <input type="hidden" name="request_status" value="rejected">
              <button type="submit">Reject</button>
            </form>
            <?php if ($row['payment_status'] !== 'paid'): ?>
              <form class="inline" action="mark_paid.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit">Mark Paid</button>
              </form>
            <?php endif; ?>
            <?php if ($row['request_status'] === 'approved'): ?>
              <form class="inline" action="download.php" method="POST" target="_blank">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit" class="download-link">Download PDF</button>
              </form>
            <?php endif; ?>
            <?php if (!empty($row['applicant_id_document'])): ?>
              <a class="download-link" href="view_document.php?id=<?php echo $row['id']; ?>" target="_blank">View ID</a>
            <?php endif; ?>
            <button type="button" class="notes-btn" data-id="<?php echo $row['id']; ?>">Notes</button>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php include 'includes/footer.php'; ?>

<script>
(function () {
  // Live AJAX filtering: every keystroke/select change re-queries
  // admin_search.php and swaps in fresh rows, no page reload. The GET
  // form above still works normally for non-JS clients / bookmarking.
  const form = document.getElementById('filterForm');
  const tbody = document.getElementById('requestsBody');
  const resultCount = document.getElementById('resultCount');
  let debounceTimer = null;

  function statusBadgeClass(status) {
    return 'status-' + status;
  }

  function renderRows(rows) {
    if (rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 20px; color:#777;">No matching requests.</td></tr>';
      return;
    }
    const csrf = document.getElementById('csrfToken').value;
    tbody.innerHTML = rows.map(r => {
      const markPaidBtn = r.payment_status !== 'paid' ? `
        <form class="inline" action="mark_paid.php" method="POST">
          <input type="hidden" name="csrf_token" value="${csrf}">
          <input type="hidden" name="id" value="${r.id}">
          <button type="submit">Mark Paid</button>
        </form>` : '';
      const downloadBtn = r.request_status === 'approved' ? `
        <form class="inline" action="download.php" method="POST" target="_blank">
          <input type="hidden" name="id" value="${r.id}">
          <button type="submit" class="download-link">Download PDF</button>
        </form>` : '';
      const viewIdLink = r.has_document ? `
        <a class="download-link" href="view_document.php?id=${r.id}" target="_blank">View ID</a>` : '';
      return `
      <tr>
        <td data-label="Select"><input type="checkbox" class="row-select" value="${r.id}"></td>
        <td data-label="ID">${r.id}</td>
        <td data-label="Type">${escapeHtml(r.certificate_type)}</td>
        <td data-label="Applicant">${escapeHtml(r.applicant_name)}</td>
        <td data-label="Person">${escapeHtml(r.person_full_name)}</td>
        <td data-label="Payment">${escapeHtml(r.payment_status)}</td>
        <td data-label="Status" class="${statusBadgeClass(r.request_status)}">${escapeHtml(r.request_status)}</td>
        <td data-label="Date">${escapeHtml(r.created_at)}</td>
        <td data-label="Action">
          <form class="inline" action="update_status.php" method="POST">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="id" value="${r.id}">
            <input type="hidden" name="request_status" value="approved">
            <button type="submit">Approve</button>
          </form>
          <form class="inline" action="update_status.php" method="POST">
            <input type="hidden" name="csrf_token" value="${csrf}">
            <input type="hidden" name="id" value="${r.id}">
            <input type="hidden" name="request_status" value="rejected">
            <button type="submit">Reject</button>
          </form>
          ${markPaidBtn}${downloadBtn}${viewIdLink}
          <button type="button" class="notes-btn" data-id="${r.id}">Notes</button>
        </td>
      </tr>`;
    }).join('');
    attachNotesHandlers();
    syncBulkBar();
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
  }

  function runSearch() {
    const params = new URLSearchParams(new FormData(form));
    fetch('admin_search.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(res => res.json())
      .then(data => {
        renderRows(data.rows);
        resultCount.textContent = data.count + ' request' + (data.count === 1 ? '' : 's') + ' found (live).';
      })
      .catch(() => { /* fall back silently — the static table is still usable */ });
  }

  form.querySelectorAll('input, select').forEach(el => {
    const evt = el.tagName === 'SELECT' ? 'change' : 'input';
    el.addEventListener(evt, () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(runSearch, 250);
    });
  });

  form.addEventListener('submit', e => {
    e.preventDefault();
    clearTimeout(debounceTimer);
    runSearch();
  });

  // --- Bulk selection ---
  const bulkBar = document.getElementById('bulkBar');
  const bulkCount = document.getElementById('bulkCount');
  const selectAll = document.getElementById('selectAll');
  const csrfToken = document.getElementById('csrfToken').value;

  function syncBulkBar() {
    const checked = document.querySelectorAll('.row-select:checked');
    bulkBar.style.display = checked.length > 0 ? 'flex' : 'none';
    bulkCount.textContent = checked.length + ' selected';
  }

  document.addEventListener('change', e => {
    if (e.target.classList.contains('row-select')) syncBulkBar();
  });

  selectAll.addEventListener('change', () => {
    document.querySelectorAll('.row-select').forEach(cb => { cb.checked = selectAll.checked; });
    syncBulkBar();
  });

  document.querySelectorAll('.bulk-action').forEach(btn => {
    btn.addEventListener('click', async () => {
      const ids = Array.from(document.querySelectorAll('.row-select:checked')).map(cb => cb.value);
      if (ids.length === 0) return;
      const action = btn.dataset.action;
      const label = { approve: 'approve', reject: 'reject', mark_paid: 'mark as paid' }[action];
      if (!confirm(`${label.charAt(0).toUpperCase() + label.slice(1)} ${ids.length} request(s)?`)) return;

      const endpoint = action === 'mark_paid' ? 'mark_paid.php' : 'update_status.php';
      btn.disabled = true;
      for (const id of ids) {
        const body = new URLSearchParams({ csrf_token: csrfToken, id: id });
        if (action !== 'mark_paid') body.set('request_status', action === 'approve' ? 'approved' : 'rejected');
        try {
          await fetch(endpoint, { method: 'POST', body, redirect: 'manual' });
        } catch (e) { /* keep going through the rest of the batch */ }
      }
      window.location.reload();
    });
  });

  // --- Notes / revision modal ---
  function attachNotesHandlers() {
    document.querySelectorAll('.notes-btn').forEach(btn => {
      btn.onclick = () => openNotesModal(btn.dataset.id);
    });
  }

  const modal = document.getElementById('notesModal');
  const modalBody = document.getElementById('notesModalBody');
  const modalRequestId = document.getElementById('notesModalRequestId');

  function openNotesModal(id) {
    modalRequestId.textContent = id;
    document.getElementById('noteRequestIdField').value = id;
    modalBody.innerHTML = '<p>Loading notes…</p>';
    modal.style.display = 'flex';
    fetch('admin_notes.php?id=' + encodeURIComponent(id))
      .then(res => res.json())
      .then(data => {
        if (!data.notes || data.notes.length === 0) {
          modalBody.innerHTML = '<p style="color:#777;">No notes yet for this request.</p>';
          return;
        }
        modalBody.innerHTML = data.notes.map(n => `
          <div class="note-entry ${n.requires_revision ? 'note-revision' : ''}">
            <p>${escapeHtml(n.note)}</p>
            <p class="note-meta">${escapeHtml(n.created_by || 'admin')} &mdash; ${escapeHtml(n.created_at)}${n.requires_revision ? ' &mdash; revision requested' : ''}</p>
          </div>
        `).join('');
      })
      .catch(() => { modalBody.innerHTML = '<p class="error">Could not load notes.</p>'; });
  }

  document.getElementById('closeNotesModal').addEventListener('click', () => { modal.style.display = 'none'; });
  modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

  attachNotesHandlers();

  // --- Analytics panel ---
  const toggleAnalyticsBtn = document.getElementById('toggleAnalytics');
  const analyticsPanel = document.getElementById('analyticsPanel');
  let analyticsLoaded = false;
  let charts = {};

  toggleAnalyticsBtn.addEventListener('click', () => {
    const showing = analyticsPanel.style.display !== 'none';
    analyticsPanel.style.display = showing ? 'none' : 'block';
    toggleAnalyticsBtn.textContent = showing ? '📊 Show Analytics' : '📊 Hide Analytics';
    if (!showing && !analyticsLoaded) loadAnalytics();
  });

  function loadAnalytics() {
    fetch('admin_analytics.php')
      .then(res => res.json())
      .then(data => {
        analyticsLoaded = true;
        document.getElementById('analyticsSummary').innerHTML =
          `<strong>ETB ${Number(data.total_revenue_30d).toLocaleString()}</strong> collected in the last 30 days &mdash; ` +
          `<strong>${data.status_counts.pending}</strong> pending, ` +
          `<strong>${data.status_counts.needs_revision}</strong> awaiting citizen action, ` +
          `<strong>${data.status_counts.approved}</strong> approved, ` +
          `<strong>${data.status_counts.rejected}</strong> rejected (all-time).`;

        const gridColor = 'rgba(0,0,0,0.06)';

        charts.daily = new Chart(document.getElementById('dailyChart'), {
          type: 'line',
          data: {
            labels: data.daily.map(d => d.date.slice(5)),
            datasets: [{ label: 'Applications', data: data.daily.map(d => d.count), borderColor: '#1a4d9a', backgroundColor: 'rgba(26,77,154,0.1)', fill: true, tension: 0.25 }]
          },
          options: { scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
        });

        charts.monthly = new Chart(document.getElementById('monthlyChart'), {
          type: 'bar',
          data: {
            labels: data.monthly.map(m => m.month),
            datasets: [{ label: 'Applications', data: data.monthly.map(m => m.count), backgroundColor: '#c9a227' }]
          },
          options: { scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
        });

        charts.revenue = new Chart(document.getElementById('revenueChart'), {
          type: 'line',
          data: {
            labels: data.revenue.map(d => d.date.slice(5)),
            datasets: [{ label: 'Revenue (ETB)', data: data.revenue.map(d => d.revenue), borderColor: '#1a7a3c', backgroundColor: 'rgba(26,122,60,0.1)', fill: true, tension: 0.25 }]
          },
          options: { scales: { y: { beginAtZero: true, grid: { color: gridColor } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
        });

        charts.status = new Chart(document.getElementById('statusChart'), {
          type: 'doughnut',
          data: {
            labels: ['Pending', 'Approved', 'Rejected', 'Needs Revision'],
            datasets: [{
              data: [data.status_counts.pending, data.status_counts.approved, data.status_counts.rejected, data.status_counts.needs_revision],
              backgroundColor: ['#a3791a', '#1a7a3c', '#b00020', '#b3590a']
            }]
          },
          options: { plugins: { legend: { position: 'bottom' } } }
        });

        charts.type = new Chart(document.getElementById('typeChart'), {
          type: 'doughnut',
          data: {
            labels: ['Birth', 'Death', 'Marriage', 'Adoption'],
            datasets: [{
              data: [data.type_counts.birth, data.type_counts.death, data.type_counts.marriage, data.type_counts.adoption],
              backgroundColor: ['#1a4d9a', '#555b66', '#c9a227', '#c2185b']
            }]
          },
          options: { plugins: { legend: { position: 'bottom' } } }
        });
      })
      .catch(() => {
        document.getElementById('analyticsSummary').innerHTML = '<p class="error">Could not load analytics.</p>';
      });
  }
})();
</script>

<div id="notesModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Notes for Request #<span id="notesModalRequestId"></span></h3>
      <button type="button" id="closeNotesModal" class="modal-close">&times;</button>
    </div>
    <div id="notesModalBody"></div>
    <form action="add_note.php" method="POST" class="note-form">
      <?php csrf_field(); ?>
      <input type="hidden" name="id" id="noteRequestIdField" value="">
      <label>Add a note</label>
      <textarea name="note" rows="3" maxlength="1000" required placeholder="e.g. Please re-upload a clearer ID photo"></textarea>
      <label class="checkbox-label">
        <input type="checkbox" name="requires_revision" value="1">
        Requires citizen revision (flags the request and texts them instead of an outright rejection)
      </label>
      <button type="submit">Save Note</button>
    </form>
  </div>
</div>

</body>
</html>
