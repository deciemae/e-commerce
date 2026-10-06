<?php

require_once __DIR__ . '/../config/db.php';

$failures = [];
$warnings = [];

function releaseCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function releaseWarning(bool $condition, string $message): void
{
    global $warnings;
    if (!$condition) {
        $warnings[] = $message;
    }
}

$root = dirname(__DIR__);
$runtimeFiles = array_merge(
    glob($root . '/*.php') ?: [],
    glob($root . '/includes/*.php') ?: []
);

foreach ($runtimeFiles as $file) {
    if (in_array(basename($file), ['setup_db.php', 'setup_security.php'], true)) {
        continue;
    }

    $contents = file_get_contents($file);
    releaseCheck($contents !== false, 'Unable to inspect ' . basename($file) . '.');
    if ($contents === false) {
        continue;
    }

    releaseCheck(
        !preg_match('/\b(?:CREATE|ALTER|DROP)\s+TABLE\b/i', $contents),
        basename($file) . ' performs schema DDL during an application request.'
    );

    if (basename($file) !== 'session.php') {
        releaseCheck(
            !preg_match('/\bsession_start\s*\(/', $contents),
            basename($file) . ' bypasses the shared session bootstrap.'
        );
    }
}

foreach (['setup_db.php', 'setup_security.php'] as $setupFile) {
    $contents = file_get_contents($root . '/' . $setupFile);
    releaseCheck(str_contains((string)$contents, 'requireCommandLineSetupApproval'), $setupFile . ' is not protected by the CLI setup guard.');
}

$cartActions = file_get_contents($root . '/cart_actions.php');
releaseCheck(str_contains((string)$cartActions, "REQUEST_METHOD'] !== 'POST'"), 'Cart actions must be POST-only.');
releaseCheck(str_contains((string)$cartActions, 'customerCsrfIsValid'), 'Cart actions must validate CSRF tokens.');
releaseCheck(str_contains((string)$cartActions, 'stock_quantity'), 'Cart actions must validate inventory.');

$customerLogout = file_get_contents($root . '/customer_logout.php');
releaseCheck(str_contains((string)$customerLogout, "REQUEST_METHOD'] !== 'POST'"), 'Customer logout must be POST-only.');
releaseCheck(str_contains((string)$customerLogout, 'customerCsrfIsValid'), 'Customer logout must validate CSRF tokens.');

$customerNavigation = file_get_contents($root . '/includes/customer_nav.php');
releaseCheck(str_contains((string)$customerNavigation, '<form method="post" action="customer_logout.php"'), 'Customer navigation must submit logout with POST.');
releaseCheck(!str_contains((string)$customerNavigation, 'href="customer_logout.php"'), 'Customer navigation must not expose a GET logout link.');

foreach (['index.php', 'shop.php', 'browse.php', 'product_details.php', 'cart.php'] as $cartClient) {
    $contents = file_get_contents($root . '/' . $cartClient);
    releaseCheck(str_contains((string)$contents, ".append('csrf_token'"), $cartClient . ' must include the CSRF token in cart requests.');
}

$productDetails = file_get_contents($root . '/product_details.php');
releaseCheck(str_contains((string)$productDetails, 'customerCsrfIsValid'), 'Product reviews must validate CSRF tokens.');
releaseCheck(!str_contains((string)$productDetails, "' . \$stmt->error"), 'Product reviews expose a raw database error.');

$dbConfig = file_get_contents($root . '/config/db.php');
releaseCheck(str_contains((string)$dbConfig, 'SHOPPINGCART_DB_HOST'), 'Database configuration must support environment variables.');
releaseCheck(!str_contains((string)$dbConfig, 'htmlspecialchars($conn->connect_error)'), 'Database connection errors must not be rendered to users.');

$sessionBootstrap = file_get_contents($root . '/includes/session.php');
releaseCheck(str_contains((string)$sessionBootstrap, "'httponly' => true"), 'Session cookies must be HttpOnly.');
releaseCheck(str_contains((string)$sessionBootstrap, "'samesite' => 'Lax'"), 'Session cookies must use SameSite=Lax.');
releaseCheck(str_contains((string)$sessionBootstrap, 'session.use_strict_mode'), 'Strict session mode must be enabled.');

$connection = getConnection();
$databaseName = (string)($connection->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'] ?? '');
releaseCheck(strcasecmp($databaseName, DB_NAME) === 0, 'Connected database does not match DB_NAME.');

$requiredTables = [
    'admins', 'admin_activity_logs', 'admin_security_settings', 'users', 'categories',
    'products', 'product_colors', 'product_reviews', 'carts', 'cart_items',
    'customer_addresses', 'orders', 'order_details',
];
$stmt = $connection->prepare(
    'SELECT table_name FROM information_schema.tables WHERE table_schema = ?'
);
$stmt->bind_param('s', $databaseName);
$stmt->execute();
$actualTables = array_map('strtolower', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME'));
$stmt->close();

foreach ($requiredTables as $requiredTable) {
    releaseCheck(in_array(strtolower($requiredTable), $actualTables, true), 'Required table is missing: ' . $requiredTable . '.');
}

$integrityQueries = [
    'Cart items reference existing carts and products.' =>
        'SELECT COUNT(*) AS total FROM cart_items ci LEFT JOIN carts c ON c.cart_id = ci.cart_id LEFT JOIN products p ON p.product_id = ci.product_id WHERE c.cart_id IS NULL OR p.product_id IS NULL',
    'Orders reference existing customers.' =>
        'SELECT COUNT(*) AS total FROM orders o LEFT JOIN users u ON u.user_id = o.user_id WHERE u.user_id IS NULL',
    'Order details reference existing orders and products.' =>
        'SELECT COUNT(*) AS total FROM order_details od LEFT JOIN orders o ON o.order_id = od.order_id LEFT JOIN products p ON p.product_id = od.product_id WHERE o.order_id IS NULL OR p.product_id IS NULL',
    'Product stock is non-negative.' =>
        'SELECT COUNT(*) AS total FROM products WHERE stock_quantity < 0',
    'Order statuses belong to the server allowlist.' =>
        "SELECT COUNT(*) AS total FROM orders WHERE status NOT IN ('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled')",
];

foreach ($integrityQueries as $message => $sql) {
    $count = (int)($connection->query($sql)->fetch_assoc()['total'] ?? -1);
    releaseCheck($count === 0, $message . ' Violations: ' . $count . '.');
}

$mismatchCount = (int)($connection->query(
    'SELECT COUNT(*) AS total
     FROM orders o
     LEFT JOIN (SELECT order_id, SUM(subtotal) AS subtotal FROM order_details GROUP BY order_id) d ON d.order_id = o.order_id
     WHERE ABS(o.total_amount - (COALESCE(d.subtotal, 0) + COALESCE(o.shipping_fee, 0))) > 0.01'
)->fetch_assoc()['total'] ?? -1);
releaseWarning($mismatchCount === 0, 'Orders with totals that cannot be reconciled to detail rows: ' . $mismatchCount . '.');

$connection->close();

foreach ($warnings as $warning) {
    fwrite(STDOUT, "WARNING: {$warning}\n");
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Release-readiness checks passed with " . count($warnings) . " warning(s).\n";
