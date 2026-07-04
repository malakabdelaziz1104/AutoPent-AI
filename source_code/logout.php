<?php

require_once 'includes/functions.php';

startSession();

// Flash message
$_SESSION['flash_message'] = 'See you again! You have been logged out successfully.';
$_SESSION['flash_type'] = 'success';

// Remove login data only
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['email']);
unset($_SESSION['logged_in_at']);

// Redirect
header('Location: login.php');
exit;