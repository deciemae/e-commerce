<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
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

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM customer_addresses WHERE user_id = ?');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$addressCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM orders WHERE user_id = ?');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$orderCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM orders WHERE user_id = ? AND status IN ("Pending", "Confirmed", "Processing", "Shipped")');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$activeOrderCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT COALESCE(SUM(total_amount), 0) AS total_spent FROM orders WHERE user_id = ? AND status <> "Cancelled"');
$stmt->bind_param('i', $customerId);
$stmt->execute();
$totalSpent = (float)($stmt->get_result()->fetch_assoc()['total_spent'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
            COUNT(od.order_detail_id) AS item_count
     FROM orders o
     LEFT JOIN order_details od ON od.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address
     ORDER BY o.order_date DESC
     LIMIT 5'
);
$stmt->bind_param('i', $customerId);
$stmt->execute();
$recentOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$addresses = customerAddresses($conn, $customerId);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Overview &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page customer-account-surface">

<?php $customerActivePage = 'dashboard'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content" class="customer-account-page">
    <div class="account-shell">
        <header class="account-page-header">
            <div>
                <p class="account-eyebrow">Customer account</p>
                <h1>Welcome, <?= htmlspecialchars($customer['first_name']) ?></h1>
                <p>Review your account details, delivery addresses, and recent orders.</p>
            </div>
            <a href="shop.php" class="account-primary-link">Continue shopping</a>
        </header>

        <?php $customerAccountPage = 'dashboard'; require 'includes/customer_account_nav.php'; ?>

        <div class="page-content account-content">

        <div class="row g-3 mb-4 account-stats-grid">
            <div class="col-md-3">
                <div class="customer-stat account-stat h-100">
                    <div class="label">Saved Addresses</div>
                    <div class="value"><?= $addressCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat account-stat h-100">
                    <div class="label">Orders Placed</div>
                    <div class="value"><?= $orderCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat account-stat h-100">
                    <div class="label">Open Orders</div>
                    <div class="value"><?= $activeOrderCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat account-stat h-100">
                    <div class="label">Total Spent</div>
                    <div class="value">&#8369;<?= number_format($totalSpent, 2) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="customer-card account-card h-100" aria-labelledby="recent-orders-title">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="recent-orders-title">Recent orders</h2>
                        <a href="customer_orders.php" class="account-text-link">View all</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-panel m-3">No orders yet. Place your first order from the cart checkout.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td>#<?= (int)$order['order_id'] ?><div class="text-muted small"><?= (int)$order['item_count'] ?> item(s)</div></td>
                                                <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?></td>
                                                <td><span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                                <td>&#8369;<?= number_format((float)$order['total_amount'], 2) ?></td>
                                                <td><a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="account-row-action">Track</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="customer-card account-card mb-4" aria-labelledby="account-summary-title">
                    <div class="card-header"><h2 id="account-summary-title">Account summary</h2></div>
                    <div class="card-body account-detail-list">
                        <div><span>Name</span><strong><?= htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></strong></div>
                        <div><span>Email</span><strong><?= htmlspecialchars($customer['email']) ?></strong></div>
                        <div><span>Phone</span><strong><?= htmlspecialchars($customer['phone_number'] ?: 'Not added') ?></strong></div>
                    </div>
                </section>

                <section class="customer-card account-card" aria-labelledby="saved-addresses-title">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="saved-addresses-title">Saved addresses</h2>
                        <a href="customer_addresses.php" class="account-text-link">Manage</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($addresses)): ?>
                            <div class="empty-panel">Add a delivery address before checkout.</div>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <?php foreach (array_slice($addresses, 0, 3) as $address): ?>
                                    <div class="address-card <?= !empty($address['is_default']) ? 'default' : '' ?> p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($address['label']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars(addressSnapshot($address)) ?></div>
                                            </div>
                                            <?php if (!empty($address['is_default'])): ?>
                                                <span class="status-pill status-confirmed">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
        </div>
    </div>

</main>

<?php require __DIR__ . '/includes/customer_footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
