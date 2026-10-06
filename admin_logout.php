<?php
require_once __DIR__ . '/admin_auth.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

if (!adminCsrfIsValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo 'Your session expired. Please return to the administrator panel and try again.';
    exit;
}

adminLogout();
unset($_SESSION['admin_csrf_token']);
session_regenerate_id(true);
$_SESSION['admin_login_notice'] = 'You have been logged out.';
adminRedirect('admin_login.php');
