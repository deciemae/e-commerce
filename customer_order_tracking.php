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
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    $stmt = $conn->prepare('SELECT order_id FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 1');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $latestOrder = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $orderId = (int)($latestOrder['order_id'] ?? 0);
}

$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
            u.first_name, u.last_name, u.email
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     WHERE o.order_id = ? AND o.user_id = ?
     LIMIT 1'
);
$stmt->bind_param('ii', $orderId, $customerId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $conn->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Not Found — Bloom &amp; Basket</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/design-system.css">
        <link rel="stylesheet" href="assets/customer.css">
    </head>
    <body class="customer-ui customer-page">
        <div class="auth-shell">
            <div class="auth-card text-center" style="max-width:560px; width:100%;">
                <h1 class="h4 fw-bold mb-2">No order to track yet</h1>
                <p class="form-muted mb-3">Place an order first, then this page will show the latest tracking status.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="shop.php" class="btn btn-primary">Shop Products</a>
                    <a href="customer_orders.php" class="btn btn-outline-primary">Order History</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$stmt = $conn->prepare(
    'SELECT od.order_detail_id, od.quantity, od.unit_price, od.subtotal,
            p.product_name, p.image_url
     FROM order_details od
     INNER JOIN products p ON p.product_id = od.product_id
     WHERE od.order_id = ?
     ORDER BY od.order_detail_id ASC'
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$orderItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statusSteps = orderStatusOptions();
$currentIndex = array_search($order['status'], $statusSteps, true);
if ($currentIndex === false) {
    $currentIndex = 0;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Tracking — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'orders'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Order Tracking</h1>
            <div class="form-muted">View the current status of your order and its item list.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="customer_orders.php" class="btn btn-outline-primary btn-sm">Order History</a>
            <a href="customer_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <div class="customer-hero mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="badge bg-light text-dark mb-3">Order #<?= (int)$order['order_id'] ?></div>
                    <h2 class="fw-bold mb-2">Current status: <?= htmlspecialchars($order['status']) ?></h2>
                    <p>Placed on <?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?>. Total: ₱<?= number_format((float)$order['total_amount'], 2) ?></p>
                </div>
                <span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="tracking-card p-4 mb-4" style="overflow: hidden;">
                    <div class="page-section-title mb-1">Status Timeline</div>
                    
                    <?php
                    $totalSteps = count($statusSteps);
                    $progressPercentage = $totalSteps > 1 ? ($currentIndex / ($totalSteps - 1)) * 100 : 0;
                    $isCancelled = (strtolower(trim($order['status'])) === 'cancelled');
                    ?>
                    <div class="timeline-h-container mt-3">
                        <div class="timeline-h">
                            <div class="timeline-h-line"></div>
                            <div class="timeline-h-progress" style="width: <?= $progressPercentage ?>%; <?= $isCancelled ? 'background:#ef4444;' : '' ?>"></div>
                            
                            <?php foreach ($statusSteps as $index => $status): ?>
                                <?php
                                    $stepClass = '';
                                    if ($isCancelled) {
                                        if ($index === $currentIndex) {
                                            $stepClass = 'cancelled active';
                                        } elseif ($index < $currentIndex) {
                                            $stepClass = 'done';
                                        }
                                    } else {
                                        $stepClass = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'active' : '');
                                    }
                                ?>
                                <div class="timeline-step-h <?= $stepClass ?>">
                                    <div class="timeline-circle">
                                        <?php if ($stepClass === 'done'): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m20.285 2-11.285 11.567-5.286-5.011-3.714 3.716 9 8.728 15-15.285z"/></svg>
                                        <?php elseif (str_contains($stepClass, 'cancelled')): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-label"><?= htmlspecialchars($status) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="customer-card">
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
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($item['product_name']) ?></div>
                                        </td>
                                        <td><?= (int)$item['quantity'] ?></td>
                                        <td>₱<?= number_format((float)$item['unit_price'], 2) ?></td>
                                        <td>₱<?= number_format((float)$item['subtotal'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="customer-card mb-4">
                    <div class="card-header">Delivery Details</div>
                    <div class="card-body" style="padding: 1.25rem; margin-top:-15px;">
                        <div class="mb-2"><strong>Customer:</strong> <?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                        <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
                        <div><strong>Address:</strong><br><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?></div>
                    </div>
                </div>

                <div class="customer-card">
                    <div class="card-header">Order Summary</div>
                    <div class="card-body" style="padding: 1.25rem; margin-top:-15px;">
                        <div class="d-flex justify-content-between mb-2"><span>Order ID</span><strong>#<?= (int)$order['order_id'] ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Status</span><strong><?= htmlspecialchars($order['status']) ?></strong></div>
                        <div class="d-flex justify-content-between mb-2"><span>Total</span><strong>₱<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                        <div class="d-flex justify-content-between"><span>Date</span><strong><?= htmlspecialchars(date('M d, Y', strtotime($order['order_date']))) ?></strong></div>
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
