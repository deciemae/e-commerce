<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/customer_favourites.php';

$failures = [];

function favouriteDatabaseCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$conn = getConnection();
$userId = (int)($conn->query('SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1')->fetch_assoc()['user_id'] ?? 0);
$productId = (int)($conn->query('SELECT product_id FROM products ORDER BY product_id ASC LIMIT 1')->fetch_assoc()['product_id'] ?? 0);

favouriteDatabaseCheck($userId > 0, 'A customer fixture is required for the favourites database test.');
favouriteDatabaseCheck($productId > 0, 'A product fixture is required for the favourites database test.');

if ($userId > 0 && $productId > 0) {
    $conn->begin_transaction();

    $stmt = $conn->prepare('DELETE FROM customer_favourites WHERE user_id = ? AND product_id = ?');
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO customer_favourites (user_id, product_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $productId);
    favouriteDatabaseCheck($stmt->execute(), 'A customer must be able to save an existing product.');
    $stmt->close();

    favouriteDatabaseCheck(
        in_array($productId, customerFavouriteProductIds($conn, $userId, [$productId]), true),
        'Saved favourites must be readable only through the owning customer scope.'
    );

    $stmt = $conn->prepare('DELETE FROM customer_favourites WHERE user_id = ? AND product_id = ?');
    $stmt->bind_param('ii', $userId, $productId);
    favouriteDatabaseCheck($stmt->execute() && $stmt->affected_rows === 1, 'A customer must be able to remove a saved product.');
    $stmt->close();

    favouriteDatabaseCheck(
        !customerFavouriteExists($conn, $userId, $productId),
        'Removed favourites must no longer be returned.'
    );

    $conn->rollback();
}

$constraintResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM information_schema.referential_constraints
     WHERE constraint_schema = DATABASE()
       AND table_name = 'customer_favourites'
       AND delete_rule = 'CASCADE'"
);
favouriteDatabaseCheck((int)($constraintResult->fetch_assoc()['total'] ?? 0) === 2, 'Favourites must cascade when a customer or product is deleted.');

$conn->close();

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Favourites database checks passed.\n";
