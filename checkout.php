<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

class CheckoutValidationException extends RuntimeException
{
}

function checkoutItemIds(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $ids = array_map('intval', explode(',', $value));
    $ids = array_filter($ids, static fn(int $id): bool => $id > 0);

    return array_values(array_unique($ids));
}

function fetchCheckoutItems(mysqli $conn, int $cartId, array $selectedItemIds, bool $lockForUpdate = false): array
{
    $sql =
        'SELECT ci.cart_item_id, ci.product_id, ci.quantity, ci.color,
                p.product_name, p.price, p.stock_quantity, p.image_url,
                c.category_name
         FROM cart_items ci
         INNER JOIN products p ON ci.product_id = p.product_id
         INNER JOIN categories c ON p.category_id = c.category_id
         WHERE ci.cart_id = ?';

    $params = [$cartId];
    $types = 'i';

    if (!empty($selectedItemIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedItemIds), '?'));
        $sql .= " AND ci.cart_item_id IN ($placeholders)";
        $params = array_merge($params, $selectedItemIds);
        $types .= str_repeat('i', count($selectedItemIds));
    }

    $sql .= ' ORDER BY ci.added_at ASC';
    if ($lockForUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $items;
}

$conn = getConnection();

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$customerId = (int)$customer['user_id'];
$cartId = getOrCreateCart($conn);
$selectedItems = checkoutItemIds($_GET['items'] ?? '');
$checkoutQuery = !empty($selectedItems) ? '?items=' . implode(',', $selectedItems) : '';
$cartItems = fetchCheckoutItems($conn, $cartId, $selectedItems);
$addresses = customerAddresses($conn, $customerId);
$paymentMethods = [
    'Credit/Debit Card',
    'E-Wallet',
    'Cash on Delivery (COD)',
    'Online Banking',
];
$csrfToken = customerCsrfToken();
$error = '';
$selectedAddressId = (int)($_POST['address_id'] ?? 0);
$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
$shippingFee = 50.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your checkout session expired. Please refresh the page and try again.';
    } elseif (empty($cartItems)) {
        $error = 'Your cart is empty.';
    } elseif ($selectedAddressId <= 0) {
        $error = 'Please select a delivery address.';
    } elseif (!in_array($paymentMethod, $paymentMethods, true)) {
        $error = 'Please select a valid payment method.';
    } else {
        $conn->begin_transaction();

        try {
            $lockedItems = fetchCheckoutItems($conn, $cartId, $selectedItems, true);
            if (empty($lockedItems) || (!empty($selectedItems) && count($lockedItems) !== count($selectedItems))) {
                throw new CheckoutValidationException('One or more selected products are no longer in your cart.');
            }

            $stmt = $conn->prepare(
                'SELECT address_id, user_id, label, recipient_name, phone_number, address_line1,
                        address_line2, city, state, postal_code, country, is_default, created_at
                 FROM customer_addresses
                 WHERE address_id = ? AND user_id = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->bind_param('ii', $selectedAddressId, $customerId);
            $stmt->execute();
            $selectedAddress = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$selectedAddress) {
                throw new CheckoutValidationException('The selected delivery address is not available.');
            }

            $orderSubtotal = 0.0;
            foreach ($lockedItems as $item) {
                $quantity = (int)$item['quantity'];
                $availableStock = (int)$item['stock_quantity'];

                if ($quantity < 1 || $quantity > $availableStock) {
                    throw new CheckoutValidationException(
                        'One of the selected products is no longer available in the requested quantity.'
                    );
                }

                $orderSubtotal += (float)$item['price'] * $quantity;
            }

            $orderTotal = $orderSubtotal + $shippingFee;
            $orderAddress = addressSnapshot($selectedAddress);
            $status = 'Pending';
            $paymentStatus = 'Pending';

            $stmt = $conn->prepare(
                'INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method, payment_status, shipping_fee)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('idssssd', $customerId, $orderTotal, $status, $orderAddress, $paymentMethod, $paymentStatus, $shippingFee);
            $stmt->execute();
            $orderId = (int)$conn->insert_id;
            $stmt->close();

            foreach ($lockedItems as $item) {
                $productId = (int)$item['product_id'];
                $quantity = (int)$item['quantity'];
                $unitPrice = (float)$item['price'];
                $subtotal = $unitPrice * $quantity;

                $stmt = $conn->prepare(
                    'INSERT INTO order_details (order_id, product_id, quantity, unit_price, subtotal)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('iiidd', $orderId, $productId, $quantity, $unitPrice, $subtotal);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    'UPDATE products
                     SET stock_quantity = stock_quantity - ?
                     WHERE product_id = ? AND stock_quantity >= ?'
                );
                $stmt->bind_param('iii', $quantity, $productId, $quantity);
                $stmt->execute();

                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new CheckoutValidationException(
                        'One of the selected products is no longer available in the requested quantity.'
                    );
                }
                $stmt->close();

                $cartItemId = (int)$item['cart_item_id'];
                $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
                $stmt->bind_param('ii', $cartItemId, $cartId);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    $stmt->close();
                    throw new CheckoutValidationException('A selected cart item changed during checkout.');
                }
                $stmt->close();
            }

            $conn->commit();
            $conn->close();
            header('Location: payment_confirmation.php?order_id=' . $orderId);
            exit;
        } catch (CheckoutValidationException $exception) {
            $conn->rollback();
            $error = $exception->getMessage();
        } catch (Throwable $throwable) {
            $conn->rollback();
            error_log('Checkout failure: ' . $throwable->getMessage());
            $error = 'We could not place your order. Please review your cart and try again.';
        }
    }
}

