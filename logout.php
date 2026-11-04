<?php
/**
 * Logout script
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();

$_SESSION['flash_success'] = 'You have been securely signed out.';
header('Location: /login.php');
exit;
