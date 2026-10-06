<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'includes/customer_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo 'Your session expired. Please return to the store and try again.';
    exit;
}

customerLogout();
unset($_SESSION['customer_csrf_token']);
session_regenerate_id(true);

header('Location: customer_login.php');
exit;
