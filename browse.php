<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/product_colors.php';

function colorToCSS(string $name): string {
    $map = [
        'red' => '#ef4444', 'blue' => '#3b82f6', 'black' => '#111827',
        'white' => '#ffffff', 'green' => '#22c55e', 'yellow' => '#eab308',
        'purple' => '#8b5cf6', 'pink' => '#ec4899', 'orange' => '#f97316',
        'gray' => '#9ca3af', 'grey' => '#9ca3af', 'brown' => '#92400e',
        'gold' => '#f59e0b', 'silver' => '#cbd5e1', 'navy' => '#1e3a8a',
        'cyan' => '#06b6d4', 'teal' => '#14b8a6', 'lime' => '#84cc16',
        'rose' => '#f43f5e', 'amber' => '#f59e0b', 'emerald' => '#10b981',
        'indigo' => '#6366f1', 'violet' => '#7c3aed', 'beige' => '#d4b483',
    ];

    return $map[strtolower(trim($name))] ?? '#9ca3af';
}

function renderCartIcon(string $class = ''): string {
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
    return '<svg' . $classAttr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>';
}

$conn = getConnection();
ensureCustomerTables($conn);
$searchTerm = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT p.product_id, p.product_name, p.description, c.category_name,
            p.price, p.stock_quantity, p.image_url
     FROM products p
     INNER JOIN categories c ON p.category_id = c.category_id';
$params = [];
$types = '';

if ($searchTerm !== '') {
    $likeTerm = '%' . $searchTerm . '%';
    $sql .= ' WHERE (p.product_name LIKE ? OR p.description LIKE ? OR c.category_name LIKE ?
                     OR EXISTS (SELECT 1 FROM product_colors pc WHERE pc.product_id = p.product_id AND pc.color LIKE ?))';
    $params = [$likeTerm, $likeTerm, $likeTerm, $likeTerm];
    $types = 'ssss';
}

$sql .= ' ORDER BY p.product_name ASC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($searchTerm !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $products = [];
}

$productColorMap = fetchProductColorsForIds($conn, array_column($products, 'product_id'));

$cartQty = 0;
if (currentCustomerId() || !empty($_SESSION['cart_id'])) {
    $cartId = getOrCreateCart($conn);
    $stmt = $conn->prepare('SELECT COALESCE(SUM(quantity), 0) AS total_qty FROM cart_items WHERE cart_id = ?');
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $cartQty = (int)($stmt->get_result()->fetch_assoc()['total_qty'] ?? 0);
    $stmt->close();
}

