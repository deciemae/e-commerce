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
        <title>No Order to Track &mdash; Bloom &amp; Basket</title>
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
                        <h1>Order tracking</h1>
                        <p>Follow the progress of your current and previous orders.</p>
                    </div>
                </header>
                <?php $customerAccountPage = 'orders'; require 'includes/customer_account_nav.php'; ?>
                <div class="page-content account-content">
                    <section class="account-empty-state">
                        <h2>No order to track yet</h2>
                        <p>Place an order first, then its latest status will appear here.</p>
                        <div class="account-empty-actions">
                            <a href="shop.php" class="account-primary-link">Shop products</a>
                            <a href="customer_orders.php" class="account-secondary-link">Order history</a>
                        </div>
                    </section>
                </div>
            </div>
        </main>
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
    <title>Order Tracking &mdash; Bloom &amp; Basket</title>
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
                <h1>Order tracking</h1>
                <p>Follow the current status and review every item in this order.</p>
            </div>
            <a href="customer_orders.php" class="account-secondary-link">Back to orders</a>
        </header>

        <?php $customerAccountPage = 'orders'; require 'includes/customer_account_nav.php'; ?>

        <div class="page-content account-content">
        <section class="customer-hero account-order-hero mb-4" aria-labelledby="tracking-order-title">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <p class="account-order-number">Order #<?= (int)$order['order_id'] ?></p>
                    <h2 id="tracking-order-title">Current status: <?= htmlspecialchars($order['status']) ?></h2>
                    <p>Placed on <?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?>. Total: &#8369;<?= number_format((float)$order['total_amount'], 2) ?></p>
                </div>
                <span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="tracking-card account-card mb-4" aria-labelledby="status-timeline-title">
                    <div class="card-header"><h2 id="status-timeline-title">Status timeline</h2></div>
                    <div class="card-body">
                    
                    <?php
                    $totalSteps = count($statusSteps);
                    $progressPercentage = $totalSteps > 1 ? ($currentIndex / ($totalSteps - 1)) * 100 : 0;
                    $isCancelled = (strtolower(trim($order['status'])) === 'cancelled');
                    ?>
                    <div class="timeline-h-container mt-3">
                        <div class="timeline-h">
                            <div class="timeline-h-line"></div>
                            <div class="timeline-h-progress <?= $isCancelled ? 'is-cancelled' : '' ?>" style="--timeline-progress: <?= number_format($progressPercentage, 2, '.', '') ?>%;"></div>
                            
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
                </section>

                <section class="customer-card account-card" aria-labelledby="tracking-items-title">
                    <div class="card-header"><h2 id="tracking-items-title">Order items</h2></div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle account-tracking-items">
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
                                        <td data-label="Product">
                                            <div class="account-tracking-product">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>" class="account-order-image">
                                                <?php else: ?>
                                                    <span class="account-order-image account-order-image-placeholder" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td data-label="Quantity"><span class="account-cell-value"><?= (int)$item['quantity'] ?></span></td>
                                        <td data-label="Unit price"><span class="account-cell-value">&#8369;<?= number_format((float)$item['unit_price'], 2) ?></span></td>
                                        <td data-label="Subtotal"><span class="account-cell-value">&#8369;<?= number_format((float)$item['subtotal'], 2) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="customer-card account-card mb-4" aria-labelledby="delivery-details-title">
                    <div class="card-header"><h2 id="delivery-details-title">Delivery details</h2></div>
                    <div class="card-body account-detail-list">
                        <div><span>Customer</span><strong><?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?></strong></div>
                        <div><span>Email</span><strong><?= htmlspecialchars($order['email']) ?></strong></div>
                        <div class="account-detail-block"><span>Address</span><strong><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?></strong></div>
                    </div>
                </section>

                <section class="customer-card account-card" aria-labelledby="tracking-summary-title">
                    <div class="card-header"><h2 id="tracking-summary-title">Order summary</h2></div>
                    <div class="card-body account-detail-list">
                        <div><span>Order ID</span><strong>#<?= (int)$order['order_id'] ?></strong></div>
                        <div><span>Status</span><strong><?= htmlspecialchars($order['status']) ?></strong></div>
                        <div><span>Total</span><strong>&#8369;<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
                        <div><span>Date</span><strong><?= htmlspecialchars(date('M d, Y', strtotime($order['order_date']))) ?></strong></div>
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
