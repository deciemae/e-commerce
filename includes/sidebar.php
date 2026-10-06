<?php
// Usage: include this file and set $activePage before including.
// e.g. $activePage = 'categories';
$activePage = $activePage ?? 'dashboard';
$adminLoggedIn = function_exists('adminIsLoggedIn') && adminIsLoggedIn();
?>
<div id="sidebar">
    <a class="sidebar-brand" href="admin_dashboard.php" aria-label="Bloom and Basket administrator dashboard">
        <span class="sidebar-brand-logo" aria-hidden="true">
            <img src="assets/logo.png" alt="" width="140" height="40">
        </span>
        <span class="sidebar-brand-copy">
            <strong>Bloom &amp; Basket</strong>
            <small>Administrator</small>
        </span>
    </a>

    <nav aria-label="Administrator navigation">
        <div class="nav-section-label">ADMIN MANAGEMENT</div>
        <div class="nav-item">
            <a href="admin_dashboard.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" aria-label="Dashboard" title="Dashboard" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zM10.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zM2.5 10a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zM10.5 10a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/></svg>
                <span class="nav-label">Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="categories.php" class="<?= $activePage === 'categories' ? 'active' : '' ?>" aria-label="Categories" title="Categories" <?= $activePage === 'categories' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0a1.5 1.5 0 0 1 1.5-1.5h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a1.5 1.5 0 0 1-1.5-1.5z"/></svg>
                <span class="nav-label">Categories</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="products.php" class="<?= $activePage === 'products' ? 'active' : '' ?>" aria-label="Products" title="Products" <?= $activePage === 'products' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2zm3.564 1.428L5.596 5 8 5.961 14.154 3.5zM15 4.239l-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.761V6.838L1 4.239v6.523zM7.443.184a1.5 1.5 0 0 1 1.114 0l7.129 2.852A.5.5 0 0 1 16 3.5v8.662a1.5 1.5 0 0 1-.901 1.387l-6.5 2.6a1.5 1.5 0 0 1-1.198 0l-6.5-2.6A1.5 1.5 0 0 1 0 12.162V3.5a.5.5 0 0 1 .314-.464z"/></svg>
                <span class="nav-label">Products</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_orders.php" class="<?= $activePage === 'admin-orders' ? 'active' : '' ?>" aria-label="Orders" title="Orders" <?= $activePage === 'admin-orders' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 2.5A1.5 1.5 0 0 1 3.5 1h9A1.5 1.5 0 0 1 14 2.5v11A1.5 1.5 0 0 1 12.5 15h-9A1.5 1.5 0 0 1 2 13.5zM3.5 2a.5.5 0 0 0-.5.5V13a.5.5 0 0 0 .5.5h9a.5.5 0 0 0 .5-.5V2.5a.5.5 0 0 0-.5-.5z"/><path d="M4.5 4.5h7v1h-7zm0 2.5h7v1h-7zm0 2.5h4v1h-4z"/></svg>
                <span class="nav-label">Orders</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="customers.php" class="<?= $activePage === 'customers' ? 'active' : '' ?>" aria-label="Customers" title="Customers" <?= $activePage === 'customers' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg>
                <span class="nav-label">Customers</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_activity_logs.php" class="<?= $activePage === 'activity-logs' ? 'active' : '' ?>" aria-label="Activity Logs" title="Activity Logs" <?= $activePage === 'activity-logs' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/></svg>
                <span class="nav-label">Activity Logs</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="admin_settings.php" class="<?= $activePage === 'settings' ? 'active' : '' ?>" aria-label="Settings" title="Settings" <?= $activePage === 'settings' ? 'aria-current="page"' : '' ?>>
                <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg>
                <span class="nav-label">Settings</span>
            </a>
        </div>

        <hr class="sidebar-divider">

        <div class="nav-section-label">ACCOUNT</div>
        <?php if ($adminLoggedIn): ?>
            <div class="nav-item">
                <form method="post" action="admin_logout.php" class="admin-sidebar-logout-form">
                    <?= adminCsrfInput() ?>
                    <button type="submit" class="admin-sidebar-logout-button" aria-label="Logout" title="Logout">
                    <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M6 3a1 1 0 0 1 1-1h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H7a1 1 0 0 1-1-1v-1h2V4H6zm-1 6h4.586l-1.293-1.293 1.414-1.414L15.414 8l-4.707 4.707-1.414-1.414L9.586 10H5z"/></svg>
                    <span class="nav-label">Logout</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </nav>

</div>

<button id="sidebarBackdrop" class="sidebar-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>
<button id="sidebarToggle" class="sidebar-toggle-btn" type="button" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="sidebar">
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>
<script>
(function () {
    var body = document.body;
    var btn = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    var mobileQuery = window.matchMedia('(max-width: 991.98px)');
    var collapsed = false;

    try {
        collapsed = localStorage.getItem('sidebarCollapsed') === '1';
    } catch (error) {
        collapsed = false;
    }

    function applyState(isCollapsed) {
        if (mobileQuery.matches) {
            body.classList.remove('sidebar-collapsed');
            btn.setAttribute('aria-expanded', String(body.classList.contains('admin-sidebar-open')));
            btn.setAttribute('aria-label', body.classList.contains('admin-sidebar-open') ? 'Close navigation' : 'Open navigation');
            return;
        }

        body.classList.remove('admin-sidebar-open');
        body.classList.toggle('sidebar-collapsed', isCollapsed);
        btn.setAttribute('aria-expanded', String(!isCollapsed));
        btn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    }

    function closeMobileNavigation() {
        body.classList.remove('admin-sidebar-open');
        applyState(collapsed);
    }

    applyState(collapsed);

    btn.addEventListener('click', function () {
        if (mobileQuery.matches) {
            body.classList.toggle('admin-sidebar-open');
            applyState(collapsed);
            if (body.classList.contains('admin-sidebar-open')) {
                var firstLink = document.querySelector('#sidebar nav a, #sidebar nav button');
                if (firstLink) firstLink.focus();
            }
            return;
        }

        collapsed = !collapsed;
        try {
            localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
        } catch (error) {
            // The sidebar still works when browser storage is unavailable.
        }
        applyState(collapsed);
    });

    backdrop.addEventListener('click', closeMobileNavigation);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('admin-sidebar-open')) {
            closeMobileNavigation();
            btn.focus();
            return;
        }

        if (event.key === 'Tab' && body.classList.contains('admin-sidebar-open')) {
            var focusable = Array.prototype.slice.call(document.querySelectorAll('#sidebar a[href], #sidebar button:not([disabled])'));
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
    mobileQuery.addEventListener('change', function () {
        applyState(collapsed);
    });
})();
</script>
