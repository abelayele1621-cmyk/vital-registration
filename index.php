<?php
require_once 'includes/session.php';
require_once 'includes/config.php';
require_once 'includes/lang.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Debre Birhan City Civil Registration - Home</title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/theme-toggle.js"></script>
</head>
<body>

<?php $assetPath = ''; include 'includes/header.php'; ?>

<div class="hero-banner hero-banner-tall">
  <h2><?php echo htmlspecialchars(t('hero_title')); ?></h2>
  <p><?php echo htmlspecialchars(t('hero_desc')); ?></p>
  <div class="hero-cta">
    <a href="request.php" class="btn hero-btn-primary"><?php echo htmlspecialchars(t('hero_btn_request')); ?></a>
    <a href="status.php" class="btn hero-btn-secondary"><?php echo htmlspecialchars(t('hero_btn_status')); ?></a>
  </div>
</div>

<div class="page-content wide">

  <!-- ===== Services ===== -->
  <section class="section-block">
    <h2 class="section-title"><?php echo htmlspecialchars(t('services_title')); ?></h2>
    <p class="section-intro"><?php echo htmlspecialchars(t('services_intro')); ?></p>

    <div class="service-grid">
      <div class="service-card">
        <div class="service-icon">📝</div>
        <h3><?php echo htmlspecialchars(t('svc1_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc1_desc')); ?></p>
      </div>
      <div class="service-card">
        <div class="service-icon">💳</div>
        <h3><?php echo htmlspecialchars(t('svc2_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc2_desc')); ?></p>
      </div>
      <div class="service-card">
        <div class="service-icon">📍</div>
        <h3><?php echo htmlspecialchars(t('svc3_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc3_desc')); ?></p>
      </div>
      <div class="service-card">
        <div class="service-icon">💬</div>
        <h3><?php echo htmlspecialchars(t('svc4_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc4_desc')); ?></p>
      </div>
      <div class="service-card">
        <div class="service-icon">✅</div>
        <h3><?php echo htmlspecialchars(t('svc5_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc5_desc')); ?></p>
      </div>
      <div class="service-card">
        <div class="service-icon">📊</div>
        <h3><?php echo htmlspecialchars(t('svc6_title')); ?></h3>
        <p><?php echo htmlspecialchars(t('svc6_desc')); ?></p>
      </div>
    </div>
  </section>

  <!-- ===== About Debre Birhan ===== -->
  <section class="section-block about-section">
    <h2 class="section-title"><?php echo htmlspecialchars(t('about_title')); ?></h2>
    <p><?php echo htmlspecialchars(t('about_p1')); ?></p>
    <p><?php echo htmlspecialchars(t('about_p2')); ?></p>
  </section>

  <!-- ===== Kebeles / Sub-cities served ===== -->
  <section class="section-block">
    <h2 class="section-title"><?php echo t('kebele_title'); ?></h2>
    <p class="section-intro"><?php echo htmlspecialchars(t('kebele_intro')); ?></p>

    <div class="kebele-grid">
      <?php foreach ($GLOBALS['BRANCH_MAP'] as $kebele => $branch): ?>
        <div class="kebele-chip">
          <span class="kebele-name"><?php echo htmlspecialchars($kebele); ?></span>
          <span class="kebele-branch"><?php echo htmlspecialchars($branch); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="field-hint"><?php echo t('kebele_hint'); ?></p>
  </section>

  <!-- ===== Final CTA ===== -->
  <section class="section-block final-cta">
    <h2 class="section-title"><?php echo htmlspecialchars(t('cta_title')); ?></h2>
    <div class="hero-cta">
      <a href="request.php" class="btn"><?php echo htmlspecialchars(t('cta_btn_request')); ?></a>
      <a href="public_login.php" class="btn hero-btn-secondary-dark"><?php echo htmlspecialchars(t('cta_btn_dashboard')); ?></a>
    </div>
  </section>

</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>
