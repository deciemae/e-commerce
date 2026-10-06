<?php

$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

session_save_path(sys_get_temp_dir());
session_id('bloom-basket-admin-security-' . bin2hex(random_bytes(4)));
require_once __DIR__ . '/../includes/admin_security.php';

$token = adminCsrfToken();
check((bool)preg_match('/^[a-f0-9]{64}$/', $token), 'CSRF token must be 32 random bytes encoded as hex.');
check(adminCsrfToken() === $token, 'CSRF token must remain stable within a session.');
check(adminCsrfIsValid($token), 'The issued CSRF token must validate.');
check(!adminCsrfIsValid('invalid-token'), 'An invalid CSRF token must be rejected.');
check(str_contains(adminCsrfInput(), htmlspecialchars($token, ENT_QUOTES, 'UTF-8')), 'The CSRF input must contain the escaped session token.');

adminSetFlash('success', 'Saved safely.');
$flash = adminPullFlash();
check(($flash['type'] ?? null) === 'success', 'Flash type must be preserved.');
check(($flash['message'] ?? null) === 'Saved safely.', 'Flash message must be preserved.');
check(adminPullFlash() === null, 'Flash messages must only be consumed once.');

$readmePath = dirname(__DIR__) . '/README.md';
check(is_file($readmePath), 'README fixture must exist.');
check(!adminRemoveStoredUpload('README.md', dirname(__DIR__) . '/uploads'), 'Upload cleanup must reject files outside the allowed root.');
check(is_file($readmePath), 'Rejected upload cleanup must not delete an outside file.');

$root = dirname(__DIR__);
$adminFiles = glob($root . '/admin_*.php') ?: [];
$adminFiles[] = $root . '/products.php';
$adminFiles[] = $root . '/categories.php';

foreach ($adminFiles as $file) {
    $contents = file_get_contents($file);
    check($contents !== false, 'Unable to read ' . basename($file) . '.');
    if ($contents === false) {
        continue;
    }
    check(!str_contains($contents, '<style>'), basename($file) . ' still contains a legacy inline style block.');
    check(!preg_match('/\sstyle\s*=/', $contents), basename($file) . ' still contains a legacy inline style attribute.');
    check(!str_contains($contents, "' . \$stmt->error"), basename($file) . ' exposes a database statement error.');
}

$sidebar = file_get_contents($root . '/includes/sidebar.php');
$topbar = file_get_contents($root . '/includes/admin_topbar.php');
check(!str_contains((string)$sidebar, 'href="admin_logout.php"'), 'Sidebar logout must not use GET.');
check(!str_contains((string)$topbar, 'href="admin_logout.php"'), 'Topbar logout must not use GET.');
$logout = file_get_contents($root . '/admin_logout.php');
check(str_contains((string)$logout, "REQUEST_METHOD'] !== 'POST'"), 'Logout must reject non-POST requests.');
check(str_contains((string)$logout, 'adminCsrfIsValid'), 'Logout must verify its CSRF token.');
$auth = file_get_contents($root . '/admin_auth.php');
check(str_contains((string)$auth, 'session_regenerate_id(true)'), 'Administrator login must rotate the session identifier.');

foreach (['products.php', 'categories.php', 'admin_orders.php', 'admin_order_details.php', 'admin_account.php', 'admin_settings.php'] as $writeRoute) {
    $contents = file_get_contents($root . '/' . $writeRoute);
    check(str_contains((string)$contents, 'adminRequireValidCsrf('), $writeRoute . ' must reject missing or invalid CSRF tokens.');
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Admin security checks passed.\n";
