<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/product_colors.php';

// ── Color helper ──────────────────────────────────────────────
function colorToCSS(string $name): string {
    $map = [
        'red'     => '#ef4444', 'blue'    => '#3b82f6', 'black'   => '#111827',
        'white'   => '#ffffff', 'green'   => '#22c55e', 'yellow'  => '#eab308',
        'purple'  => '#8b5cf6', 'pink'    => '#ec4899', 'orange'  => '#f97316',
        'gray'    => '#9ca3af', 'grey'    => '#9ca3af', 'brown'   => '#92400e',
        'gold'    => '#f59e0b', 'silver'  => '#cbd5e1', 'navy'    => '#1e3a8a',
        'cyan'    => '#06b6d4', 'teal'    => '#14b8a6', 'lime'    => '#84cc16',
        'rose'    => '#f43f5e', 'amber'   => '#f59e0b', 'emerald' => '#10b981',
        'indigo'  => '#6366f1', 'violet'  => '#7c3aed', 'beige'   => '#d4b483',
    ];
    return $map[strtolower(trim($name))] ?? '#9ca3af';
}

$conn = getConnection();
$searchTerm = trim((string)($_GET['q'] ?? ''));
$selectedCategoryId = (int)($_GET['category'] ?? 0);
$selectedCategoryName = '';
$categories = $conn->query(
    'SELECT category_id, category_name FROM categories ORDER BY category_name ASC'
)->fetch_all(MYSQLI_ASSOC);

if ($selectedCategoryId > 0) {
    $categoryStmt = $conn->prepare('SELECT category_name FROM categories WHERE category_id = ?');
    $categoryStmt->bind_param('i', $selectedCategoryId);
    $categoryStmt->execute();
    $selectedCategoryName = (string)($categoryStmt->get_result()->fetch_assoc()['category_name'] ?? '');
    $categoryStmt->close();
}

// Fetch available products
$sql = 'SELECT p.product_id, p.product_name, p.description, c.category_name,
            p.price, p.stock_quantity, p.image_url
     FROM   products p
     INNER JOIN categories c ON p.category_id = c.category_id';
$params = [];
$types = '';
$where = [];

if ($selectedCategoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $selectedCategoryId;
    $types .= 'i';
}

if ($searchTerm !== '') {
    $likeTerm = '%' . $searchTerm . '%';
    $where[] = '(p.product_name LIKE ? OR p.description LIKE ? OR c.category_name LIKE ?
                 OR EXISTS (SELECT 1 FROM product_colors pc WHERE pc.product_id = p.product_id AND pc.color LIKE ?))';
    $params = array_merge($params, [$likeTerm, $likeTerm, $likeTerm, $likeTerm]);
    $types .= 'ssss';
}

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY p.product_name ASC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $products = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $products = [];
}

$productColorMap = fetchProductColorsForIds($conn, array_column($products, 'product_id'));

