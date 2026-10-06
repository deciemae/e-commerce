<?php
require_once 'config/db.php';
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'admin_auth.php';
require_once 'includes/product_colors.php';
requireAdminLogin();

function normalizeImagePath(?string $path): string
{
    return trim((string)$path);
}

function removeStoredImage(?string $path): void
{
    adminRemoveStoredUpload(normalizeImagePath($path), __DIR__ . '/uploads');
}

function uploadProductImage(array $file, string &$error): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $error = 'The uploaded image could not be processed.';
        return '';
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 2 * 1024 * 1024;

    $tmpPath = $file['tmp_name'] ?? '';
    $fileSize = (int)($file['size'] ?? 0);
    $originalExtension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $fileSize <= 0) {
        $error = 'The uploaded image is invalid.';
        return '';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    if (!$mimeType || !isset($allowedTypes[$mimeType]) || !in_array($originalExtension, $allowedExtensions, true)) {
        $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
        return '';
    }

    if ($fileSize > $maxSize) {
        $error = 'File is too large. Maximum size is 2 MB.';
        return '';
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowedTypes[$mimeType];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        $error = 'Failed to save the uploaded file.';
        return '';
    }

    return 'uploads/' . $filename;
}

function fetchProductById(mysqli $conn, int $productId): ?array
{
    $stmt = $conn->prepare(
        'SELECT product_id, category_id, product_name, description, price, stock_quantity, image_url
         FROM products
         WHERE product_id = ?'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($product) {
        $product['color_options'] = implode(', ', fetchProductColors($conn, $productId));
    }

    return $product ?: null;
}

$error = '';

$conn = getConnection();

$catResult = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC');
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

