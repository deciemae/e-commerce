<?php
require_once __DIR__ . '/admin_auth.php';

adminLogout();
$_SESSION['admin_login_error'] = 'You have been logged out.';
header('Location: admin_login.php');
exit;
