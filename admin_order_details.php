<?php
session_start();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();
require_once 'includes/customer_system.php';

$conn = getConnection();
ensureCustomerTables($conn);

$error = $_SESSION['admin_order_detail_flash_error'] ?? '';
$success = $_SESSION['admin_order_detail_flash_success'] ?? '';
unset($_SESSION['admin_order_detail_flash_error'], $_SESSION['admin_order_detail_flash_success']);

$orderId = (int)($_GET['order_id'] ?? ($_POST['order_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = trim($_POST['status'] ?? '');
    if ($orderId <= 0 || !in_array($status, orderStatusOptions(), true)) {
        $_SESSION['admin_order_detail_flash_error'] = 'Invalid order status update.';
    } else {
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $status, $orderId);
        if ($stmt->execute()) {
            $_SESSION['admin_order_detail_flash_success'] = 'Order status updated successfully.';
        } else {
            $_SESSION['admin_order_detail_flash_error'] = 'Failed to update order status.';
        }
        $stmt->close();
    }

    header('Location: admin_order_details.php?order_id=' . $orderId);
    exit;
}

$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
            u.first_name, u.last_name, u.email, u.phone_number
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     WHERE o.order_id = ?
     LIMIT 1'
);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    $conn->close();
    http_response_code(404);
    exit('Order not found.');
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<style>
        .customer-card {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;

        width: 100%;
        background: #ffffff !important;

        border: none !important;
        border-radius: 24px !important;

        box-shadow: 0 8px 25px rgba(20, 35, 60, 0.06) !important;

        overflow: hidden !important;
        margin-bottom: 24px !important;
    }

    /* Card header */
    .customer-card .card-header {
        display: block !important;
        visibility: visible !important;

        background: #ffffff !important;
        background-image: none !important;

        color: #101b2d !important;

        border: none !important;
        border-bottom: 1px solid #e3e7ed !important;

        padding: 20px 20px 12px !important;

        font-size: 1.2rem !important;
        font-weight: 700 !important;
    }

    /* Card body */
    .customer-card .card-body {
        display: block !important;
        visibility: visible !important;

        background: #ffffff !important;
        color: #152238 !important;

        padding: 24px 28px 28px !important;
    }

    /* Text inside card */
    .customer-card .card-body strong {
        color: #101b2d !important;
        font-weight: 700;
    }

    .customer-card .card-body > div {
        color: #152238;
        font-size: 1.05rem;
        line-height: 1.5;
    }

    /* Status badge */
    .customer-card .order-status-badge {
        display: inline-block;
    }
</style>
<body class="admin-ui">

<?php $activePage = 'admin-orders'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php
    $pageTitle = 'Order Details';
    $topbarActions = '<a href="admin_orders.php" class="btn btn-outline-primary btn-sm">Back to Orders</a>';
    require_once 'includes/admin_topbar.php';
    ?>

    <div class="page-content">
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="customer-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Order #<?= (int)$order['order_id'] ?></span>
                        <form method="post" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="min-width:160px">
                                <?php foreach (orderStatusOptions() as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Save Status</button>
                        </form>
                    </div>
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
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
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
                    <div class="card-header">Customer & Delivery</div>
                    <div class="card-body">
                        <div class="mb-2"><strong>Customer:</strong> <?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                        <div class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
                        <div class="mb-2"><strong>Phone:</strong> <?= htmlspecialchars($order['phone_number'] ?? 'N/A') ?></div>
                        <div class="mb-2"><strong>Status:</strong> <span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></div>
                        <div class="mb-2"><strong>Placed:</strong> <?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?></div>
                        <div><strong>Shipping Address:</strong><br><?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?></div>
                    </div>
                </div>

                <div class="customer-card">
                    <div class="card-header">Order Total</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2"><span>Order ID</span><strong>#<?= (int)$order['order_id'] ?></strong></div>
                        <div class="d-flex justify-content-between"><span>Total Amount</span><strong>₱<?= number_format((float)$order['total_amount'], 2) ?></strong></div>
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
