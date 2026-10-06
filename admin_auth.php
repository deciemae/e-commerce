<?php

require_once __DIR__ . '/includes/session.php';
startApplicationSession();

require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/admin_security.php';
require_once __DIR__ . '/config/db.php';

// Admin login is now handled via DB in admin_login.php

function adminIsLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_email']);
}

function currentAdminId(): ?int
{
    return !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function adminLoginUser(string $email): void
{
    $email = strtolower(trim($email));

    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_name'] = 'Administrator';

    // An admin session must never carry a leftover customer identity.
    unset($_SESSION['customer_user_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['cart_id']);

    $conn = getConnection();

    $stmt = $conn->prepare('SELECT admin_id FROM admins WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $_SESSION['admin_id'] = (int)$row['admin_id'];
    }

    // Record last login time for the Account Details card in admin_account.php
    $stmt = $conn->prepare('UPDATE admins SET last_login = NOW() WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    logAdminActivity('Login', 'Admin logged in successfully.');
}

function adminLogout(): void
{
    if (adminIsLoggedIn()) {
        logAdminActivity('Logout', 'Admin logged out.');
    }
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_id']);
}

function requireAdminLogin(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, private');
    }

    if (!adminIsLoggedIn()) {
        $_SESSION['admin_login_error'] = 'Please log in to access the admin panel.';
        header('Location: admin_login.php');
        exit;
    }

    // Keep scrubbing any leftover customer identity on every admin page load,
    // so a session that mixed the two (e.g. from earlier browsing) self-heals.
    unset($_SESSION['customer_user_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['cart_id']);
}

function logAdminActivity(string $action, string $details = ''): void
{
    if (!adminIsLoggedIn()) {
        return;
    }
    $email = $_SESSION['admin_email'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $conn = getConnection();
    $stmt = $conn->prepare('INSERT INTO admin_activity_logs (admin_email, action, details, ip_address) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('ssss', $email, $action, $details, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}
