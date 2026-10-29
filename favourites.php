<?php

require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/customer_system.php';
require_once __DIR__ . '/includes/customer_favourites.php';

$customerId = currentCustomerId();
$favouriteProducts = [];

if ($customerId) {
    $conn = getConnection();
    $favouriteProducts = customerFavouriteProducts($conn, $customerId);
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favourites &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<?php if (!$customerId): ?>
<body class="customer-ui favourites-auth-page">
    <main class="favourites-auth-shell">
        <section class="favourites-auth-panel" aria-labelledby="favourites-auth-title">
            <a href="index.php" class="favourites-auth-brand" aria-label="Bloom and Basket home">
                <img src="assets/logo.png" alt="" width="54" height="40">
                <span>Bloom &amp; Basket</span>
            </a>
            <div class="favourites-auth-copy">
                <h1 id="favourites-auth-title">Save your favourites</h1>
                <p>Sign in or create an account to keep products saved across your devices.</p>
            </div>
            <div class="favourites-auth-actions">
                <a href="customer_login.php?return_to=favourites.php" class="favourites-auth-primary">Sign in</a>
                <a href="customer_register.php?return_to=favourites.php" class="favourites-auth-secondary">Create account</a>
            </div>
            <a href="shop.php" class="favourites-auth-back">Continue shopping</a>
        </section>
    </main>
</body>
<?php else: ?>
<body class="customer-ui customer-page favourites-page">
    <?php $customerActivePage = 'favourites'; require __DIR__ . '/includes/customer_nav.php'; ?>

    <main id="main-content">
        <div class="page-content favourites-content">
            <header class="favourites-heading">
                <h1>Favourites</h1>
                <p><?= count($favouriteProducts) ?> <?= count($favouriteProducts) === 1 ? 'product' : 'products' ?></p>
            </header>

            <?php if (!$favouriteProducts): ?>
                <section class="favourites-empty" aria-labelledby="favourites-empty-title">
                    <h2 id="favourites-empty-title">No favourites yet</h2>
                    <p>Use the heart on a product to save it here.</p>
                    <a href="shop.php">Shop products</a>
                </section>
            <?php else: ?>
                <section class="catalog-surface" aria-label="Saved products">
                    <div class="row g-3 product-grid" data-favourites-grid>
                        <?php foreach ($favouriteProducts as $product): ?>
                            <?php $isOutOfStock = (int)$product['stock_quantity'] <= 0; ?>
                            <div class="col-12 col-sm-6 col-lg-4" data-favourite-card>
                                <article class="product-catalog-card <?= $isOutOfStock ? 'opacity-75' : '' ?>">
                                    <div class="product-img-wrap">
                                        <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php else: ?>
                                                <div class="no-img-placeholder">No image</div>
                                            <?php endif; ?>
                                        </a>
                                        <?php renderFavouriteButton((int)$product['product_id'], true, true, 'favourite-toggle-card'); ?>
                                    </div>
                                    <div class="product-info">
                                        <div class="catalog-product-name"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <span class="cat-badge"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <div class="catalog-price-stock">
                                            <div class="catalog-price">&#8369;<?= number_format((float)$product['price'], 2) ?></div>
                                            <div class="catalog-stock"><?= $isOutOfStock ? 'Out of stock' : 'In stock' ?></div>
                                        </div>
                                        <a href="product_details.php?product_id=<?= (int)$product['product_id'] ?>" class="favourite-view-product">View product</a>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/includes/customer_footer.php'; ?>
</body>
<?php endif; ?>
</html>
