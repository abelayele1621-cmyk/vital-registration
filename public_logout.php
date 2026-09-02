<?php
require_once 'includes/session.php';

unset($_SESSION['citizen_identifier']);
unset($_SESSION['citizen_channel']);

header('Location: public_login.php');
exit;
