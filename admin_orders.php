<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();
require_once 'includes/customer_system.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireValidCsrf('admin_orders.php');
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($orderId <= 0 || !in_array($status, orderStatusOptions(), true)) {
        adminSetFlash('error', 'Invalid order status update.');
    } else {
        $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $status, $orderId);
        if ($stmt->execute()) {
            logAdminActivity('Update Order Status', "Updated order ID: {$orderId} to status: {$status}");
            adminSetFlash('success', 'Order status updated successfully.');
        } else {
            adminSetFlash('error', 'Failed to update order status.');
        }
        $stmt->close();
    }

    adminRedirect('admin_orders.php');
}

$filter = trim($_GET['status'] ?? '');
$params = [];
$types = '';
$sql = 'SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
               u.first_name, u.last_name, u.email,
               COUNT(od.order_detail_id) AS item_count
        FROM orders o
        INNER JOIN users u ON u.user_id = o.user_id
        LEFT JOIN order_details od ON od.order_id = o.order_id';

if ($filter !== '' && in_array($filter, orderStatusOptions(), true)) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $filter;
    $types .= 's';
}

$sql .= ' GROUP BY o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address, u.first_name, u.last_name, u.email
          ORDER BY o.order_date DESC';

$stmt = $conn->prepare($sql);
$validFilter = $filter !== '' && in_array($filter, orderStatusOptions(), true);
if ($validFilter) {
    $stmt->bind_param('s', $filter);
}
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
    <title>Admin Orders — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php $activePage = 'admin-orders'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php
    $pageTitle = 'Order Management';
    $topbarActions = '<a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>';
    require_once 'includes/admin_topbar.php';
    ?>

    <div class="page-content">
        <div class="customer-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Orders</span>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <span class="text-muted small">Filter by status</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="admin_orders.php" class="btn btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>">All</a>
                        <?php foreach (orderStatusOptions() as $status): ?>
                            <a href="admin_orders.php?status=<?= urlencode($status) ?>" class="btn btn-outline-secondary <?= $filter === $status ? 'active' : '' ?>"><?= htmlspecialchars($status) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="empty-panel m-3">No orders found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <caption class="visually-hidden">Customer orders and fulfillment status</caption>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?= (int)$order['order_id'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($order['email']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($order['order_date']))) ?></td>
                                        <td><span class="order-status-badge <?= htmlspecialchars(orderStatusBadgeClass((string)$order['status'])) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                        <td><?= (int)$order['item_count'] ?></td>
                                        <td>₱<?= number_format((float)$order['total_amount'], 2) ?></td>
                                        <td>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a href="admin_order_details.php?order_id=<?= (int)$order['order_id'] ?>" class="btn btn-outline-primary btn-sm">Details</a>
                                                <form method="post" class="d-flex gap-1 align-items-center">
                                                    <?= adminCsrfInput() ?>
                                                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                                    <select name="status" class="form-select form-select-sm status-select" aria-label="Status for order <?= (int)$order['order_id'] ?>">
                                                        <?php foreach (orderStatusOptions() as $status): ?>
                                                            <option value="<?= htmlspecialchars($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                                </form>
                                            </div>
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
