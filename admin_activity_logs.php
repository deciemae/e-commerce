<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

$conn = getConnection();
$logs = [];

$res = $conn->query("SELECT * FROM admin_activity_logs ORDER BY created_at DESC LIMIT 100");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php $activePage = 'activity-logs'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'Activity Logs'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content">
        <div class="log-card">
            <?php if (empty($logs)): ?>
                <p class="text-muted text-center py-4">No activity logs found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Admin Email</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-nowrap text-muted"><?= htmlspecialchars($log['created_at']) ?></td>
                                    <td><?= htmlspecialchars($log['admin_email']) ?></td>
                                    <td><strong><?= htmlspecialchars($log['action']) ?></strong></td>
                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($log['ip_address']) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-footer mt-4">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