$editProductId = (int)($_GET['edit'] ?? ($_SESSION['flash_edit_product_id'] ?? 0));
unset($_SESSION['flash_edit_product_id']);
$editProduct = $editProductId > 0 ? fetchProductById($conn, $editProductId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireValidCsrf('products.php');
    $action = trim($_POST['action'] ?? 'add');

    if (!in_array($action, ['add', 'update', 'delete'], true)) {
        adminSetFlash('error', 'Invalid product action.');
        adminRedirect('products.php');
    }

    if ($action === 'delete') {
        $productId = (int)($_POST['product_id'] ?? 0);

        if ($productId <= 0) {
            adminSetFlash('error', 'Invalid product selected for deletion.');
        } else {
            $product = fetchProductById($conn, $productId);

            if (!$product) {
                adminSetFlash('error', 'Product not found.');
            } else {
                $stmt = $conn->prepare('DELETE FROM products WHERE product_id = ?');
                $stmt->bind_param('i', $productId);

                if ($stmt->execute()) {
                    logAdminActivity('Delete Product', "Deleted product ID: {$productId} ({$product['product_name']})");
                    removeStoredImage($product['image_url'] ?? '');
                    adminSetFlash('success', 'Product "' . $product['product_name'] . '" deleted successfully.');
                } else {
                    adminSetFlash('error', 'The product could not be deleted. It may be referenced by an existing order.');
                }

                $stmt->close();
            }
        }

        adminRedirect('products.php');
    }

    $productId      = (int)($_POST['product_id'] ?? 0);
    $categoryId     = (int)($_POST['category_id'] ?? 0);
    $productName    = trim($_POST['product_name'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $priceInput     = trim($_POST['price'] ?? '');
    $stockInput     = trim((string)($_POST['stock_quantity'] ?? ''));
    $stockQuantity  = ctype_digit($stockInput) ? (int)$stockInput : -1;
    $colorOptions   = trim($_POST['color_options'] ?? '');
    $currentProduct = $action === 'update' && $productId > 0 ? fetchProductById($conn, $productId) : null;

    $priceValue = is_numeric($priceInput) ? (float)$priceInput : -1;

    if ($productName === '') {
        $error = 'Product name is required.';
    } elseif (mb_strlen($productName) > 255) {
        $error = 'Product name is too long.';
    } elseif ($categoryId <= 0) {
        $error = 'Please select a valid category.';
    } elseif (!is_finite($priceValue) || $priceValue < 0) {
        $error = 'Please enter a valid price (0 or greater).';
    } elseif ($stockQuantity < 0) {
        $error = 'Please enter a valid whole-number stock quantity.';
    } elseif ($action === 'update' && !$currentProduct) {
        $error = 'Product not found.';
    } else {
        $uploadedImage = '';

        if (!empty($_FILES['product_image']['name'])) {
            $uploadedImage = uploadProductImage($_FILES['product_image'], $error);
        }

        if ($error === '') {
            $price = number_format($priceValue, 2, '.', '');
            $imageUrl = $action === 'update' ? ($currentProduct['image_url'] ?? '') : '';
            if ($uploadedImage !== '') {
                $imageUrl = $uploadedImage;
            }

            $adminId = currentAdminId();

            if ($action === 'update') {
                $stmt = $conn->prepare(
                    'UPDATE products
                     SET category_id = ?, product_name = ?, description = ?, price = ?, stock_quantity = ?, image_url = ?, updated_by = ?
                     WHERE product_id = ?'
                );
                $stmt->bind_param('issdisii', $categoryId, $productName, $description, $price, $stockQuantity, $imageUrl, $adminId, $productId);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO products (category_id, product_name, description, price, stock_quantity, image_url, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('issdisii', $categoryId, $productName, $description, $price, $stockQuantity, $imageUrl, $adminId, $adminId);
            }

            if ($stmt->execute()) {
                $savedProductId = $action === 'update' ? $productId : $conn->insert_id;
                setProductColors($conn, $savedProductId, $colorOptions);

                if ($action === 'update') {
                    logAdminActivity('Update Product', "Updated product ID: {$productId} ({$productName}), Stock: {$stockQuantity}");
                } else {
                    $newProductId = $savedProductId;
                    logAdminActivity('Add Product', "Added product ID: {$newProductId} ({$productName}), Stock: {$stockQuantity}");
                }

                if ($uploadedImage !== '' && $action === 'update' && !empty($currentProduct['image_url']) && $currentProduct['image_url'] !== $uploadedImage) {
                    removeStoredImage($currentProduct['image_url']);
                }

                adminSetFlash('success', $action === 'update'
                    ? 'Product "' . $productName . '" updated successfully.'
                    : 'Product "' . $productName . '" added successfully.');
                $stmt->close();
                $conn->close();
                adminRedirect('products.php');
            }

            $error = 'The product could not be saved. Please review the values and try again.';
            $stmt->close();

            if ($uploadedImage !== '') {
                removeStoredImage($uploadedImage);
            }
        }
    }

    if ($error !== '') {
        adminSetFlash('error', $error);
        if ($action === 'update' && $productId > 0) {
            $_SESSION['flash_edit_product_id'] = $productId;
        } else {
            $_SESSION['flash_add_error'] = true;
        }
        adminRedirect('products.php' . ($action === 'update' && $productId > 0 ? '?edit=' . $productId : ''));
    }
}

$showAddModal = false;
if (!empty($_SESSION['flash_add_error'])) {
    $showAddModal = true;
    unset($_SESSION['flash_add_error']);
}

$sql = 'SELECT p.product_id,
               p.product_name,
               c.category_name,
               p.description,
               p.price,
               p.stock_quantity,
               p.image_url,
               p.created_at,
               creator.email AS created_by_email,
               updater.email AS updated_by_email
        FROM products p
        INNER JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN admins creator ON p.created_by = creator.admin_id
        LEFT JOIN admins updater ON p.updated_by = updater.admin_id
        ORDER BY p.product_name ASC';
$result = $conn->query($sql);
$products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$productColorMap = fetchProductColorsForIds($conn, array_column($products, 'product_id'));

if ($editProduct && !empty($editProduct['category_id'])) {
    $editProduct['product_id'] = (int)$editProduct['product_id'];
}

$conn->close();
$activePage = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products &mdash; Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php
    $pageTitle = 'Products';
    $topbarActions = '<a href="shop.php" class="btn btn-outline-primary btn-sm fw-semibold">Open Storefront</a>';
    require_once 'includes/admin_topbar.php';
    ?>

    <div class="page-content">


        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-dark fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#productModal">
                + Add Product
            </button>
        </div>

        <div class="card">
            <div class="card-header-custom">All Products</div>
            <div class="card-body p-0">
                <?php if (empty($products)): ?>
                    <div class="empty-state">No products found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 product-admin-table">
                            <caption class="visually-hidden">Products, inventory, pricing, and management actions</caption>
                            <thead>
                                <tr>
                                    <th class="product-column">Product</th>
                                    <th class="category-column">Category</th>
                                    <th class="description-column">Description</th>
                                    <th class="price-column">Price</th>
                                    <th class="inventory-column">Inventory</th>
                                    <th class="audit-column">Record Details</th>
                                    <th class="table-col-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="product-record">
                                                <?php if (!empty($p['image_url'])): ?>
                                                    <a href="<?= htmlspecialchars($p['image_url']) ?>" target="_blank" rel="noopener noreferrer">
                                                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>" class="product-thumb">
                                                    </a>
                                                <?php else: ?>
                                                    <span class="product-thumb product-thumb-placeholder text-muted" aria-hidden="true">&mdash;</span>
                                                <?php endif; ?>
                                                <div class="product-record-copy">
                                                    <span class="product-record-name"><?= htmlspecialchars($p['product_name']) ?></span>
                                                    <span class="product-record-id">Product #<?= (int)$p['product_id'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="cat-badge"><?= htmlspecialchars($p['category_name']) ?></span></td>
                                        <td class="text-muted table-cell-summary">
                                            <?= htmlspecialchars($p['description'] ?? '—') ?>
                                        </td>
                                        <td class="fw-semibold text-success">&#8369;<?= number_format((float)$p['price'], 2) ?></td>
                                        <td>
                                            <div class="product-inventory">
                                                <span class="product-stock-value"><?= (int)$p['stock_quantity'] ?> in stock</span>
                                                <?php $productColors = $productColorMap[(int)$p['product_id']] ?? []; ?>
                                                <span class="product-colors"><?= !empty($productColors) ? htmlspecialchars(implode(', ', $productColors)) : 'No color options' ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="product-audit">
                                                <span><strong>Created:</strong> <?= htmlspecialchars($p['created_at']) ?></span>
                                                <span><strong>By:</strong> <?= htmlspecialchars($p['created_by_email'] ?? '—') ?></span>
                                                <span><strong>Updated by:</strong> <?= htmlspecialchars($p['updated_by_email'] ?? '—') ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2 align-items-center">
                                                <a href="product_details.php?product_id=<?= (int)$p['product_id'] ?>" class="btn btn-sm btn-outline-primary action-icon" title="View product" aria-label="View product">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>
                                                </a>
                                                <a href="products.php?edit=<?= (int)$p['product_id'] ?>" class="btn btn-sm btn-outline-primary action-icon" title="Edit product" aria-label="Edit product">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.854a.5.5 0 0 1 .708 0l2.292 2.292a.5.5 0 0 1 0 .708l-9.193 9.193a.5.5 0 0 1-.168.11l-4 1.5a.5.5 0 0 1-.65-.65l1.5-4a.5.5 0 0 1 .11-.168L12.146.854zM11.207 2L3 10.207V13h2.793L14 4.793 11.207 2z"/></svg>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger action-icon js-delete-product" data-product-id="<?= (int)$p['product_id'] ?>" data-product-name="<?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8') ?>" title="Delete product" aria-label="Delete product" data-bs-toggle="modal" data-bs-target="#deleteProductModal">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 table-count"><?= count($products) ?> product<?= count($products) === 1 ? '' : 's' ?> found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket Management System
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable product-editor-modal">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="productModalLabel"><?= $editProduct ? 'Edit Product' : 'Add New Product' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <?php if (empty($categories)): ?>
                    <p class="text-muted mb-0 admin-form-hint">
                        You must <a href="categories.php">add a category</a> before adding a product.
                    </p>
                <?php else: ?>
                    <form method="POST" action="products.php<?= $editProduct ? '?edit=' . (int)$editProduct['product_id'] : '' ?>" enctype="multipart/form-data">
                        <?= adminCsrfInput() ?>
                        <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'add' ?>">
                        <?php if ($editProduct): ?>
                            <input type="hidden" name="product_id" value="<?= (int)$editProduct['product_id'] ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" id="product_name" name="product_name" class="form-control" placeholder="e.g. Wireless Headphones" maxlength="150" required value="<?= htmlspecialchars($editProduct['product_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-5">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select id="category_id" name="category_id" class="form-select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= (int)$cat['category_id'] ?>" <?= isset($editProduct['category_id']) && (int)$editProduct['category_id'] === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="color_options" class="form-label">Color Options</label>
                                <input type="text" id="color_options" name="color_options" class="form-control" placeholder="e.g. Red, Blue, Black" maxlength="255" value="<?= htmlspecialchars($editProduct['color_options'] ?? '') ?>">
                                <div class="form-text text-muted">Comma-separated colors. Leave empty if none.</div>
                            </div>
                            <div class="col-md-3">
                                <label for="price" class="form-label">Price (&#8369;) <span class="text-danger">*</span></label>
                                <input type="number" id="price" name="price" class="form-control" placeholder="0.00" min="0" step="0.01" required value="<?= htmlspecialchars(isset($editProduct['price']) ? number_format((float)$editProduct['price'], 2, '.', '') : '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="stock_quantity" class="form-label">Stock</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" placeholder="0" min="0" value="<?= htmlspecialchars((string)($editProduct['stock_quantity'] ?? '0')) ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" class="form-control" placeholder="Optional product description" rows="3"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label for="product_image" class="form-label">Product Image</label>
                                <input type="file" id="product_image" name="product_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text text-muted">JPG, PNG, GIF or WEBP &mdash; max 2 MB. Optional.</div>
                                <?php if ($editProduct && !empty($editProduct['image_url'])): ?>
                                    <div class="mt-3 d-flex align-items-center gap-3">
                                        <img src="<?= htmlspecialchars($editProduct['image_url']) ?>" alt="Current product image" class="product-thumb product-thumb-lg">
                                        <div class="text-muted small">Current image</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary w-100"><?= $editProduct ? 'Update Product' : 'Add Product' ?></button>
                                    <?php if ($editProduct): ?>
                                        <a href="products.php" class="btn btn-outline-secondary w-100">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete <strong id="deleteProductName">this product</strong>?</p>
                <p class="text-muted mb-0 admin-form-hint">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="products.php" class="m-0" id="deleteProductForm">
                    <?= adminCsrfInput() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId" value="">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var deleteButtons = document.querySelectorAll('.js-delete-product');
        var deleteProductName = document.getElementById('deleteProductName');
        var deleteProductId = document.getElementById('deleteProductId');

        deleteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                deleteProductName.textContent = button.getAttribute('data-product-name') || 'this product';
                deleteProductId.value = button.getAttribute('data-product-id') || '';
            });
        });

        <?php if ($editProduct || $showAddModal): ?>
        var productModal = new bootstrap.Modal(document.getElementById('productModal'));
        productModal.show();
        <?php endif; ?>
        
        var productModalEl = document.getElementById('productModal');
        if (productModalEl) {
            productModalEl.addEventListener('hidden.bs.modal', function () {
                if (window.location.search.indexOf('edit=') > -1) {
                    window.location.href = 'products.php';
                }
            });
        }
    });
</script>
</body>
</html>