$grandTotal = 0.0;
foreach ($cartItems as $item) {
    $grandTotal += ((float)$item['price']) * ((int)$item['quantity']);
}
$grandTotalWithShipping = $grandTotal + $shippingFee;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page checkout-page">

<?php $customerActivePage = 'checkout'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content">
    <header class="page-topbar checkout-page-heading d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <p class="checkout-page-eyebrow">Secure checkout</p>
            <h1>Checkout</h1>
            <p class="checkout-page-intro">Review your order, choose a delivery address, and select how you plan to pay.</p>
        </div>
        <div class="checkout-heading-actions d-flex gap-2 flex-wrap">
            <a href="cart.php" class="checkout-secondary-link">&larr; Back to Cart</a>
            <a href="customer_addresses.php" class="checkout-secondary-link">Manage Addresses</a>
        </div>
    </header>

    <div class="page-content">
        <?php if ($error): ?><div class="alert alert-danger checkout-alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-panel checkout-empty">
                <h2>Your cart is empty</h2>
                <p>Add products to your cart before proceeding to checkout.</p>
                <a href="shop.php" class="checkout-primary-action">Shop Products</a>
            </div>
        <?php else: ?>
            <form method="post" action="checkout.php<?= htmlspecialchars($checkoutQuery, ENT_QUOTES) ?>" class="row g-4 align-items-start checkout-layout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <div class="col-lg-8 checkout-main">
                    <section class="customer-card mb-4 checkout-section" aria-labelledby="delivery-title">
                        <h2 class="card-header" id="delivery-title">Delivery Address</h2>
                        <div class="card-body p-3">
                            <?php if (empty($addresses)): ?>
                                <div class="empty-panel p-3 mb-0">
                                    No delivery addresses saved.
                                    <div class="mt-3"><a href="customer_addresses.php" class="checkout-primary-action checkout-primary-action-sm">Add Address</a></div>
                                </div>
                            <?php else: ?>
                                <label for="checkout-address" class="form-label">Deliver to</label>
                                <select id="checkout-address" name="address_id" class="form-select checkout-select" required>
                                    <option value="">Select an address</option>
                                    <?php foreach ($addresses as $address): ?>
                                        <option value="<?= (int)$address['address_id'] ?>" <?= ($selectedAddressId > 0 ? $selectedAddressId === (int)$address['address_id'] : !empty($address['is_default'])) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($address['label'] . ' - ' . addressSnapshot($address)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-2">The selected address will be stored with the order.</div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="customer-card mb-4 checkout-section" aria-labelledby="order-items-title">
                        <h2 class="card-header" id="order-items-title">Order Items</h2>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle checkout-items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item):
                                        $subtotal = (float)$item['price'] * (int)$item['quantity'];
                                    ?>
                                        <tr>
                                            <td data-label="Product">
                                                <div class="checkout-product-row">
                                                    <?php if (!empty($item['image_url'])): ?>
                                                        <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>"
                                                             alt="<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>"
                                                             class="checkout-product-image">
                                                    <?php else: ?>
                                                        <span class="checkout-product-image checkout-product-placeholder" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="checkout-product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <div class="checkout-product-meta"><?= htmlspecialchars($item['category_name']) ?><?php if (!empty($item['color'])): ?> &middot; <?= htmlspecialchars($item['color']) ?><?php endif; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Quantity"><?= (int)$item['quantity'] ?></td>
                                            <td data-label="Unit price">&#8369;<?= number_format((float)$item['price'], 2) ?></td>
                                            <td data-label="Subtotal">&#8369;<?= number_format($subtotal, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    
                    <fieldset class="customer-card mb-4 checkout-section checkout-payment" aria-describedby="payment-note">
                        <legend class="card-header">Payment Method</legend>
                        <div class="card-body checkout-payment-options">
                            <?php foreach ($paymentMethods as $index => $method):
                                $methodId = 'payment-method-' . $index;
                            ?>
                                <label class="checkout-payment-option" for="<?= $methodId ?>">
                                    <input class="form-check-input"
                                           type="radio"
                                           name="payment_method"
                                           id="<?= $methodId ?>"
                                           value="<?= htmlspecialchars($method, ENT_QUOTES) ?>"
                                           <?= $paymentMethod === $method ? 'checked' : '' ?>
                                           required>
                                    <span>
                                        <strong><?= htmlspecialchars($method) ?></strong>
                                        <?php if ($method === 'Cash on Delivery (COD)'): ?>
                                            <small>Pay when your order is delivered.</small>
                                        <?php else: ?>
                                            <small>Your payment will remain pending until it is verified.</small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>

                            <p class="checkout-payment-note" id="payment-note">
                                Card, wallet, and bank credentials are not collected on this page.
                            </p>
                        </div>
                    </fieldset>
                </div>

                <div class="col-lg-4 checkout-summary-column">
                    <aside class="customer-card checkout-order-summary" aria-labelledby="checkout-summary-title">
                        <h2 class="card-header" id="checkout-summary-title">Order Summary</h2>
                        <div class="card-body">
                            <div class="checkout-summary-line"><span>Products</span><strong><?= count($cartItems) ?></strong></div>
                            <div class="checkout-summary-line"><span>Total quantity</span><strong><?= array_sum(array_column($cartItems, 'quantity')) ?></strong></div>
                            <div class="checkout-summary-line"><span>Subtotal</span><strong>&#8369;<?= number_format($grandTotal, 2) ?></strong></div>
                            <div class="checkout-summary-line"><span>Shipping fee</span><strong>&#8369;<?= number_format($shippingFee, 2) ?></strong></div>
                            <div class="checkout-summary-total"><span>Total Amount</span><strong>&#8369;<?= number_format($grandTotalWithShipping, 2) ?></strong></div>
                            <button type="submit" class="checkout-place-order" <?= empty($addresses) ? 'disabled' : '' ?>>Place Order</button>
                            <p class="checkout-submit-note">Your order and payment remain pending until they are processed.</p>
                        </div>
                    </aside>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
