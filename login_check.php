<?php
require_once 'includes/session.php';
require_once 'includes/config.php';
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

const MAX_ATTEMPTS   = 5;
const LOCKOUT_MINUTES = 15;

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Check for an active lockout on this IP.
$stmt = $conn->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_address = ?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$attemptRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($attemptRow && $attemptRow['locked_until'] && strtotime($attemptRow['locked_until']) > time()) {
    header('Location: login.php?error=' . urlencode('Too many failed attempts. Please try again later.'));
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$validUsername = hash_equals(ADMIN_USERNAME, $username);
$validPassword = ADMIN_PASSWORD_HASH !== '' && password_verify($password, ADMIN_PASSWORD_HASH);

if ($validUsername && $validPassword) {
    // Successful login: clear any attempt record for this IP.
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();

    // Regenerate the session ID to prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    header('Location: admin.php');
    exit;
} else {
    $attempts = ($attemptRow['attempts'] ?? 0) + 1;
    $lockedUntil = null;
    if ($attempts >= MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
    }

    $stmt = $conn->prepare("
        INSERT INTO login_attempts (ip_address, attempts, locked_until, last_attempt)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE attempts = ?, locked_until = ?, last_attempt = NOW()
    ");
    $stmt->bind_param("sisis", $ip, $attempts, $lockedUntil, $attempts, $lockedUntil);
    $stmt->execute();
    $stmt->close();

    $message = $lockedUntil
        ? 'Too many failed attempts. Please try again in ' . LOCKOUT_MINUTES . ' minutes.'
        : 'Invalid username or password.';

    header('Location: login.php?error=' . urlencode($message));
    exit;
}
