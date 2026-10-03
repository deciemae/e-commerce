<?php
// Shared admin topbar: page title (left) + optional extra actions + profile menu (right).
// Usage: set $pageTitle (and optionally $topbarActions, a raw HTML string) before requiring this file.
// Uses its own DB connection so it never clobbers a $conn already in scope on the including page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
?>
<div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1><?= htmlspecialchars($pageTitle ?? 'Admin Dashboard') ?></h1>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($topbarActions)): ?>
            <?= $topbarActions ?>
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
                    <a class="dropdown-item text-danger" href="admin_logout.php">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
