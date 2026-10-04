<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

$conn = getConnection();
ensureCustomerTables($conn);

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$customerId = (int)$customer['user_id'];
$cartId = getOrCreateCart($conn);

$selectedItems = isset($_GET['items']) ? explode(',', $_GET['items']) : [];
$selectedItems = array_map('intval', $selectedItems);
$selectedItems = array_filter($selectedItems, fn($id) => $id > 0);

if (empty($selectedItems)) {
    $stmt = $conn->prepare(
        'SELECT ci.cart_item_id, ci.product_id, ci.quantity, ci.color,
                p.product_name, p.price, p.stock_quantity, p.image_url,
                c.category_name
         FROM cart_items ci
         INNER JOIN products p ON ci.product_id = p.product_id
         INNER JOIN categories c ON p.category_id = c.category_id
         WHERE ci.cart_id = ?
         ORDER BY ci.added_at ASC'
    );
    $stmt->bind_param('i', $cartId);
} else {
    $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
    $types = 'i' . str_repeat('i', count($selectedItems));
    $params = array_merge([$cartId], $selectedItems);
    
    $stmt = $conn->prepare(
        "SELECT ci.cart_item_id, ci.product_id, ci.quantity, ci.color,
                p.product_name, p.price, p.stock_quantity, p.image_url,
                c.category_name
         FROM cart_items ci
         INNER JOIN products p ON ci.product_id = p.product_id
         INNER JOIN categories c ON p.category_id = c.category_id
         WHERE ci.cart_id = ? AND ci.cart_item_id IN ($placeholders)
         ORDER BY ci.added_at ASC"
    );
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$addresses = customerAddresses($conn, $customerId);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedAddressId = (int)($_POST['address_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $shippingFee = 50.00; // Flat shipping fee

    if (empty($cartItems)) {
        $error = 'Your cart is empty.';
    } elseif ($selectedAddressId <= 0) {
        $error = 'Please select a delivery address.';
    } elseif (empty($paymentMethod) || !in_array($paymentMethod, ['Credit/Debit Card', 'E-Wallet', 'Cash on Delivery (COD)', 'Online Banking'])) {
        $error = 'Please select a valid payment method.';
    } else {
        $selectedAddress = null;
        foreach ($addresses as $address) {
            if ((int)$address['address_id'] === $selectedAddressId) {
                $selectedAddress = $address;
                break;
            }
        }

        if (!$selectedAddress) {
            $error = 'The selected address was not found.';
        } else {
            $orderTotal = 0.0;
            foreach ($cartItems as $item) {
                $orderTotal += ((float)$item['price']) * ((int)$item['quantity']);
            }

            $orderAddress = addressSnapshot($selectedAddress);

            $conn->begin_transaction();
            try {
                $orderTotalWithShipping = $orderTotal + $shippingFee;
                $stmt = $conn->prepare(
                    'INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method, payment_status, shipping_fee)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $status = 'Pending';
                $paymentStatus = ($paymentMethod === 'Cash on Delivery (COD)') ? 'Pending' : 'Paid';
                $stmt->bind_param('idssssd', $customerId, $orderTotalWithShipping, $status, $orderAddress, $paymentMethod, $paymentStatus, $shippingFee);
                $stmt->execute();
                $orderId = (int)$conn->insert_id;
                $stmt->close();

                foreach ($cartItems as $item) {
                    $quantity = (int)$item['quantity'];
                    $unitPrice = (float)$item['price'];
                    $subtotal = $unitPrice * $quantity;

                    $stmt = $conn->prepare(
                        'INSERT INTO order_details (order_id, product_id, quantity, unit_price, subtotal)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param('iiidd', $orderId, $item['product_id'], $quantity, $unitPrice, $subtotal);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?');
                    $stmt->bind_param('iii', $quantity, $item['product_id'], $quantity);
                    $stmt->execute();

                    if ($stmt->affected_rows < 1) {
                        throw new Exception('One of the items is no longer available in the requested quantity.');
                    }
                    $stmt->close();
                }

                foreach ($cartItems as $item) {
                    $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?');
                    $stmt->bind_param('ii', $item['cart_item_id'], $cartId);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                $conn->close();
                header('Location: payment_confirmation.php?order_id=' . $orderId);
                exit;
            } catch (Throwable $throwable) {
                $conn->rollback();
                $error = $throwable->getMessage();
            }
        }
    }
}

$grandTotal = 0.0;
foreach ($cartItems as $item) {
    $grandTotal += ((float)$item['price']) * ((int)$item['quantity']);
}
$shippingFee = 50.00;
$grandTotalWithShipping = $grandTotal + $shippingFee;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'checkout'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Checkout</h1>
            <div class="form-muted">Review the cart, choose a delivery address, and place your order.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="cart.php" class="btn btn-outline-primary btn-sm">Back to Cart</a>
            <a href="customer_addresses.php" class="btn btn-outline-primary btn-sm">Manage Addresses</a>
        </div>
    </div>

    <div class="page-content">
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <?php if (empty($cartItems)): ?>
            <div class="empty-panel">
                <h3 class="h5 fw-bold mb-2">Your cart is empty</h3>
                <p class="mb-3">Add products to your cart before proceeding to checkout.</p>
                <a href="shop.php" class="btn btn-primary">Shop Products</a>
            </div>
        <?php else: ?>
            <form method="post" action="checkout.php?items=<?= htmlspecialchars($_GET['items'] ?? '') ?>" class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="customer-card mb-4">
                        <div class="card-header">Delivery Address</div>
                        <div class="card-body p-3">
                            <?php if (empty($addresses)): ?>
                                <div class="empty-panel p-3 mb-0">
                                    No delivery addresses saved.
                                    <div class="mt-2"><a href="customer_addresses.php" class="btn btn-primary btn-sm">Add Address</a></div>
                                </div>
                            <?php else: ?>
                                <select name="address_id" class="form-select" required>
                                    <option value="">Select an address</option>
                                    <?php foreach ($addresses as $address): ?>
                                        <option value="<?= (int)$address['address_id'] ?>" <?= !empty($address['is_default']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($address['label'] . ' - ' . addressSnapshot($address)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-2">The selected address will be stored with the order.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="customer-card mb-4">
                        <div class="card-header">Order Items</div>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
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
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($item['category_name']) ?><?php if (!empty($item['color'])): ?> · Color: <?= htmlspecialchars($item['color']) ?><?php endif; ?></div>
                                            </td>
                                            <td><?= (int)$item['quantity'] ?></td>
                                            <td>₱<?= number_format((float)$item['price'], 2) ?></td>
                                            <td>₱<?= number_format($subtotal, 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="customer-card mb-4">
                        <div class="card-header">Payment Method</div>
                        <div class="card-body" style="padding: 1.25rem;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_card" value="Credit/Debit Card" required onchange="togglePaymentFields()">
                                <label class="form-check-label" for="pay_card">Credit/Debit Card</label>
                            </div>
                            <div id="fields_card" class="payment-fields d-none p-3 mb-3 border rounded bg-light">
                                <div class="mb-2"><label class="form-label small">Card Number</label><input type="text" class="form-control form-control-sm" placeholder="XXXX XXXX XXXX XXXX" oninput="this.value = this.value.replace(/[^0-9\s]/g, '')"></div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label small">Expiry Date</label><input type="text" class="form-control form-control-sm" placeholder="MM/YY" oninput="this.value = this.value.replace(/[^0-9\/]/g, '')"></div>
                                    <div class="col-6"><label class="form-label small">CVV</label><input type="text" class="form-control form-control-sm" placeholder="123" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                                </div>
                                <!-- <div class="form-text mt-2 text-danger small">Use dummy/test information only.</div> -->
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_ewallet" value="E-Wallet" required onchange="togglePaymentFields()">
                                <label class="form-check-label" for="pay_ewallet">E-Wallet (GCash, Maya)</label>
                            </div>
                            <div id="fields_ewallet" class="payment-fields d-none p-3 mb-3 border rounded bg-light">
                                <div class="mb-2"><label class="form-label small">E-Wallet Mobile Number</label><input type="text" class="form-control form-control-sm" placeholder="09XX XXX XXXX" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                                <!-- <div class="form-text mt-2 text-danger small">Use dummy/test information only.</div> -->
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_online" value="Online Banking" required onchange="togglePaymentFields()">
                                <label class="form-check-label" for="pay_online">Online Banking</label>
                            </div>
                            <div id="fields_online" class="payment-fields d-none p-3 mb-3 border rounded bg-light">
                                <div class="mb-2"><label class="form-label small">Select Bank</label>
                                    <select class="form-select form-select-sm">
                                        <option>BDO Unibank</option>
                                        <option>Bank of the Philippine Islands (BPI)</option>
                                        <option>Metrobank</option>
                                        <option>UnionBank</option>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label small">Account Number</label><input type="text" class="form-control form-control-sm" placeholder="XXXXXXXXXX" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                                <!-- <div class="form-text mt-2 text-danger small">Use dummy/test information only.</div> -->
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="Cash on Delivery (COD)" required onchange="togglePaymentFields()">
                                <label class="form-check-label" for="pay_cod">Cash on Delivery (COD)</label>
                            </div>
                            <div id="fields_cod" class="payment-fields d-none p-3 mt-2 border rounded bg-light">
                                <div class="small">Pay with cash upon delivery of your order.</div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    function togglePaymentFields() {
                        document.querySelectorAll('.payment-fields').forEach(el => el.classList.add('d-none'));
                        if (document.getElementById('pay_card').checked) document.getElementById('fields_card').classList.remove('d-none');
                        if (document.getElementById('pay_ewallet').checked) document.getElementById('fields_ewallet').classList.remove('d-none');
                        if (document.getElementById('pay_online').checked) document.getElementById('fields_online').classList.remove('d-none');
                        if (document.getElementById('pay_cod').checked) document.getElementById('fields_cod').classList.remove('d-none');
                    }
                    </script>
                </div>

                <div class="col-lg-4">
                    <div class="customer-card">
                        <div class="card-header">Order Summary</div>
                        <div class="card-body" style="padding: 1.25rem; margin-top:-15px;">
                            <div class="d-flex justify-content-between mb-2"><span>Items</span><strong><?= count($cartItems) ?></strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Total Quantity</span><strong><?= array_sum(array_column($cartItems, 'quantity')) ?></strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>₱<?= number_format($grandTotal, 2) ?></strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Shipping Fee</span><strong>₱<?= number_format($shippingFee, 2) ?></strong></div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3"><span>Total Amount</span><strong class="fs-5">₱<?= number_format($grandTotalWithShipping, 2) ?></strong></div>
                            <button type="submit" class="btn btn-primary w-100" <?= empty($addresses) ? 'disabled' : '' ?>>Place Order</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
