<?php

require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customer_system.php';
require_once __DIR__ . '/includes/customer_favourites.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$customerId = currentCustomerId();
if (!$customerId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'requires_authentication' => true,
        'auth_url' => 'favourites.php',
        'message' => 'Sign in or create an account to save favourites.',
    ]);
    exit;
}

$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$action = (string)($_POST['action'] ?? 'toggle');

if ($productId === false || !in_array($action, ['add', 'remove', 'toggle'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid favourite request.']);
    exit;
}

$conn = getConnection();
$productStmt = $conn->prepare('SELECT product_id FROM products WHERE product_id = ? LIMIT 1');
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$productExists = $productStmt->get_result()->num_rows === 1;
$productStmt->close();

if (!$productExists) {
    $conn->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

$isFavourite = customerFavouriteExists($conn, $customerId, $productId);
$shouldFavourite = $action === 'add' || ($action === 'toggle' && !$isFavourite);

if ($shouldFavourite) {
    $stmt = $conn->prepare('INSERT IGNORE INTO customer_favourites (user_id, product_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $customerId, $productId);
    $success = $stmt->execute();
    $stmt->close();
    $isFavourite = $success && customerFavouriteExists($conn, $customerId, $productId);
} else {
    $stmt = $conn->prepare('DELETE FROM customer_favourites WHERE user_id = ? AND product_id = ?');
    $stmt->bind_param('ii', $customerId, $productId);
    $success = $stmt->execute();
    $stmt->close();
    $isFavourite = false;
}

$conn->close();

if (!$success) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Your favourites could not be updated.']);
    exit;
}

echo json_encode([
    'success' => true,
    'is_favourite' => $isFavourite,
    'message' => $isFavourite ? 'Added to favourites.' : 'Removed from favourites.',
]);
