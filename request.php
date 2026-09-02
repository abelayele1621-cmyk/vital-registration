<?php
require_once 'includes/session.php';
require_once 'includes/csrf.php';
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Debre Birhan City Civil Registration - Request Certificate</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="hero-banner">
  <h2>Certificate Request Form</h2>
  <p>Birth, death, marriage, divorce, and adoption certificates for Debre Birhan City residents — submit online, pay securely, and track your request every step of the way.</p>
</div>

<div class="page-content">
  <?php if (isset($_GET['error'])): ?>
    <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
  <?php endif; ?>
  <p><a href="status.php">Already submitted a request? Check its status here.</a></p>

  <!-- Step progress indicator -->
  <div class="wizard-steps" id="wizardSteps">
    <div class="wizard-step active" data-step="1">
      <span class="wizard-step-num">1</span>
      <span class="wizard-step-label">Personal Info</span>
    </div>
    <div class="wizard-step" data-step="2">
      <span class="wizard-step-num">2</span>
      <span class="wizard-step-label">Document Upload</span>
    </div>
    <div class="wizard-step" data-step="3">
      <span class="wizard-step-num">3</span>
      <span class="wizard-step-label">Review &amp; Pay</span>
    </div>
  </div>

  <form action="submit_request.php" method="POST" enctype="multipart/form-data" id="requestForm" novalidate>
    <?php csrf_field(); ?>

    <!-- ===== Step 1: Personal Info & Certificate Type Selection ===== -->
    <fieldset class="wizard-panel active" data-panel="1">
      <div class="form-group">
        <label for="certificate_type">Select Certificate Type:</label>
        <select name="certificate_type" id="certificate_type" class="form-control" onchange="toggleFormFields()" required>
            <option value="">-- Select Type --</option>
            <option value="birth">Birth Certificate</option>
            <option value="death">Death Certificate</option>
            <option value="marriage">Marriage Certificate</option>
            <option value="divorce">Divorce Certificate</option>
            <option value="adoption">Adoption Certificate (Gudifecha)</option>
        </select>
      </div>

      <h3>Applicant Info</h3>
      <label>Your Full Name</label>
      <input type="text" name="applicant_name" required>

      <label>Relationship to Person</label>
      <input type="text" name="applicant_relationship" placeholder="self, parent, guardian..." required>

      <label>National ID Number</label>
      <input type="text" name="applicant_id_number" required>

      <label>Phone Number</label>
      <input type="text" name="applicant_phone" placeholder="+2519xxxxxxxx" required>
      <small class="field-hint">Used to text you request updates via SMS.</small>

      <label>Email</label>
      <input type="email" name="applicant_email">

      <label>Address</label>
      <input type="text" name="applicant_address" placeholder="House number, street, etc.">

      <label>Sub-city / Kebele</label>
      <select name="sub_city" required>
        <option value="">-- select your kebele/sub-city --</option>
        <?php foreach ($GLOBALS['BRANCH_MAP'] as $kebele => $branch): ?>
          <option value="<?php echo htmlspecialchars($kebele); ?>"><?php echo htmlspecialchars($kebele); ?></option>
        <?php endforeach; ?>
        <option value="Other">Other / Not Listed</option>
      </select>
      <small class="field-hint">Your request is automatically routed to the branch office covering your kebele.</small>

      <h3>Details of the Person / Event</h3>

      <!-- ===== BIRTH SPECIFIC FIELDS ===== -->
      <div class="cert-section" id="section-birth" style="display:none;">
        <label>Full Name</label>
        <input type="text" name="person_full_name">
        
        <label>Father's Name</label>
        <input type="text" name="father_name">

        <label>Grandfather's Name</label>
        <input type="text" name="person_grandfather_name">

        <label>Date of Birth</label>
        <input type="date" name="person_dob">

        <label>Place / Country of Birth</label>
        <input type="text" name="person_place_of_birth">

        <label>Sex</label>
        <select name="person_sex">
          <option value="">-- select --</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>

        <label>Mother's Full Name</label>
        <input type="text" name="mother_name">

        <label>Mother's Nationality</label>
        <input type="text" name="mother_nationality" value="Ethiopian">

        <label>Father's Nationality</label>
        <input type="text" name="father_nationality" value="Ethiopian">

        <label>Purpose</label>
        <input type="text" name="purpose" placeholder="passport, school, etc.">
      </div>

      <!-- ===== DEATH SPECIFIC FIELDS ===== -->
      <div class="cert-section" id="section-death" style="display:none;">
        <label>Deceased Full Name</label>
        <input type="text" name="person_full_name">

        <label>Father's Name</label>
        <input type="text" name="father_name">

        <label>Grandfather's Name</label>
        <input type="text" name="person_grandfather_name">

        <label>Title / Honorific</label>
        <input type="text" name="deceased_title" placeholder="Mr, Mrs, Ms, Dr...">

        <label>Sex</label>
        <select name="person_sex">
          <option value="">-- select --</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>

        <label>Date of Death</label>
        <input type="date" name="date_of_death">

        <label>Place of Death</label>
        <input type="text" name="place_of_death">

        <label>Place of Registration</label>
        <input type="text" name="person_place_of_birth">

        <label>Mother's Full Name</label>
        <input type="text" name="mother_name">

        <label>Father's Full Name</label>
        <input type="text" name="father_name_duplicate">
      </div>

      <!-- ===== MARRIAGE SPECIFIC FIELDS ===== -->
      <div class="cert-section" id="section-marriage" style="display:none;">
        <label>Primary Spouse Full Name</label>
        <input type="text" name="person_full_name">

        <label>Father's Name</label>
        <input type="text" name="father_name">

        <label>Grandfather's Name</label>
        <input type="text" name="person_grandfather_name">

        <label>Second Spouse's Full Name</label>
        <input type="text" name="spouse_name">

        <label>Date of Marriage</label>
        <input type="date" name="marriage_date">

        <label>Place of Marriage</label>
        <input type="text" name="marriage_place">

        <label>Place of Registration</label>
        <input type="text" name="person_place_of_birth">
      </div>

      <!-- ===== DIVORCE SPECIFIC FIELDS ===== -->
      <div class="cert-section" id="section-divorce" style="display:none;">
        <label>First Spouse Name</label>
        <input type="text" name="person_full_name">

        <label>Father's Name</label>
        <input type="text" name="father_name">

        <label>Grandfather's Name</label>
        <input type="text" name="person_grandfather_name">

        <label>Second Spouse / Ex-Spouse Name</label>
        <input type="text" name="spouse_name">

        <label>Date of Divorce</label>
        <input type="date" name="divorce_date">

        <label>Place / Court of Divorce</label>
        <input type="text" name="divorce_place">

        <label>Place of Registration</label>
        <input type="text" name="person_place_of_birth">
      </div>

      <!-- ===== ADOPTION SPECIFIC FIELDS ===== -->
      <div class="cert-section" id="section-adoption" style="display:none;">
        <label>Adopted Person Full Name</label>
        <input type="text" name="person_full_name">

        <label>Father's Name</label>
        <input type="text" name="father_name">

        <label>Grandfather's Name</label>
        <input type="text" name="person_grandfather_name">

        <label>Sex</label>
        <select name="person_sex">
          <option value="">-- select --</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
        </select>

        <label>Date of Birth</label>
        <input type="date" name="person_dob">

        <label>Adoption Register Form Number</label>
        <input type="text" name="adoption_reg_form_number">

        <label>Place of Registration</label>
        <input type="text" name="person_place_of_birth">

        <label>Mother's Full Name</label>
        <input type="text" name="mother_name">
      </div>

      <div class="wizard-nav">
        <span></span>
        <button type="button" class="wizard-next">Next: Document Upload &rarr;</button>
      </div>
    </fieldset>

    <!-- ===== Step 2: Document Upload ===== -->
    <fieldset class="wizard-panel" data-panel="2">
      <h3>Supporting Document (optional)</h3>
      <p class="field-hint">Upload a scan or photo of the applicant's ID card to speed up review (JPG, PNG, or PDF, max 5MB).</p>

      <label>ID Document</label>
      <input type="file" name="applicant_id_document" id="idDocumentInput" accept=".jpg,.jpeg,.png,.pdf">
      <div id="uploadPreview" class="upload-preview"></div>

      <h3>Additional Request Details</h3>
      <label>Special Category (if applicable)</label>
      <select name="exemption_category" id="exemptionCategory">
        <option value="">None — standard fee applies</option>
        <?php foreach ($GLOBALS['FEE_EXEMPT_CATEGORIES'] as $key => $label): ?>
          <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
        <?php endforeach; ?>
      </select>
      <small class="field-hint">Selecting a category waives the certificate fee, subject to staff verification.</small>

      <label>Number of Copies</label>
      <input type="number" name="num_copies" id="numCopies" value="1" min="1" max="20">

      <label>Delivery Method</label>
      <select name="delivery_method" id="deliveryMethod">
        <option value="pickup">Pickup</option>
        <option value="mail">Mail</option>
      </select>

      <div class="wizard-nav">
        <button type="button" class="wizard-prev">&larr; Back</button>
        <button type="button" class="wizard-next">Next: Review &amp; Pay &rarr;</button>
      </div>
    </fieldset>

    <!-- ===== Step 3: Review & Pay ===== -->
    <fieldset class="wizard-panel" data-panel="3">
      <h3>Review Your Request</h3>
      <p class="field-hint">Please check the details below before continuing to payment.</p>
      <div id="reviewSummary" class="review-summary"></div>

      <p id="feeLine"><strong>Certificate fee: <?php echo number_format(CERTIFICATE_FEE_ETB); ?> ETB</strong> — you'll be taken to our secure payment page next.</p>

      <div class="wizard-nav">
        <button type="button" class="wizard-prev">&larr; Back</button>
        <button type="submit" id="submitRequestBtn">Continue to Payment</button>
      </div>
    </fieldset>
  </form>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Dynamic Field Toggle Function
