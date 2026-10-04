<?php
session_start();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

$conn = getConnection();

// Fetch customers and order counts
$sql = 'SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone_number, u.created_at,
               COUNT(o.order_id) AS total_orders,
               COALESCE(SUM(o.total_amount), 0) AS total_spent
        FROM users u
        LEFT JOIN orders o ON u.user_id = o.user_id
        GROUP BY u.user_id, u.first_name, u.last_name, u.email, u.phone_number, u.created_at
        ORDER BY u.created_at DESC';

$stmt = $conn->prepare($sql);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Customers — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="admin-ui">

<?php $activePage = 'customers'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php
    $pageTitle = 'Customers';
    $topbarActions = '<a href="index.php" class="btn btn-outline-primary btn-sm">Dashboard</a>';
    require_once 'includes/admin_topbar.php';
    ?>

    <div class="page-content">
        <div class="customer-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Customers List</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($customers)): ?>
                    <div class="empty-panel m-3">No customers found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($customer['email']) ?></td>
                                        <td><?= htmlspecialchars($customer['phone_number'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(date('M d, Y', strtotime($customer['created_at']))) ?></td>
                                        <td><?= (int)$customer['total_orders'] ?></td>
                                        <td>₱<?= number_format((float)$customer['total_spent'], 2) ?></td>
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
