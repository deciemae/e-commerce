<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

$conn = getConnection();
$customer = fetchCurrentCustomer($conn);
$customerId = (int)$customer['user_id'];
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: customer_orders.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT order_id, order_date, total_amount, status, payment_method, payment_status
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
    <title>Payment Confirmation — Bloom & Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <style>
        .confirmation-card {
            max-width: 600px;
            margin: 40px auto;
            text-align: center;
            padding: 40px 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
        }
        .confirmation-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            margin: 30px 0;
        }
        .confirmation-details .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 1px dashed #dee2e6;
            padding-bottom: 10px;
        }
        .confirmation-details .detail-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
    </style>
</head>
<body class="customer-ui customer-page bg-light">

<?php $customerActivePage = 'orders'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="container">
        <div class="confirmation-card">
            <div class="success-icon">
                ✓
            </div>
            <h1 class="h3 fw-bold text-dark mb-2">Order Confirmed!</h1>
            <p class="text-muted">Thank you for your purchase. Your order has been successfully placed.</p>
            
            <div class="confirmation-details">
                <div class="detail-row">
                    <span class="text-muted">Order Number</span>
                    <strong class="text-dark">#<?= (int)$order['order_id'] ?></strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Payment Method</span>
                    <strong class="text-dark"><?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Payment Status</span>
                    <strong class="text-dark">
                        <?php if (($order['payment_status'] ?? '') === 'Paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Total Amount</span>
                    <strong class="text-dark fs-5">₱<?= number_format((float)$order['total_amount'], 2) ?></strong>
                </div>
            </div>

            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="customer_order_tracking.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-primary px-4 py-2">Track Order</a>
                <a href="shop.php" class="btn btn-outline-secondary px-4 py-2">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>

<div class="page-footer text-center py-4 text-muted" style="margin-top:auto;">
    &copy; <?= date('Y') ?> Bloom & Basket Management System
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
