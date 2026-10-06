<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

$conn = getConnection();

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$customerId = (int)$customer['user_id'];

$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
            COUNT(od.order_detail_id) AS item_count,
            (
                SELECT p.image_url
                FROM order_details od2
                INNER JOIN products p ON p.product_id = od2.product_id
                WHERE od2.order_id = o.order_id
                ORDER BY od2.order_detail_id ASC
                LIMIT 1
            ) AS first_image_url
     FROM orders o
     LEFT JOIN order_details od ON od.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address, first_image_url
     ORDER BY o.order_date DESC'
);
$stmt->bind_param('i', $customerId);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page customer-account-surface">

<?php $customerActivePage = 'orders'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content" class="customer-account-page">
    <div class="account-shell">
        <header class="account-page-header">
            <div>
                <p class="account-eyebrow">Customer account</p>
                <h1>Order history</h1>
                <p>Review every order and open its current tracking details.</p>
            </div>
            <a href="shop.php" class="account-primary-link">Shop products</a>
        </header>

        <?php $customerAccountPage = 'orders'; require 'includes/customer_account_nav.php'; ?>

        <div class="page-content account-content">
        <section class="customer-card account-card" aria-labelledby="previous-orders-title">
            <div class="card-header"><h2 id="previous-orders-title">Previous orders</h2></div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="empty-panel m-3">You have no orders yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle account-orders-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td data-label="Product">
                                            <?php if (!empty($order['first_image_url'])): ?>
                                                <img src="<?= htmlspecialchars($order['first_image_url'], ENT_QUOTES) ?>" alt="Product from order #<?= (int)$order['order_id'] ?>" class="account-order-image">
                                            <?php else: ?>
                                                <span class="account-order-image account-order-image-placeholder" aria-hidden="true"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Order"><span class="account-cell-value">#<?= (int)$order['order_id'] ?></span></td>
                                        <td data-label="Date"><span class="account-cell-value"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?></span></td>
                                        <td data-label="Status"><span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                        <td data-label="Items"><span class="account-cell-value"><?= (int)$order['item_count'] ?></span></td>
                                        <td data-label="Total"><span class="account-cell-value">&#8369;<?= number_format((float)$order['total_amount'], 2) ?></span></td>
                                        <td data-label="Action" class="text-end">
                                            <a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="account-row-action">Track order</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
