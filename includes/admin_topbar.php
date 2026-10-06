<?php
// Shared admin utility bar plus a content-level page heading.
// Usage: set $pageTitle (and optionally $topbarActions, a raw HTML string) before requiring this file.
// Uses its own DB connection so it never clobbers a $conn already in scope on the including page.

require_once __DIR__ . '/session.php';
startApplicationSession();

require_once __DIR__ . '/admin_toast.php';
$adminFlash = adminPullFlash();

$adminPhotoPath = null;
$adminEmail = $_SESSION['admin_email'] ?? null;
if ($adminEmail) {
    $topbarConn = getConnection();
    $adminStmt = $topbarConn->prepare('SELECT photo_path FROM admins WHERE email = ? LIMIT 1');
    if ($adminStmt) {
        $adminStmt->bind_param('s', $adminEmail);
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminPhotoPath = $adminRow['photo_path'] ?? null;
        $adminStmt->close();
    }
    $topbarConn->close();
}

$pageSubtitleMap = [
    'Dashboard' => 'Store performance and operational priorities',
    'Products' => 'Catalog, pricing, inventory, and product media',
    'Categories' => 'Organize the storefront catalog',
    'Customers' => 'Registered customer activity and order value',
    'Order Management' => 'Review and update fulfillment status',
    'Order Details' => 'Items, delivery information, and order totals',
    'Activity Logs' => 'Administrator access and recorded actions',
    'My Account' => 'Profile, account details, and password security',
    'Settings' => 'Administrative access and security policies',
];
$resolvedPageTitle = $pageTitle ?? 'Admin Dashboard';
$pageSubtitle = $pageSubtitle ?? ($pageSubtitleMap[$resolvedPageTitle] ?? 'Bloom & Basket operations');
?>
<header class="page-topbar">
    <div class="page-topbar-actions d-flex align-items-center gap-3">
        <?php if (!empty($topbarActions)): ?>
            <?= $topbarActions ?>
        <?php endif; ?>

        <?php if ($adminEmail): ?>
            <span class="page-topbar-identity"><?= htmlspecialchars($adminEmail) ?></span>
        <?php endif; ?>

        <div class="dropdown profile-menu">
            <button class="profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                <?php if ($adminPhotoPath): ?>
                    <img src="<?= htmlspecialchars($adminPhotoPath) ?>" alt="Profile photo">
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="admin_account.php">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                        My Account
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="admin_logout.php" class="admin-logout-form">
                        <?= adminCsrfInput() ?>
                        <button class="dropdown-item text-danger" type="submit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
<?php renderAdminToast($adminFlash); ?>

<section class="admin-page-intro" aria-labelledby="adminPageTitle">
    <h1 id="adminPageTitle"><?= htmlspecialchars($resolvedPageTitle) ?></h1>
    <?php if (!empty($pageSubtitle)): ?>
        <p><?= htmlspecialchars($pageSubtitle) ?></p>
    <?php endif; ?>
</section>
