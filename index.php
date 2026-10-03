<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

$conn = getConnection();

/*
 * HERO IMAGE
 * Replace this path with any image you want to use for the hero section.
 * Example: assets/images/hero.jpg
 */
$heroImage = 'assets/hero-photo.png';

$totalProducts = (int)($conn->query('SELECT COUNT(*) AS total FROM products')->fetch_assoc()['total'] ?? 0);

$categoryRows = $conn->query(
    'SELECT category_id, category_name, description
     FROM categories
     ORDER BY category_name ASC'
)->fetch_all(MYSQLI_ASSOC);

$categoryHighlights = [];
foreach ($categoryRows as $category) {
    $stmt = $conn->prepare(
        'SELECT product_id, product_name, description, price, stock_quantity, image_url
         FROM products
         WHERE category_id = ?
         ORDER BY product_id DESC
         LIMIT 1'
    );
    $stmt->bind_param('i', $category['category_id']);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $categoryHighlights[] = [
        'category' => $category,
        'product' => $product,
    ];
}

$featuredProducts = $conn->query(
    'SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.image_url,
            p.category_id, c.category_name
     FROM products p
     INNER JOIN categories c ON p.category_id = c.category_id
    ORDER BY c.category_name ASC, p.created_at DESC, p.product_id DESC'
)->fetch_all(MYSQLI_ASSOC);

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
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloom &amp; Basket | Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <style>
        .featured-product-card { position: relative; }
        .featured-add-cart { position: absolute; top: 8px; right: 8px; z-index: 5; border-radius: 999px; padding: .35rem .45rem; }
        #cart-toast-container { position: fixed; right: 18px; bottom: 18px; z-index: 1200; }
        .toast-item { display:flex; align-items:center; gap:.5rem; background:#0f172a; color:#fff; padding:.6rem .8rem; margin-top:.5rem; border-radius:.5rem; box-shadow:0 6px 18px rgba(2,6,23,.2); opacity:1; transition: transform .25s ease, opacity .25s ease; }
        .toast-item.toast-hiding { transform: translateY(8px); opacity:0; }
        .toast-item .toast-icon { font-weight:700; }

        /* ===== Custom Hero Section ===== */
        .custom-hero {
            position: relative;
            min-height: 430px;
            border-radius: 28px;
            overflow: hidden;
            isolation: isolate;
            display: flex;
            align-items: center;
            background: #111827;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .14);
        }

        .custom-hero-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -3;
        }

        .custom-hero-overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg,
                    rgba(0, 0, 0, .78) 0%,
                    rgba(0, 0, 0, .55) 42%,
                    rgba(0, 0, 0, .18) 75%,
                    rgba(0, 0, 0, .08) 100%);
            z-index: -2;
        }

        .custom-hero-content {
            width: 100%;
            max-width: 1250px;
            padding: 64px 52px;
            color: #fff;
        }

        .custom-hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 18px;
            color: rgba(255,255,255,.82);
            font-size: .82rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .custom-hero-eyebrow::before {
            content: "";
            width: 34px;
            height: 2px;
            background: currentColor;
            border-radius: 999px;
        }

        .custom-hero h1 {
            margin: 0 0 18px;
            max-width: none;
            font-size: clamp(2.5rem, 5vw, 4.8rem);
            line-height: .98;
            letter-spacing: -.045em;
            font-weight: 800;
        }

        .custom-hero h1 .hero-line {
            display: block;
            white-space: nowrap;
        }

        .custom-hero p {
            max-width: none;
            margin: 0 0 30px;
            color: rgba(255,255,255,.88);
            font-size: clamp(1rem, 1.5vw, 1.18rem);
            line-height: 1.7;
            white-space: nowrap;
        }

        .custom-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .custom-hero-actions .btn {
            min-height: 48px;
            padding: 12px 22px;
            border-radius: 12px;
            font-weight: 700;
        }

        .custom-hero-actions .btn-light {
            color: #111827;
            border: 1px solid #fff;
        }

        .custom-hero-actions .btn-outline-light {
            border-width: 1px;
            background: rgba(255,255,255,.06);
            backdrop-filter: blur(6px);
        }

        .custom-hero-actions .btn:hover {
            transform: translateY(-2px);
            transition: .2s ease;
        }

        @media (max-width: 767.98px) {
            .custom-hero {
                min-height: 500px;
                border-radius: 20px;
            }

            .custom-hero-content {
                padding: 48px 28px;
            }

            .custom-hero-overlay {
                background: linear-gradient(90deg,
                    rgba(0,0,0,.82) 0%,
                    rgba(0,0,0,.60) 100%);
            }

            .custom-hero h1 {
                font-size: clamp(2.35rem, 11vw, 3.4rem);
            }

            .custom-hero h1 .hero-line {
                white-space: normal;
            }

            .custom-hero p {
                white-space: normal;
            }
        }

        .category-cards-scroll {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 12px;
            scrollbar-width: thin;
        }
        .category-cards-scroll::-webkit-scrollbar { height: 6px; }
        .category-cards-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .category-card {
            flex: 0 0 auto;
            width: 180px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
        }
        .category-card-img {
            height: 140px;
            background: #f8fafc;
        }
        .category-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .category-card-img .no-img {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .category-card-body {
            padding: 12px 14px;
        }
        .category-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .category-card-link {
            font-size: 0.75rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }

    </style>
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'home'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-content">
        <!--
            HERO IMAGE:
            Change the $heroImage value near the top of this file to use a different image.
            Recommended: a wide landscape image (around 1920 x 800 or larger).
        -->
        <section class="custom-hero mb-4">
            <img
                src="<?= htmlspecialchars($heroImage) ?>"
                alt="Featured products"
                class="custom-hero-image"
            >
            <div class="custom-hero-overlay"></div>

            <div class="custom-hero-content">
                <div class="custom-hero-eyebrow">Fresh finds for everyday living</div>

                <h1>
                    <span class="hero-line">Everything you need,</span>
                    <span class="hero-line">all in one place.</span>
                </h1>

                <p>Discover products that make life easier, more comfortable, and more beautiful.</p>

                <div class="custom-hero-actions">
                    <a href="shop.php" class="btn btn-light">Shop Products</a>
                    <a href="about_us.php" class="btn btn-outline-light">Learn More</a>
                </div>
            </div>
        </section>

        <!-- Features/Perks Bar -->
        <section class="features-bar py-4 py-md-5 mb-5" style="border-bottom: 1px solid rgba(0,0,0,0.05); border-top: 1px solid rgba(0,0,0,0.05);">
            <div class="row g-4 text-center text-md-start justify-content-center">
                <!-- Free Shipping -->
                <div class="col-6 col-md-3 d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-start gap-3">
                    <div class="feature-icon" style="color: #0f172a;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #0f172a; font-size: 0.95rem;">Free Shipping</h6>
                        <span class="text-muted" style="font-size: 0.8rem;">On orders over ₱2,500</span>
                    </div>
                </div>
                <!-- Secure Payments -->
                <div class="col-6 col-md-3 d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-start gap-3">
                    <div class="feature-icon" style="color: #0f172a;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #0f172a; font-size: 0.95rem;">Secure Payments</h6>
                        <span class="text-muted" style="font-size: 0.8rem;">100% secure checkout</span>
                    </div>
                </div>
                <!-- Easy Returns -->
                <div class="col-6 col-md-3 d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-start gap-3">
                    <div class="feature-icon" style="color: #0f172a;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                            <path d="M3 3v5h5"></path>
                        </svg>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #0f172a; font-size: 0.95rem;">Easy Returns</h6>
                        <span class="text-muted" style="font-size: 0.8rem;">30-day return policy</span>
                    </div>
                </div>
                <!-- 24/7 Support -->
                <div class="col-6 col-md-3 d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-start gap-3">
                    <div class="feature-icon" style="color: #0f172a;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                        </svg>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold" style="color: #0f172a; font-size: 0.95rem;">24/7 Support</h6>
                        <span class="text-muted" style="font-size: 0.8rem;">Always here to help</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <h2 class="fw-bold mb-0" style="font-size: clamp(1.2rem, 2vw, 1.5rem); color: #0f172a;">Shop by Categories</h2>
                <a href="shop.php" class="text-decoration-none fw-semibold" style="color: #64748b; font-size: 0.85rem;">View All Categories &rarr;</a>
            </div>
            <div class="category-cards-scroll">
                <?php foreach ($categoryHighlights as $highlight): ?>
                    <a href="shop.php?category=<?= (int)$highlight['category']['category_id'] ?>" class="category-card text-decoration-none">
                        <div class="category-card-img">
                            <?php if (!empty($highlight['product']['image_url'])): ?>
                                <img src="<?= htmlspecialchars($highlight['product']['image_url']) ?>" alt="<?= htmlspecialchars($highlight['category']['category_name']) ?>">
                            <?php else: ?>
                                <div class="no-img">No Image</div>
                            <?php endif; ?>
                        </div>
                        <div class="category-card-body">
                            <h3 class="category-card-title"><?= htmlspecialchars($highlight['category']['category_name']) ?></h3>
                            <div class="category-card-link">Shop Now <span>&rarr;</span></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-5">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <h2 class="fw-bold mb-0" style="font-size: clamp(1.5rem, 2vw, 2rem); color: #0f172a;">Featured Products</h2>
                <a href="shop.php" class="btn btn-outline-primary btn-sm fw-semibold">View All</a>
            </div>

            <?php if (empty($featuredProducts)): ?>
                <div class="card border-0 shadow-sm p-4 text-center text-muted">No featured products available right now.</div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-md-6 col-lg-4 col-xl-3 featured-product-item" data-category-id="<?= (int)$product['category_id'] ?>">
                            <div class="featured-product-card h-100">
                                <div class="featured-product-header">
                                    <span class="featured-product-tag"><?= htmlspecialchars($product['category_name']) ?></span>
                                </div>

                                <div class="featured-product-visual">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>">
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
                                        </a>
                                    <?php else: ?>
                                        <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>" class="d-inline-block w-100">
                                            <div class="d-flex align-items-center justify-content-center text-muted fw-semibold" style="height: 340px;">No Image</div>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="featured-add-cart btn btn-outline-primary" data-product-id="<?= (int)$product['product_id'] ?>" title="Add to cart" aria-label="Add to cart">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
                                    </button>
                                </div>

                                <div class="featured-product-footer">
                                    <div>
                                        <div class="featured-product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                        <div class="featured-product-desc"><?= htmlspecialchars($product['description'] ?? 'Beautiful essentials for everyday living.') ?></div>
                                    </div>
                                    <div>
                                        <div class="featured-product-price">&#8369;<?= number_format((float)$product['price'], 2) ?></div>
                                        <div class="featured-product-stock text-muted small">Stock: <span class="fw-semibold text-dark"><?= (int)$product['stock_quantity'] ?></span></div>
                                    </div>
                                </div>

                                <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>" class="btn btn-primary btn-sm w-100">View Product</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<!-- Toast notifications -->
<div id="cart-toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // filter logic removed since categories are now links
    });
