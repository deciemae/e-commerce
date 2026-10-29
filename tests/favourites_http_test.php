<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_favourites.php';

$baseUrl = rtrim(getenv('SHOPPINGCART_BASE_URL') ?: 'http://127.0.0.1:8012', '/');
$sessionPath = getenv('SHOPPINGCART_SESSION_PATH') ?: (__DIR__ . '/runtime_sessions');

if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    fwrite(STDERR, "FAIL: Unable to create the test session directory.\n");
    exit(1);
}

$conn = getConnection();
$userId = (int)($conn->query('SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1')->fetch_assoc()['user_id'] ?? 0);
$productId = (int)($conn->query('SELECT product_id FROM products ORDER BY product_id ASC LIMIT 1')->fetch_assoc()['product_id'] ?? 0);

if ($userId <= 0 || $productId <= 0) {
    fwrite(STDERR, "FAIL: Customer and product fixtures are required.\n");
    $conn->close();
    exit(1);
}

$wasFavourite = customerFavouriteExists($conn, $userId, $productId);
$sessionId = 'favourite-http-' . bin2hex(random_bytes(8));
$csrfToken = bin2hex(random_bytes(32));

session_save_path($sessionPath);
session_id($sessionId);
session_start();
$_SESSION['customer_user_id'] = $userId;
$_SESSION['customer_name'] = 'Favourites Test';
$_SESSION['customer_email'] = 'favourites-test@example.invalid';
$_SESSION['customer_csrf_token'] = $csrfToken;
session_write_close();

function favouriteHttpRequest(string $baseUrl, string $sessionId, array $fields): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: PHPSESSID={$sessionId}\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);
    $body = @file_get_contents($baseUrl . '/favourite_actions.php', false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $matches);

    return [
        'status' => isset($matches[1]) ? (int)$matches[1] : 0,
        'json' => json_decode((string)$body, true),
    ];
}

$failures = [];
$addResponse = favouriteHttpRequest($baseUrl, $sessionId, [
    'action' => 'add',
    'product_id' => $productId,
    'csrf_token' => $csrfToken,
]);
if ($addResponse['status'] !== 200 || !($addResponse['json']['is_favourite'] ?? false)) {
    $failures[] = 'Authenticated add-to-favourites request failed.';
}
if (!customerFavouriteExists($conn, $userId, $productId)) {
    $failures[] = 'The favourite was not persisted for the authenticated customer.';
}

$removeResponse = favouriteHttpRequest($baseUrl, $sessionId, [
    'action' => 'remove',
    'product_id' => $productId,
    'csrf_token' => $csrfToken,
]);
if ($removeResponse['status'] !== 200 || ($removeResponse['json']['is_favourite'] ?? true)) {
    $failures[] = 'Authenticated remove-from-favourites request failed.';
}
if (customerFavouriteExists($conn, $userId, $productId)) {
    $failures[] = 'The removed favourite is still persisted.';
}

if ($wasFavourite) {
    $stmt = $conn->prepare('INSERT IGNORE INTO customer_favourites (user_id, product_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
$sessionFile = rtrim($sessionPath, '\\/') . DIRECTORY_SEPARATOR . 'sess_' . $sessionId;
if (is_file($sessionFile)) {
    unlink($sessionFile);
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Authenticated favourites HTTP checks passed.\n";
