<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

$conn = getConnection();
$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    $conn->close();
    customerLogout();
    header('Location: customer_login.php');
    exit;
}
$customerId = (int)$customer['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: customer_orders.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT order_id, total_amount, status, payment_method, payment_status
     FROM orders
     WHERE order_id = ? AND user_id = ?
     LIMIT 1'
);
$stmt->bind_param('ii', $orderId, $customerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: customer_orders.php');
    exit;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page confirmation-page">

<?php $customerActivePage = 'orders'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content">
    <div class="confirmation-shell">
        <section class="confirmation-card" aria-labelledby="confirmation-title">
            <div class="success-icon" aria-hidden="true">&#10003;</div>
            <p class="confirmation-eyebrow">Order received</p>
            <h1 id="confirmation-title">Your order has been placed</h1>
            <p class="confirmation-intro">We saved your order and will update its status as it is processed.</p>
            
            <div class="confirmation-details">
                <div class="detail-row">
                    <span>Order Number</span>
                    <strong>#<?= (int)$order['order_id'] ?></strong>
                </div>
                <div class="detail-row">
                    <span>Order Status</span>
                    <strong><?= htmlspecialchars($order['status'] ?? 'Pending') ?></strong>
                </div>
                <div class="detail-row">
                    <span>Payment Method</span>
                    <strong><?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></strong>
                </div>
                <div class="detail-row">
                    <span>Payment Status</span>
                    <strong>
                        <?php if (($order['payment_status'] ?? '') === 'Paid'): ?>
                            <span class="confirmation-status is-paid">Paid</span>
                        <?php else: ?>
                            <span class="confirmation-status is-pending">Pending</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="detail-row">
                    <span>Total Amount</span>
                    <strong class="confirmation-total">&#8369;<?= number_format((float)$order['total_amount'], 2) ?></strong>
                </div>
            </div>

            <p class="confirmation-payment-note">
                Payment status is currently <?= htmlspecialchars(strtolower((string)($order['payment_status'] ?? 'pending'))) ?>.
                No card, wallet, or bank credentials were stored by this checkout page.
            </p>

            <div class="confirmation-actions">
                <a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="confirmation-primary-action">Track Order</a>
                <a href="shop.php" class="confirmation-secondary-action">Continue Shopping</a>
            </div>
        </section>
    </div>
</main>

<div class="page-footer">
    &copy; <?= date('Y') ?> Bloom &amp; Basket
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
