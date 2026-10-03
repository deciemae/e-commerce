<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
header('Content-Type: application/json');

// ── Helpers ───────────────────────────────────────────────────
function jsonOut(bool $ok, string $msg): void {
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

// ── Route ─────────────────────────────────────────────────────
$action = trim($_POST['action'] ?? '');
$conn   = getConnection();
ensureCustomerTables($conn);

switch ($action) {

    // ── ADD ──────────────────────────────────────────────────
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity   = max(1, (int)($_POST['quantity'] ?? 1));
        $color      = trim($_POST['color'] ?? '');

        if ($product_id <= 0) {
            jsonOut(false, 'Invalid product.');
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
            $stmt = $conn->prepare('UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?');
            $stmt->bind_param('ii', $new_qty, $existing['cart_item_id']);
            $stmt->execute();
            $stmt->close();
            jsonOut(true, 'Cart updated — quantity increased.');
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO cart_items (cart_id, product_id, quantity, color) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('iiis', $cart_id, $product_id, $quantity, $color_check);
            $stmt->execute();
            $stmt->close();
            jsonOut(true, 'Product added to cart!');
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

        $cart_id = getOrCreateCart($conn);
        $stmt = $conn->prepare('SELECT cart_item_id FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
        $stmt->bind_param('ii', $cart_item_id, $cart_id);
        $stmt->execute();
        $ownedItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ownedItem) {
            jsonOut(false, 'Cart item not found.');
        }

        $stmt = $conn->prepare(
            'UPDATE cart_items SET quantity = ?, color = ? WHERE cart_item_id = ?'
        );
        $stmt->bind_param('isi', $quantity, $color_val, $cart_item_id);
        $stmt->execute();
        $stmt->close();

        jsonOut(true, 'Cart updated.');
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

        jsonOut(true, 'Item removed from cart.');
        break;

    // ── CLEAR ────────────────────────────────────────────────
    case 'clear':
        $cart_id = getOrCreateCart($conn);
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->bind_param('i', $cart_id);
        $stmt->execute();
        $stmt->close();
        jsonOut(true, 'Cart cleared.');
        break;

    default:
        jsonOut(false, 'Unknown action.');
}

$conn->close();
