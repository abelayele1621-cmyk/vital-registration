<?php
require_once __DIR__ . '/includes/lang.php';

$lang = $_GET['lang'] ?? 'en';
if (array_key_exists($lang, SUPPORTED_LANGUAGES)) {
    $_SESSION['lang'] = $lang;
}

// Redirect back to wherever the switcher was clicked from, falling back
// to the homepage if there's no usable referrer.
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;