function toggleFormFields() {
    const selectedType = document.getElementById('certificate_type').value;
    const allSections = document.querySelectorAll('.cert-section');
    
    // Hide all certificate sections first
    allSections.forEach(section => {
        section.style.display = 'none';
    });

    // Show only the section matching the selection
    if (selectedType) {
        const targetSection = document.getElementById('section-' + selectedType);
        if (targetSection) {
            targetSection.style.display = 'block';
        }
    }
}

(function () {
  const panels = Array.from(document.querySelectorAll('.wizard-panel'));
  const steps = Array.from(document.querySelectorAll('.wizard-step'));
  let current = 1;

  function showStep(n) {
    panels.forEach(p => p.classList.toggle('active', Number(p.dataset.panel) === n));
    steps.forEach(s => {
      const stepNum = Number(s.dataset.step);
      s.classList.toggle('active', stepNum === n);
      s.classList.toggle('completed', stepNum < n);
    });
    current = n;
    window.scrollTo({ top: document.getElementById('wizardSteps').offsetTop - 20, behavior: 'smooth' });
    if (n === 3) buildReview();
  }

  function validatePanel(n) {
    const panel = panels.find(p => Number(p.dataset.panel) === n);
    const required = panel.querySelectorAll('[required]');
    for (const field of required) {
      // Check if the field is visible before validating
      if (field.offsetParent !== null && !field.value.trim()) {
        field.classList.add('field-invalid');
        field.focus();
        return false;
      }
      field.classList.remove('field-invalid');
    }
    return true;
  }

  document.querySelectorAll('.wizard-next').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!validatePanel(current)) return;
      showStep(Math.min(3, current + 1));
    });
  });

  document.querySelectorAll('.wizard-prev').forEach(btn => {
    btn.addEventListener('click', () => showStep(Math.max(1, current - 1)));
  });

  // Live upload preview
  const fileInput = document.getElementById('idDocumentInput');
  const preview = document.getElementById('uploadPreview');
  fileInput.addEventListener('change', () => {
    preview.innerHTML = '';
    const file = fileInput.files[0];
    if (!file) return;
    const sizeOk = file.size <= 5 * 1024 * 1024;
    const row = document.createElement('div');
    row.className = 'upload-preview-row' + (sizeOk ? '' : ' field-invalid');
    row.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)' + (sizeOk ? '' : ' — too large, max 5MB');
    preview.appendChild(row);
    if (file.type.startsWith('image/') && sizeOk) {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      img.className = 'upload-preview-thumb';
      preview.appendChild(img);
    }
  });

  function buildReview() {
    const form = document.getElementById('requestForm');
    const rows = [
      ['Certificate Type', form.certificate_type.value],
      ['Applicant Name', form.applicant_name.value],
      ['Relationship', form.applicant_relationship.value],
      ['National ID Number', form.applicant_id_number.value],
      ['Phone', form.applicant_phone.value],
      ['Email', form.applicant_email.value || '—'],
      ['Sub-city / Kebele', form.sub_city.value || '—'],
      ['Person on Certificate', form.person_full_name.value],
      ['Number of Copies', form.num_copies.value],
      ['Delivery Method', form.delivery_method.value],
      ['ID Document', fileInput.files[0] ? fileInput.files[0].name : 'Not attached'],
    ];
    const table = document.createElement('table');
    rows.forEach(([label, value]) => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td><strong>' + label + '</strong></td><td></td>';
      tr.children[1].textContent = value;
      table.appendChild(tr);
    });
    document.getElementById('reviewSummary').innerHTML = '';
    document.getElementById('reviewSummary').appendChild(table);

    const exemptSelect = document.getElementById('exemptionCategory');
    const feeLine = document.getElementById('feeLine');
    if (exemptSelect.value) {
      feeLine.innerHTML = '<strong>Certificate fee: 0 ETB (exemption requested — pending staff verification)</strong>';
    } else {
      feeLine.innerHTML = '<strong>Certificate fee: <?php echo number_format(CERTIFICATE_FEE_ETB); ?> ETB</strong> — you\'ll be taken to our secure payment page next.';
    }
  }

  showStep(1);
  toggleFormFields(); // Run on load
})();
</script>

</body>
</html>