// Fetch current cart count
$cart_id = null;
$total_cart_qty = 0;
if (currentCustomerId() || !empty($_SESSION['cart_id'])) {
    $cart_id = getOrCreateCart($conn);
    $s = $conn->prepare('SELECT SUM(quantity) as total_qty FROM cart_items WHERE cart_id = ?');
    $s->bind_param('i', $cart_id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $total_cart_qty = (int)($r['total_qty'] ?? 0);
    $s->close();
}

$conn->close();
$activePage = 'shop';

function renderCartIcon(string $class = ''): string {
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '';
    return '<svg' . $classAttr . ' xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Shop — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page catalog-page">

<?php $customerActivePage = 'shop'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">

    <!-- Topbar -->
    <div class="page-topbar d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <div>
            <h1>Customer Store Catalog</h1>
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
                <span class="badge bg-white text-primary rounded-pill" id="shop-cart-badge"><?= $total_cart_qty ?></span>
            </a>
        </div>
    </div>

    <div class="page-content">

        <section class="catalog-heading" aria-labelledby="catalog-title">
            <div>
                <p class="catalog-eyebrow">Bloom &amp; Basket catalog</p>
                <h1 id="catalog-title"><?= $selectedCategoryName !== '' ? htmlspecialchars($selectedCategoryName) : 'Shop all products' ?></h1>
                <p>Browse everyday essentials and choose the options that suit you.</p>
            </div>
            <form method="GET" action="shop.php" class="catalog-search" role="search">
                <?php if ($selectedCategoryId > 0): ?>
                    <input type="hidden" name="category" value="<?= $selectedCategoryId ?>">
                <?php endif; ?>
                <label class="visually-hidden" for="catalog-search-input">Search catalog</label>
                <input id="catalog-search-input" type="search" name="q" value="<?= htmlspecialchars($searchTerm, ENT_QUOTES) ?>" placeholder="Search products" aria-label="Search catalog">
                <button type="submit">Search</button>
            </form>
        </section>

        <nav class="catalog-filters" aria-label="Product categories">
            <a href="shop.php<?= $searchTerm !== '' ? '?q=' . rawurlencode($searchTerm) : '' ?>" class="catalog-filter <?= $selectedCategoryId === 0 ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $category): ?>
                <?php $filterQuery = http_build_query(array_filter(['category' => (int)$category['category_id'], 'q' => $searchTerm], static fn($value) => $value !== '')); ?>
                <a href="shop.php?<?= htmlspecialchars($filterQuery, ENT_QUOTES) ?>" class="catalog-filter <?= $selectedCategoryId === (int)$category['category_id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($category['category_name']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($selectedCategoryId > 0 || $searchTerm !== ''): ?>
                <a href="shop.php" class="catalog-filter catalog-filter-clear">Clear filters</a>
            <?php endif; ?>
        </nav>

        <div class="catalog-surface">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <?php if ($selectedCategoryName !== ''): ?>
                            No products are available in “<?= htmlspecialchars($selectedCategoryName) ?>” right now. <a href="shop.php">View all products</a>.
                        <?php elseif ($searchTerm !== ''): ?>
                            No products matched “<?= htmlspecialchars($searchTerm) ?>”. Try a different keyword or <a href="shop.php">clear the search</a>.
                        <?php else: ?>
                            No products available in the catalog right now.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3 product-grid">
                        <?php foreach ($products as $p):
                            $colors = $productColorMap[(int)$p['product_id']] ?? [];
                            $isOutOfStock = ((int)$p['stock_quantity'] <= 0);
                        ?>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="product-catalog-card <?= $isOutOfStock ? 'opacity-75' : '' ?>" id="pcard-<?= $p['product_id'] ?>">
                                <!-- Image -->
                                <div class="product-img-wrap">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <a href="product_details.php?product_id=<?= (int)$p['product_id'] ?>">
                                            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['product_name']) ?>">
                                        </a>
                                    <?php else: ?>
                                        <a href="product_details.php?product_id=<?= (int)$p['product_id'] ?>" class="d-inline-block w-100">
                                            <div class="no-img-placeholder">🛍️</div>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="product-info">
                                    <span class="cat-badge mb-1"><?= htmlspecialchars($p['category_name']) ?></span>
                                    <div class="catalog-product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                                    <p class="catalog-product-description">
                                        <?= htmlspecialchars($p['description'] ?? 'No description provided.') ?>
                                    </p>
                                    <div class="catalog-price-stock">
                                        <div class="catalog-price">&#8369;<?= number_format((float)$p['price'], 2) ?></div>
                                        <div class="catalog-stock">
                                            <?php if ($isOutOfStock): ?>
                                                <span class="text-danger fw-bold">Out of Stock</span>
                                            <?php else: ?>
                                                Stock: <span class="fw-semibold text-dark"><?= (int)$p['stock_quantity'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($colors) && !$isOutOfStock): ?>
                                    <div class="color-picker-wrap">
                                        <span class="color-picker-label">Color:</span>
                                        <div class="color-swatches" data-selected="">
                                            <?php foreach ($colors as $clr): ?>
                                            <button type="button"
                                                    class="color-swatch"
                                                    data-color="<?= htmlspecialchars($clr) ?>"
                                                    style="background:<?= colorToCSS($clr) ?>"
                                                    title="<?= htmlspecialchars($clr) ?>">
                                            </button>
                                            <?php endforeach; ?>
                                        </div>
                                        <span class="selected-color-label"></span>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Add to Cart Quantity & Button -->
                                    <?php if (!$isOutOfStock): ?>
                                        <div class="d-flex align-items-center gap-2 mt-auto pt-2">
                                            <input type="number" class="form-control form-control-sm add-qty-input"
                                                   id="add-qty-<?= $p['product_id'] ?>" value="1" min="1" max="<?= (int)$p['stock_quantity'] ?>"
                                                   aria-label="Quantity for <?= htmlspecialchars($p['product_name'], ENT_QUOTES) ?>">
                                            <button class="btn-add-cart flex-grow-1"
                                                    id="add-btn-<?= $p['product_id'] ?>"
                                                    onclick="addToCart(<?= $p['product_id'] ?>, this, event)">
                                                <span class="btn-icon"><?= renderCartIcon('cart-icon') ?></span>
                                                <span>Add to Cart</span>
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

<?php require __DIR__ . '/includes/customer_footer.php'; ?>

<!-- Toast notifications -->
<div id="cart-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Color swatches selection
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

async function addToCart(productId, btn, event) {
    var card  = btn.closest('.product-catalog-card');
    var colorSwatches = card ? card.querySelector('.color-swatches') : null;
    var hasColorOptions = colorSwatches && colorSwatches.querySelectorAll('.color-swatch').length > 0;
    var color = getSelectedColor(card);

    if (hasColorOptions && !color) {
        showToast('Please select a valid product color.', 'error');
        // Shake color swatches to prompt user
        if (colorSwatches) {
            colorSwatches.classList.remove('swatch-shake');
            void colorSwatches.offsetWidth;
            colorSwatches.classList.add('swatch-shake');
            setTimeout(function() { colorSwatches.classList.remove('swatch-shake'); }, 600);
        }
        return;
    }

    var qtyInput = document.getElementById('add-qty-' + productId);
    var qty   = qtyInput ? (parseInt(qtyInput.value) || 1) : 1;
    var clickPos = (event && typeof event.clientX === 'number') ? { clientX: event.clientX, clientY: event.clientY } : null;

    btn.disabled    = true;
    var origHTML    = btn.innerHTML;
    btn.textContent = 'Adding…';

    try {
        var fd = new FormData();
        fd.append('action',     'add');
        fd.append('product_id', productId);
        fd.append('quantity',   qty);
        fd.append('color',      color);
        fd.append('csrf_token', <?= json_encode(customerCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);

        var res  = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            // Only fly to basket if the item was successfully added!
            var imgEl = card ? card.querySelector('.product-img-wrap img') : null;
            if (typeof window.animateFlyToCart === 'function') {
                window.animateFlyToCart(imgEl || btn, clickPos);
            }

            showToast(data.message, 'success');
            btn.textContent = '✓ Added!';
            
            // Update cart badges in real time
            if (typeof window.updateNavCartCount === 'function' && typeof data.total_cart_qty !== 'undefined') {
                window.updateNavCartCount(data.total_cart_qty);
            } else {
                var badge = document.getElementById('shop-cart-badge');
                if (badge) {
                    badge.textContent = parseInt(badge.textContent || 0) + qty;
                }
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
        btn.disabled    = false;
    }
}
</script>
</body>
</html>
