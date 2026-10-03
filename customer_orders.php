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
    <title>Order History — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'orders'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Order History</h1>
            <div class="form-muted">Review previous orders and open the tracking view for each one.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="customer_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
            <a href="shop.php" class="btn btn-outline-primary btn-sm">Shop</a>
            <a href="customer_logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>

    <div class="page-content">
        <div class="customer-card">
            <div class="card-header">Previous Orders</div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="empty-panel m-3">You have no orders yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
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
                                        <td>
                                            <?php if (!empty($order['first_image_url'])): ?>
                                                <img src="<?= htmlspecialchars($order['first_image_url']) ?>" alt="Product image" style="width: 48px; height: 48px; object-fit: cover; border-radius: 10px; border: 1px solid rgba(148,163,184,.3); background: #f8fafc;">
                                            <?php else: ?>
                                                <div style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 10px; border: 1px solid rgba(148,163,184,.3); background: #f8fafc; color: #94a3b8; font-size: 1.1rem;">🛍️</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>#<?= (int)$order['order_id'] ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?></td>
                                        <td><span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                        <td><?= (int)$order['item_count'] ?></td>
                                        <td>₱<?= number_format((float)$order['total_amount'], 2) ?></td>
                                        <td class="text-end">
                                            <a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-primary btn-sm">Track</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
