<?php
session_start();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

$conn = getConnection();

// ---- Key metrics ----
$totalRevenue = (float)($conn->query('SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE status != "Cancelled"')->fetch_assoc()['total'] ?? 0);
$totalOrders = (int)($conn->query('SELECT COUNT(*) AS total FROM orders')->fetch_assoc()['total'] ?? 0);
$lowStockProducts = (int)($conn->query('SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= 10')->fetch_assoc()['total'] ?? 0);
$totalCustomers = (int)($conn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'] ?? 0);
$totalProductsCount = (int)($conn->query('SELECT COUNT(*) AS total FROM products')->fetch_assoc()['total'] ?? 0);
$totalCategoriesCount = (int)($conn->query('SELECT COUNT(*) AS total FROM categories')->fetch_assoc()['total'] ?? 0);

// ---- Sales trend: last 6 months of revenue, grouped by month ----
$salesTrendRaw = $conn->query(
    "SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym,
            DATE_FORMAT(order_date, '%b %Y') AS label,
            SUM(total_amount) AS revenue,
            COUNT(*) AS orders_count
     FROM orders
     WHERE status != 'Cancelled'
       AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym, label
     ORDER BY ym ASC"
)->fetch_all(MYSQLI_ASSOC);

$trendLabels = array_map(fn($r) => $r['label'], $salesTrendRaw);
$trendRevenue = array_map(fn($r) => (float)$r['revenue'], $salesTrendRaw);
$trendOrders = array_map(fn($r) => (int)$r['orders_count'], $salesTrendRaw);

// ---- Order status distribution ----
$statusRaw = $conn->query(
    'SELECT status, COUNT(*) AS total FROM orders GROUP BY status ORDER BY total DESC'
)->fetch_all(MYSQLI_ASSOC);
$statusLabels = array_map(fn($r) => $r['status'], $statusRaw);
$statusCounts = array_map(fn($r) => (int)$r['total'], $statusRaw);

// ---- Top-selling products (top 8, by units sold) ----
$topProductsRaw = $conn->query(
    'SELECT p.product_name, SUM(od.quantity) AS total_sold, p.stock_quantity
     FROM order_details od
     INNER JOIN products p ON p.product_id = od.product_id
     INNER JOIN orders o ON o.order_id = od.order_id
     WHERE o.status != "Cancelled"
     GROUP BY p.product_id
     ORDER BY total_sold DESC
     LIMIT 8'
)->fetch_all(MYSQLI_ASSOC);

$topProductLabels = array_map(fn($r) => $r['product_name'], $topProductsRaw);
$topProductUnits = array_map(fn($r) => (int)$r['total_sold'], $topProductsRaw);

// ---- Low-stock products detail ----
$lowStockList = $conn->query(
    'SELECT product_name, stock_quantity
     FROM products
     WHERE stock_quantity <= 10
     ORDER BY stock_quantity ASC
     LIMIT 10'
)->fetch_all(MYSQLI_ASSOC);

// ---- Recent activity (recent orders) ----
$recentOrders = $conn->query(
    'SELECT o.order_id, o.order_date, o.total_amount, o.status, u.first_name, u.last_name
     FROM orders o
     INNER JOIN users u ON u.user_id = o.user_id
     ORDER BY o.order_date DESC
     LIMIT 10'
)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --ink: #2a2621;
            --muted: #8a8175;
            --line: rgba(42, 38, 33, 0.09);
            --paper: #fbf9f6;
            --rose: #c2477a;
            --rose-soft: rgba(194, 71, 122, 0.12);
            --plum: #6e4b78;
            --plum-soft: rgba(110, 75, 120, 0.12);
            --sage: #5f8863;
            --sage-soft: rgba(95, 136, 99, 0.13);
            --amber: #c47f2c;
            --amber-soft: rgba(196, 127, 44, 0.14);
        }

        #main-content {
            color: var(--ink);
        }

        .page-topbar h1 {
            color: var(--ink);
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 0.92rem;
            margin: 0;
        }

        /* ---- Metric cards ---- */
        .insight-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 2px rgba(42, 38, 33, 0.04);
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent, var(--rose));
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 18px;
            height: 100%;
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }

        .insight-card:hover {
            box-shadow: 0 6px 20px rgba(42, 38, 33, 0.08);
            transform: translateY(-1px);
        }

        .insight-card.accent-sales { --accent: var(--rose); }
        .insight-card.accent-orders { --accent: var(--plum); }
        .insight-card.accent-users { --accent: var(--sage); }
        .insight-card.accent-stock { --accent: var(--amber); }
        .insight-card.accent-products { --accent: #3f7d8c; }
        .insight-card.accent-categories { --accent: #c45c2c; }
        .insight-card.accent-stock.is-alert { --accent: #b8433a; }

        .insight-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--accent, var(--rose)) 14%, white);
            color: var(--accent, var(--rose));
            flex-shrink: 0;
        }

        .insight-icon svg {
            width: 26px;
            height: 26px;
        }

        .insight-body {
            display: flex;
            flex-direction: column;
            text-align: right;
            flex: 1;
            min-width: 0;
            overflow-wrap: break-word;
        }

        .insight-title {
            font-size: 0.8rem;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .insight-value {
            font-variant-numeric: tabular-nums;
            font-size: clamp(1.2rem, 1.5vw + 0.5rem, 1.6rem);
            font-weight: 700;
            color: var(--ink);
            line-height: 1.1;
            word-break: break-word;
        }

        .insight-card.is-alert .insight-value { color: #b8433a; }

        /* ---- Chart / table cards ---- */
        .chart-card, .customer-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px 24px;
            box-shadow: 0 1px 2px rgba(42, 38, 33, 0.04);
            border: 1px solid var(--line);
            height: 100%;
        }

        .chart-card-header, .customer-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding: 0 0 14px;
            border-bottom: 1px solid var(--line);
            background: transparent;
        }

        .chart-card-header h2 {
            font-weight: 600;
            font-size: 1.08rem;
            color: var(--ink);
            margin: 0;
        }

        .customer-card .card-header span {
            font-weight: 600;
            font-size: 1.08rem;
            color: var(--ink);
        }

        .chart-wrap { position: relative; height: 280px; }
        .chart-wrap.small { height: 240px; }

        .empty-panel {
            color: var(--muted);
            text-align: center;
            padding: 40px 0;
            font-size: 0.92rem;
        }

        /* ---- Tables ---- */
        .table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            font-weight: 600;
            border-bottom: 1px solid var(--line);
            padding-bottom: 10px;
        }

        .table td {
            border-color: var(--line);
            padding-top: 12px;
            padding-bottom: 12px;
            font-size: 0.92rem;
        }

        .stock-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            background: var(--amber-soft);
            color: var(--amber);
        }

        .stock-pill.critical {
            background: rgba(184, 67, 58, 0.12);
            color: #b8433a;
        }

        .stock-pill::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

    </style>
