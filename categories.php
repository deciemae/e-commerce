<?php
require_once 'config/db.php';
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'admin_auth.php';
requireAdminLogin();

// ── Handle Add / Edit / Delete Category ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireValidCsrf('categories.php');
    $action = $_POST['action'] ?? 'add';
    $conn = getConnection();

    if (!in_array($action, ['add', 'update', 'delete'], true)) {
        $conn->close();
        adminSetFlash('error', 'Invalid category action.');
        adminRedirect('categories.php');
    }
    
    if ($action === 'delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            adminSetFlash('error', 'Invalid category selected.');
        } else {
            $checkStmt = $conn->prepare('SELECT COUNT(*) as cnt FROM products WHERE category_id = ?');
            $checkStmt->bind_param('i', $categoryId);
            $checkStmt->execute();
            $count = $checkStmt->get_result()->fetch_assoc()['cnt'];
            $checkStmt->close();
            
            if ($count > 0) {
                adminSetFlash('error', "Cannot delete category because it has $count product(s) associated with it.");
            } else {
                $stmt = $conn->prepare('DELETE FROM categories WHERE category_id = ?');
                $stmt->bind_param('i', $categoryId);
                if ($stmt->execute()) {
                    logAdminActivity('Delete Category', "Deleted category ID: {$categoryId}");
                    adminSetFlash('success', 'Category deleted successfully.');
                } else {
                    adminSetFlash('error', 'The category could not be deleted.');
                }
                $stmt->close();
            }
        }
    } else {
        $name = trim($_POST['category_name'] ?? '');
        $desc = trim($_POST['description']   ?? '');
        
        if ($name === '') {
            adminSetFlash('error', 'Category name is required.');
        } elseif (mb_strlen($name) > 100 || mb_strlen($desc) > 255) {
            adminSetFlash('error', 'Category names are limited to 100 characters and descriptions to 255 characters.');
        } else {
            if ($action === 'update') {
                $categoryId = (int)($_POST['category_id'] ?? 0);
                if ($categoryId <= 0) {
                    $conn->close();
                    adminSetFlash('error', 'Invalid category selected.');
                    adminRedirect('categories.php');
                }
                $stmt = $conn->prepare('UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?');
                $stmt->bind_param('ssi', $name, $desc, $categoryId);
                if ($stmt->execute()) {
                    logAdminActivity('Update Category', "Updated category ID: {$categoryId}");
                    adminSetFlash('success', 'Category "' . $name . '" updated successfully.');
                } else {
                    adminSetFlash('error', 'The category could not be updated.');
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO categories (category_name, description) VALUES (?, ?)');
                $stmt->bind_param('ss', $name, $desc);
                if ($stmt->execute()) {
                    logAdminActivity('Add Category', "Added category: {$name}");
                    adminSetFlash('success', 'Category "' . $name . '" added successfully.');
                } else {
                    adminSetFlash('error', 'The category could not be added.');
                }
                $stmt->close();
            }
        }
    }
    $conn->close();
    adminRedirect('categories.php');
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $conn = getConnection();
    $stmt = $conn->prepare('SELECT * FROM categories WHERE category_id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editCategory = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
}


// ── Fetch All Categories ─────────────────────────────────────────────────────
$conn       = getConnection();
$result     = $conn->query('SELECT category_id, category_name, description, created_at FROM categories ORDER BY category_name ASC');
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();
?>
<?php $activePage = 'categories'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'Categories'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content">

        <!-- Add/Edit Category -->
        <div class="card mb-4 category-editor-card">
            <div class="card-header-custom"><?= $editCategory ? 'Edit Category' : 'Add New Category' ?></div>
            <div class="card-body p-4">
                <form method="POST" action="categories.php" class="category-editor-form">
                    <?= adminCsrfInput() ?>
                    <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'add' ?>">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="category_id" value="<?= (int)$editCategory['category_id'] ?>">
                    <?php endif; ?>
                    <div class="category-editor-grid">
                        <div class="category-editor-field">
                            <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" id="category_name" name="category_name"
                                   class="form-control" placeholder="e.g. Electronics"
                                   maxlength="100" required value="<?= htmlspecialchars($editCategory['category_name'] ?? '') ?>">
                        </div>
                        <div class="category-editor-field">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" id="description" name="description"
                                   class="form-control" placeholder="Optional description"
                                   maxlength="255" value="<?= htmlspecialchars($editCategory['description'] ?? '') ?>">
                        </div>
                        <div class="d-flex gap-2 category-editor-actions">
                            <button type="submit" class="btn btn-primary text-nowrap"><?= $editCategory ? 'Update Category' : 'Add Category' ?></button>
                            <?php if ($editCategory): ?>
                                <a href="categories.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-center">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="card">
            <div class="card-header-custom">All Categories</div>
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="empty-state">No categories found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <caption class="visually-hidden">Product categories and management actions</caption>
                            <thead>
                                <tr>
                                    <th class="table-col-id">#</th>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Created At</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $i => $cat): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($cat['category_name']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($cat['created_at']) ?></td>
                                        <td>
                                            <div class="d-flex gap-2 align-items-center justify-content-end">
                                                <a href="shop.php?category=<?= (int)$cat['category_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary action-icon" title="View category" aria-label="View category">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>
                                                </a>
                                                <a href="categories.php?edit=<?= (int)$cat['category_id'] ?>" class="btn btn-sm btn-outline-primary action-icon" title="Edit category" aria-label="Edit category">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.193 9.193a.5.5 0 0 1-.168.11l-4 1.5a.5.5 0 0 1-.65-.65l1.5-4a.5.5 0 0 1 .11-.168L12.146.854zM11.207 2L3 10.207V13h2.793L14 4.793 11.207 2z"/></svg>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger action-icon js-delete-category" data-category-id="<?= (int)$cat['category_id'] ?>" data-category-name="<?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>" title="Delete category" aria-label="Delete category" data-bs-toggle="modal" data-bs-target="#deleteCategoryModal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 table-count"><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?> found.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="categories.php" class="modal-content">
            <?= adminCsrfInput() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" id="delete_category_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="deleteCategoryModalLabel">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-0">Are you sure you want to delete the category <strong id="delete_category_name"></strong>?</p>
                <p class="text-danger small mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> This action cannot be undone. You cannot delete a category if it still has products attached to it.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Category</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('.js-delete-category');
        const deleteIdInput = document.getElementById('delete_category_id');
        const deleteNameSpan = document.getElementById('delete_category_name');

        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                deleteIdInput.value = this.dataset.categoryId;
                deleteNameSpan.textContent = '"' + this.dataset.categoryName + '"';
            });
        });
    });
</script>
</body>
</html>
