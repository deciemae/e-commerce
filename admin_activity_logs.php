<?php
session_start();
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
    <style>
        :root {
            --ink: #2a2621;
            --muted: #8a8175;
            --line: rgba(42, 38, 33, 0.09);
            --paper: #fbf9f6;
            --rose: #c2477a;
        }
        #main-content { color: var(--ink); }
        .page-topbar h1 { color: var(--ink); margin-bottom: 4px; }
        .page-subtitle { color: var(--muted); font-size: 0.92rem; margin: 0; }
        .log-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 2px rgba(42, 38, 33, 0.04);
            border: 1px solid var(--line);
        }
    </style>
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
