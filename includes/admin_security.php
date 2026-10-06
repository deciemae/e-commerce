<?php

require_once __DIR__ . '/session.php';
startApplicationSession();

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function adminCsrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function adminCsrfIsValid(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['admin_csrf_token'])
        && is_string($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $token);
}

function adminSetFlash(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'info'];
    $_SESSION['admin_flash'] = [
        'type' => in_array($type, $allowedTypes, true) ? $type : 'info',
        'message' => trim($message),
    ];
}

function adminPullFlash(): ?array
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    return is_array($flash) ? $flash : null;
}

function adminRedirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function adminRequireValidCsrf(string $redirectPath): void
{
    if (adminCsrfIsValid($_POST['csrf_token'] ?? null)) {
        return;
    }

    adminSetFlash('error', 'Your session expired. Please try again.');
    adminRedirect($redirectPath);
}

function adminRemoveStoredUpload(?string $relativePath, string $allowedRoot): bool
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '' || preg_match('/^https?:\/\//i', $relativePath)) {
        return false;
    }

    $root = realpath($allowedRoot);
    $candidate = realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
    if ($root === false || $candidate === false || !is_file($candidate)) {
        return false;
    }

    $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($candidate, $rootPrefix)) {
        return false;
    }

    return unlink($candidate);
}