$loggedIn = currentCustomerId() !== null;
$customerName = currentCustomerName();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'shop'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div>
            <h1>Shop</h1>
            <div class="form-muted">Discover products, compare options, and continue shopping before checkout.</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
            <form method="GET" action="shop.php" class="d-flex align-items-center gap-2 search-form">
                <input type="search" name="q" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES) ?>" class="form-control" placeholder="Search products..." aria-label="Search products">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($searchTerm !== ''): ?>
                    <a href="shop.php" class="btn btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </form>
            <a href="cart.php" class="btn btn-primary d-inline-flex align-items-center gap-2">
                <span class="d-inline-flex align-items-center gap-1"><?= renderCartIcon('cart-icon') ?> <span>View Shopping Cart</span></span>
                <span class="badge bg-white text-primary rounded-pill" id="shop-cart-badge"><?= $cartQty ?></span>
            </a>
        </div>
    </div>

    <div class="page-content">
        <div class="card mb-4">
            <div class="card-header-custom d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <span>Products</span>
            </div>
            <div class="card-body p-4">
                <?php if (empty($products)): ?>
                    <div class="empty-state"><?php if ($searchTerm !== ''): ?>No products matched “<?= htmlspecialchars($searchTerm) ?>”. Try a different keyword or <a href="shop.php">clear the search</a>.<?php else: ?>No products available right now.<?php endif; ?></div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product):
                            $colors = $productColorMap[(int)$product['product_id']] ?? [];
                            $isOutOfStock = ((int)$product['stock_quantity'] <= 0);
                        ?>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="product-catalog-card <?= $isOutOfStock ? 'opacity-75' : '' ?>" id="pcard-<?= (int)$product['product_id'] ?>">
                                    <div class="product-img-wrap position-relative">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>">
                                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
                                                </a>
                                            <?php else: ?>
                                                <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>" class="d-inline-block w-100">
                                                    <div class="no-img-placeholder">🛍️</div>
                                                </a>
                                            <?php endif; ?>
                                        <span class="cat-badge mb-1 position-absolute top-0 start-0 m-3"><?= htmlspecialchars($product['category_name']) ?></span>
                                    </div>

                                    <div class="product-info">
                                        <div class="catalog-product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.4em;">
                                            <?= htmlspecialchars($product['description'] ?? 'No description provided.') ?>
                                        </p>
                                        <div class="catalog-price">&#8369;<?= number_format((float)$product['price'], 2) ?></div>
                                        <div class="catalog-stock mb-2">
                                            <?php if ($isOutOfStock): ?>
                                                <span class="text-danger fw-bold">Out of Stock</span>
                                            <?php else: ?>
                                                Stock: <span class="fw-semibold text-dark"><?= (int)$product['stock_quantity'] ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                            View Details
                                        </a>

                                        <?php if (!empty($colors) && !$isOutOfStock): ?>
                                            <div class="color-picker-wrap">
                                                <span class="color-picker-label">Color:</span>
                                                <div class="color-swatches" data-selected="">
                                                    <?php foreach ($colors as $color): ?>
                                                        <button type="button"
                                                                class="color-swatch"
                                                                data-color="<?= htmlspecialchars($color) ?>"
                                                                style="background:<?= colorToCSS($color) ?>"
                                                                title="<?= htmlspecialchars($color) ?>">
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                                <span class="selected-color-label"></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!$isOutOfStock): ?>
                                            <div class="d-flex align-items-center gap-2 mt-auto pt-2">
                                                <input type="number" class="form-control form-control-sm add-qty-input"
                                                       id="add-qty-<?= (int)$product['product_id'] ?>" value="1" min="1" max="<?= (int)$product['stock_quantity'] ?>"
                                                       style="width: 65px; text-align: center;">
                                                <button class="btn-add-cart flex-grow-1"
                                                        id="add-btn-<?= (int)$product['product_id'] ?>"
                                                        onclick="addToCart(<?= (int)$product['product_id'] ?>, this)">
                                                    <span class="btn-icon"><?= renderCartIcon('cart-icon') ?></span>
                                                    <span><?= $loggedIn ? 'Add to Cart' : 'Login to Buy' ?></span>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100 mt-auto" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<div id="cart-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const isLoggedIn = <?= $loggedIn ? 'true' : 'false' ?>;

document.querySelectorAll('.color-swatches').forEach(function(container) {
    container.querySelectorAll('.color-swatch').forEach(function(swatch) {
        swatch.addEventListener('click', function() {
            const already = container.dataset.selected === this.dataset.color;
            container.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
            if (already) {
                container.dataset.selected = '';
                container.nextElementSibling.textContent = '';
            } else {
                this.classList.add('selected');
                container.dataset.selected = this.dataset.color;
                container.nextElementSibling.textContent = this.dataset.color;
            }
        });
    });
});

function getSelectedColor(card) {
    const sw = card.querySelector('.color-swatches');
    return sw ? (sw.dataset.selected || '') : '';
}

function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('cart-toast-container');
    var t = document.createElement('div');
    var icons = { success: '✓', error: '✕', info: 'ℹ' };
    t.className = 'toast-item toast-' + type;
    t.innerHTML = '<span class="toast-icon">' + (icons[type] || '✓') + '</span><span>' + message + '</span>';
    container.appendChild(t);
    setTimeout(function() {
        t.classList.add('toast-hiding');
        setTimeout(function() { t.remove(); }, 350);
    }, 3000);
}

async function addToCart(productId, btn) {
    if (!isLoggedIn) {
        window.location.href = 'customer_login.php';
        return;
    }

    var card = btn.closest('.product-catalog-card');
    var color = getSelectedColor(card);
    var qtyInput = document.getElementById('add-qty-' + productId);
    var qty = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;

    btn.disabled = true;
    var origHTML = btn.innerHTML;
    btn.textContent = 'Adding…';

    try {
        var fd = new FormData();
        fd.append('action', 'add');
        fd.append('product_id', productId);
        fd.append('quantity', qty);
        fd.append('color', color);

        var res = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            btn.textContent = '✓ Added!';

            var badge = document.getElementById('shop-cart-badge');
            if (badge) {
                badge.textContent = parseInt(badge.textContent || 0) + qty;
            }
            setTimeout(function() {
                btn.innerHTML = origHTML;
                btn.disabled = false;
            }, 1200);
        } else {
            showToast(data.message || 'Failed to add item.', 'error');
            btn.innerHTML = origHTML;
            btn.disabled = false;
        }
    } catch (e) {
        showToast('A network error occurred.', 'error');
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
}
</script>
</body>
</html>