</head>
<body class="admin-ui">

<?php $activePage = 'dashboard'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'Dashboard'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content">

        <!-- Key metrics -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-sales">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M17 7.5c0-1.93-2.24-3.5-5-3.5S7 5.57 7 7.5 9.24 11 12 11s5 1.57 5 3.5-2.24 3.5-5 3.5-5-1.57-5-3.5"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Total Sales</div>
                        <div class="insight-value">₱<?= number_format($totalRevenue, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-orders">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1.2 11.2a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8zM9 7a3 3 0 0 1 6 0"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Total Orders</div>
                        <div class="insight-value"><?= number_format($totalOrders) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-users">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM9.5 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 19c0-2.8 2.5-5 5.5-5S13 16.2 13 19M12 14.5c3.6 0 8 1.9 8 4.5"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Registered Users</div>
                        <div class="insight-value"><?= number_format($totalCustomers) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-categories">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect stroke-linecap="round" stroke-linejoin="round" x="3" y="3" width="7" height="7"/><rect stroke-linecap="round" stroke-linejoin="round" x="14" y="3" width="7" height="7"/><rect stroke-linecap="round" stroke-linejoin="round" x="14" y="14" width="7" height="7"/><rect stroke-linecap="round" stroke-linejoin="round" x="3" y="14" width="7" height="7"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Total Categories</div>
                        <div class="insight-value"><?= number_format($totalCategoriesCount) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-products">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="3.27 6.96 12 12.01 20.73 6.96"/><line stroke-linecap="round" stroke-linejoin="round" x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Total Products</div>
                        <div class="insight-value"><?= number_format($totalProductsCount) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="insight-card accent-stock <?= $lowStockProducts > 0 ? 'is-alert' : '' ?>">
                    <div class="insight-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2 3 6.5V12c0 5 3.8 8.7 9 10 5.2-1.3 9-5 9-10V6.5zM12 8v5m0 4h.01"/></svg>
                    </div>
                    <div class="insight-body">
                        <div class="insight-title">Low-Stock Products</div>
                        <div class="insight-value"><?= number_format($lowStockProducts) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales trend + order status -->
        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="chart-card">
                    <div class="chart-card-header"><h2>Sales Trend</h2><span class="page-subtitle">Last 6 months</span></div>
                    <?php if (empty($trendLabels)): ?>
                        <div class="empty-panel">No sales data available for this period.</div>
                    <?php else: ?>
                        <div class="chart-wrap">
                            <canvas id="salesTrendChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="chart-card-header"><h2>Order Status</h2></div>
                    <?php if (empty($statusLabels)): ?>
                        <div class="empty-panel">No orders yet.</div>
                    <?php else: ?>
                        <div class="chart-wrap small">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent orders + top-selling products -->
        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="customer-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Recent Orders</span>
                        <a href="admin_orders.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <?php if (empty($recentOrders)): ?>
                        <div class="empty-panel">No recent orders.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>#<?= (int)$order['order_id'] ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name'])) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars(date('M d, Y', strtotime($order['order_date']))) ?></td>
                                            <td><span class="order-status-badge status-<?= strtolower($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="chart-card h-100">
                    <div class="chart-card-header">
                        <h2>Top-Selling Products</h2>
                        <a href="products.php" class="btn btn-outline-primary btn-sm">View Catalog</a>
                    </div>
                    <?php if (empty($topProductLabels)): ?>
                        <div class="empty-panel">No sales data available.</div>
                    <?php else: ?>
                        <div class="chart-wrap">
                            <canvas id="topProductsChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Low-stock detail -->
        <?php if (!empty($lowStockList)): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="customer-card">
                    <div class="card-header">
                        <span>Low-Stock Products</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockList as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td>
                                            <span class="stock-pill <?= (int)$item['stock_quantity'] <= 3 ? 'critical' : '' ?>">
                                                <?= (int)$item['stock_quantity'] ?> left
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const trendLabels = <?= json_encode($trendLabels) ?>;
    const trendRevenue = <?= json_encode($trendRevenue) ?>;
    const trendOrders = <?= json_encode($trendOrders) ?>;
    const statusLabels = <?= json_encode($statusLabels) ?>;
    const statusCounts = <?= json_encode($statusCounts) ?>;
    const topProductLabels = <?= json_encode($topProductLabels) ?>;
    const topProductUnits = <?= json_encode($topProductUnits) ?>;

    Chart.defaults.color = '#8a8175';
    Chart.defaults.borderColor = 'rgba(42, 38, 33, 0.08)';

    if (trendLabels.length) {
        new Chart(document.getElementById('salesTrendChart'), {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Revenue (₱)',
                        data: trendRevenue,
                        backgroundColor: 'rgba(194, 71, 122, 0.55)',
                        borderRadius: 6,
                        maxBarThickness: 34,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        label: 'Orders',
                        data: trendOrders,
                        type: 'line',
                        borderColor: '#6e4b78',
                        backgroundColor: '#6e4b78',
                        pointBackgroundColor: '#6e4b78',
                        pointRadius: 4,
                        yAxisID: 'y1',
                        tension: 0.35,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } },
                scales: {
                    y: { position: 'left', beginAtZero: true, grid: { color: 'rgba(42,38,33,0.06)' }, title: { display: true, text: 'Revenue (₱)' } },
                    y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Orders' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    if (statusLabels.length) {
        new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusCounts,
                    backgroundColor: ['#c2477a', '#5f8863', '#c47f2c', '#6e4b78', '#8a8175', '#b8433a', '#3f7d8c'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
            }
        });
    }

    if (topProductLabels.length) {
        new Chart(document.getElementById('topProductsChart'), {
            type: 'bar',
            data: {
                labels: topProductLabels,
                datasets: [{
                    label: 'Units Sold',
                    data: topProductUnits,
                    backgroundColor: 'rgba(95, 136, 99, 0.65)',
                    borderRadius: 6,
                    maxBarThickness: 18
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(42,38,33,0.06)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
</script>
</body>
</html>
