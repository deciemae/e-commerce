<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
header('Content-Type: application/json');

// ── Helpers ───────────────────────────────────────────────────
function getCartTotalQuantity(mysqli $conn, int $cartId): int {
    $stmt = $conn->prepare('SELECT SUM(quantity) as total_qty FROM cart_items WHERE cart_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($res['total_qty'] ?? 0);
}

function jsonOut(bool $ok, string $msg, array $extra = []): void {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

function cartColorIsValid(mysqli $conn, int $productId, string $color): bool
{
    $stmt = $conn->prepare('SELECT color FROM product_colors WHERE product_id = ? ORDER BY product_color_id ASC');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $colors = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'color');
    $stmt->close();

    if (empty($colors)) {
        return $color === '';
    }

    return in_array($color, $colors, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    jsonOut(false, 'Method not allowed.');
}

if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    jsonOut(false, 'Your cart session expired. Please refresh the page and try again.');
}

// ── Route ─────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? '');
$conn   = getConnection();

switch ($action) {

    // ── ADD ──────────────────────────────────────────────────
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantityInput = trim((string)($_POST['quantity'] ?? ''));
        $quantity   = ctype_digit($quantityInput) ? (int)$quantityInput : 0;
        $color      = trim($_POST['color'] ?? '');

        if ($product_id <= 0) {
            jsonOut(false, 'Invalid product.');
        }
        if ($quantity < 1) {
            jsonOut(false, 'Quantity must be at least 1.');
        }
        if (mb_strlen($color) > 50) {
            jsonOut(false, 'Invalid product color.');
        }

        // Verify product exists and has stock
        $stmt = $conn->prepare('SELECT product_id, stock_quantity FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            jsonOut(false, 'Product not found.');
        }
        if ($prod['stock_quantity'] <= 0) {
            jsonOut(false, 'Product is out of stock.');
        }
        if ($quantity > (int)$prod['stock_quantity']) {
            jsonOut(false, 'Requested quantity exceeds available stock.');
        }
        if (!cartColorIsValid($conn, $product_id, $color)) {
            jsonOut(false, 'Please select a valid product color.');
        }

        $cart_id = getOrCreateCart($conn);

        // Check for duplicate: same product + same color (treat NULL and '' as equivalent)
        $color_check = $color !== '' ? $color : null;
        $stmt = $conn->prepare(
            'SELECT cart_item_id, quantity FROM cart_items
             WHERE cart_id = ? AND product_id = ? AND COALESCE(color, \'\') = COALESCE(?, \'\')'
        );
        $stmt->bind_param('iis', $cart_id, $product_id, $color_check);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;
            if ($new_qty > (int)$prod['stock_quantity']) {
                jsonOut(false, 'Requested quantity exceeds available stock.');
            }
            $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?');
            $stmt->bind_param('ii', $new_qty, $existing['cart_item_id']);
            $stmt->execute();
            $stmt->close();
            $totalQty = getCartTotalQuantity($conn, $cart_id);
            jsonOut(true, 'Cart updated — quantity increased.', ['total_cart_qty' => $totalQty]);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO cart_items (cart_id, product_id, quantity, color) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('iiis', $cart_id, $product_id, $quantity, $color_check);
            $stmt->execute();
            $stmt->close();
            $totalQty = getCartTotalQuantity($conn, $cart_id);
            jsonOut(true, 'Product added to cart!', ['total_cart_qty' => $totalQty]);
        }
        break;

    // ── UPDATE ───────────────────────────────────────────────
    case 'update':
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
        $quantity     = (int)($_POST['quantity'] ?? 1);
        $color        = trim($_POST['color'] ?? '');
        $color_val    = $color !== '' ? $color : null;

        if ($cart_item_id <= 0) {
            jsonOut(false, 'Invalid cart item.');
        }
        if ($quantity < 1) {
            jsonOut(false, 'Quantity must be at least 1.');
        }
        if (mb_strlen($color) > 50) {
            jsonOut(false, 'Invalid product color.');
        }

        $cart_id = getOrCreateCart($conn);
        $stmt = $conn->prepare(
            'SELECT ci.cart_item_id, ci.product_id, p.stock_quantity
             FROM cart_items ci
             INNER JOIN products p ON p.product_id = ci.product_id
             WHERE ci.cart_item_id = ? AND ci.cart_id = ?'
        );
        $stmt->bind_param('ii', $cart_item_id, $cart_id);
        $stmt->execute();
        $ownedItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ownedItem) {
            jsonOut(false, 'Cart item not found.');
        }
        if ($quantity > (int)$ownedItem['stock_quantity']) {
            jsonOut(false, 'Requested quantity exceeds available stock.');
        }
        if (!cartColorIsValid($conn, (int)$ownedItem['product_id'], $color)) {
            jsonOut(false, 'Please select a valid product color.');
        }

        $stmt = $conn->prepare(
            'UPDATE cart_items SET quantity = ?, color = ? WHERE cart_item_id = ?'
        );
        $stmt->bind_param('isi', $quantity, $color_val, $cart_item_id);
        $stmt->execute();
        $stmt->close();

        $totalQty = getCartTotalQuantity($conn, $cart_id);
        jsonOut(true, 'Cart updated.', ['total_cart_qty' => $totalQty]);
        break;

    // ── REMOVE ───────────────────────────────────────────────
    case 'remove':
        $cart_item_id = (int)($_POST['cart_item_id'] ?? 0);

        if ($cart_item_id <= 0) {
            jsonOut(false, 'Invalid cart item.');
        }

        $cart_id = getOrCreateCart($conn);
        $stmt = $conn->prepare('SELECT cart_item_id FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
        $stmt->bind_param('ii', $cart_item_id, $cart_id);
        $stmt->execute();
        $ownedItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ownedItem) {
            jsonOut(false, 'Cart item not found.');
        }

        $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
        $stmt->bind_param('ii', $cart_item_id, $cart_id);
        $stmt->execute();
        $stmt->close();

        $totalQty = getCartTotalQuantity($conn, $cart_id);
        jsonOut(true, 'Item removed from cart.', ['total_cart_qty' => $totalQty]);
        break;

    // ── CLEAR ────────────────────────────────────────────────
    case 'clear':
        $cart_id = getOrCreateCart($conn);
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->bind_param('i', $cart_id);
        $stmt->execute();
        $stmt->close();
        jsonOut(true, 'Cart cleared.', ['total_cart_qty' => 0]);
        break;

    default:
        jsonOut(false, 'Unknown action.');
}

$conn->close();