</script>
<script>
// Toast helper
function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('cart-toast-container');
    var t = document.createElement('div');
    var icons = { success: '✓', error: '✕', info: 'ℹ' };
    t.className = 'toast-item toast-' + type;
    t.innerHTML = '<span class="toast-icon">' + (icons[type] || '✓') + '</span><span>' + message + '</span>';
    container.appendChild(t);
    setTimeout(function() { t.classList.add('toast-hiding'); setTimeout(function(){ t.remove(); }, 300); }, 3000);
}

// Make entire featured card clickable (except buttons/links)
document.querySelectorAll('.featured-product-item').forEach(function(item) {
    item.addEventListener('click', function(e) {
        if (e.target.closest('a') || e.target.closest('button')) return;
        var link = item.querySelector('a[href*="product_details.php"]');
        if (link) window.location = link.getAttribute('href');
    });
});

// Add-to-cart handler for featured cards
document.querySelectorAll('.featured-add-cart').forEach(function(btn){
    btn.addEventListener('click', async function(e){
        e.stopPropagation();
        var productId = this.dataset.productId;
        this.disabled = true;
        var orig = this.innerHTML;
        this.innerHTML = 'Adding…';
        try {
            var fd = new FormData(); fd.append('action','add'); fd.append('product_id', productId); fd.append('quantity', 1); fd.append('color','');
            var res = await fetch('cart_actions.php', { method: 'POST', body: fd });
            var data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                var badge = document.getElementById('shop-cart-badge');
                if (badge) badge.textContent = parseInt(badge.textContent || 0) + 1;
            } else {
                showToast(data.message || 'Failed to add item.', 'error');
            }
        } catch(err) {
            showToast('Network error adding to cart.', 'error');
        }
        this.innerHTML = orig; this.disabled = false;
    });
});
</script>
</body>
</html>
