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
    <title>Customer Dashboard — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'dashboard'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h1>Customer Dashboard</h1>
            <!-- <div class="form-muted">Manage your profile, addresses, cart, and orders from one place.</div> -->
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="customer_profile.php" class="btn btn-outline-primary btn-sm">Profile</a>
            <a href="customer_addresses.php" class="btn btn-outline-primary btn-sm">Addresses</a>
            <a href="customer_orders.php" class="btn btn-primary btn-sm">Order History</a>
            <a href="customer_logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>

    <div class="page-content">

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="customer-stat h-100">
                    <div class="label">Saved Addresses</div>
                    <div class="value"><?= $addressCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat h-100">
                    <div class="label">Orders Placed</div>
                    <div class="value"><?= $orderCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat h-100">
                    <div class="label">Open Orders</div>
                    <div class="value"><?= $activeOrderCount ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="customer-stat h-100">
                    <div class="label">Total Spent</div>
                    <div class="value">₱<?= number_format($totalSpent, 2) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="customer-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Orders</span>
                        <a href="customer_orders.php" class="btn btn-outline-primary btn-sm">View All</a>
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
                                                <td>₱<?= number_format((float)$order['total_amount'], 2) ?></td>
                                                <td><a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-sm btn-outline-primary">Track</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="customer-card mb-4">
                    <div class="card-header">Account Summary</div>
                    <div class="card-body" style="margin-left: 15px; margin-bottom: 25px;">
                        <div class="mb-2"><strong>Name:</strong> <?= htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></div>
                        <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($customer['email']) ?></div>
                        <div><strong>Phone:</strong> <?= htmlspecialchars($customer['phone_number'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <div class="customer-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Saved Delivery Addresses</span>
                        <a href="customer_addresses.php" class="btn btn-outline-primary btn-sm">Manage</a>
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
                </div>
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
