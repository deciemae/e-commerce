<?php
require_once 'config/db.php';
require_once 'includes/product_colors.php';
session_start();

function renderStars(float $rating): string
{
    $filled = (int)round($rating);
    $html = '<span class="star-rating" aria-label="' . htmlspecialchars(number_format($rating, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star ' . ($i <= $filled ? 'filled' : '') . '">★</span>';
    }
    $html .= '</span>';

    return $html;
}

function renderStarInput(string $name = 'rating'): string
{
    $html = '<div class="star-input-group">';
    for ($i = 5; $i >= 1; $i--) {
        $html .= '<input type="radio" id="' . $name . '_' . $i . '" name="' . $name . '" value="' . $i . '" required>';
        $html .= '<label for="' . $name . '_' . $i . '" title="' . $i . ' star' . ($i > 1 ? 's' : '') . '">★</label>';
    }
    $html .= '</div>';

    return $html;
}

// Color helper (small subset used by product listing pages)
function colorToCSS(string $name): string
{
    $map = [
        'red' => '#ef4444', 'blue' => '#3b82f6', 'black' => '#111827',
        'white' => '#ffffff', 'green' => '#22c55e', 'yellow' => '#eab308',
        'purple' => '#8b5cf6', 'pink' => '#ec4899', 'orange' => '#f97316',
        'gray' => '#9ca3af', 'grey' => '#9ca3af', 'brown' => '#92400e',
        'gold' => '#f59e0b', 'silver' => '#cbd5e1'
    ];
    return $map[strtolower(trim($name))] ?? '#9ca3af';
}

function getProductDetails(mysqli $conn, int $productId): ?array
{
    $stmt = $conn->prepare(
        'SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity, p.image_url,
                c.category_name,
                (
                    SELECT COALESCE(AVG(r.rating), 0)
                    FROM product_reviews r
                    WHERE r.product_id = p.product_id
                ) AS average_rating,
                (
                    SELECT COUNT(*)
                    FROM product_reviews r
                    WHERE r.product_id = p.product_id
                ) AS review_count
         FROM products p
         INNER JOIN categories c ON p.category_id = c.category_id
         WHERE p.product_id = ?'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $product ?: null;
}

function fetchReviews(mysqli $conn, int $productId): array
{
    $stmt = $conn->prepare(
        'SELECT review_id, rating, review_text, created_at
         FROM product_reviews
         WHERE product_id = ?
         ORDER BY created_at DESC, review_id DESC'
    );
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $reviews;
}

$success = '';
$error = '';

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$productId = (int)($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    header('Location: shop.php');
    exit;
}

$conn = getConnection();
$product = getProductDetails($conn, $productId);

if (!$product) {
    $conn->close();
    $error = 'Product not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $rating = (int)($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5 stars.';
    } elseif ($reviewText === '') {
        $error = 'Please write a review before submitting.';
    } else {
        $stmt = $conn->prepare('INSERT INTO product_reviews (product_id, rating, review_text) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $productId, $rating, $reviewText);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            $_SESSION['flash_success'] = 'Your review has been submitted.';
            header('Location: product_details.php?product_id=' . $productId);
            exit;
        }

        $error = 'Failed to submit review: ' . $stmt->error;
        $stmt->close();
    }
}

$reviews = $product ? fetchReviews($conn, $productId) : [];
$colors = $product ? fetchProductColors($conn, $productId) : [];
$conn->close();
$activePage = 'shop';

$isOutOfStock = $product ? ((int)$product['stock_quantity'] <= 0) : true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'shop'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <!-- <div class="product-page-header">
        <h1>Product Details</h1>
        <a href="shop.php" class="btn btn-outline-primary btn-sm fw-semibold">Back to Shop</a>
    </div> -->

    <div class="page-content">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
            <div class="product-detail-layout mb-4">
                <div class="product-detail-media card">
                    <div class="card-body">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" class="product-detail-image">
                        <?php else: ?>
                            <div class="product-detail-placeholder">No image available</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="product-detail-summary card">
                    <div class="card-body p-4">
                        <div class="cat-badge mb-3"><?= htmlspecialchars($product['category_name']) ?></div>
                        <h2 class="product-detail-title"><?= htmlspecialchars($product['product_name']) ?></h2>
                        <div class="product-detail-price mb-2">&#8369;<?= number_format((float)$product['price'], 2) ?></div>
                        <div class="product-detail-stock mb-2">
                            <?php if ($isOutOfStock): ?>
                                <span class="text-danger fw-bold">Out of Stock</span>
                            <?php else: ?>
                                Stock: <span class="fw-semibold text-dark"><?= (int)$product['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-detail-rating mb-3">
                            <?= renderStars((float)$product['average_rating']) ?>
                            <span class="text-muted small ms-2"><?= number_format((float)$product['average_rating'], 1) ?> / 5 from <?= (int)$product['review_count'] ?> review<?= (int)$product['review_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <p class="text-muted product-detail-description"><?= nl2br(htmlspecialchars($product['description'] ?? 'No description provided.')) ?></p>
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

                        <div class="mt-3 d-flex align-items-center gap-2">
                            <label class="visually-hidden" for="add-qty-<?= (int)$product['product_id'] ?>">Quantity</label>
                            <input id="add-qty-<?= (int)$product['product_id'] ?>" type="number" class="form-control form-control-sm" value="1" min="1" max="<?= (int)$product['stock_quantity'] ?>" style="width:80px; text-align:center;">
                            <button type="button" class="btn btn-outline-primary btn-add-cart d-inline-flex align-items-center gap-2" onclick="addToCart(<?= (int)$product['product_id'] ?>, this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
                                <span>Add to Cart</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header-custom">Write a Review</div>
                        <div class="card-body p-4">
                            <form method="POST" action="product_details.php?product_id=<?= (int)$product['product_id'] ?>">
                                <div class="mb-3">
                                    <label class="form-label">Rating</label>
                                    <?= renderStarInput('rating') ?>
                                </div>
                                <div class="mb-3">
                                    <label for="review_text" class="form-label">Review</label>
                                    <textarea id="review_text" name="review_text" class="form-control" rows="5" maxlength="1000" placeholder="Share your experience with this product." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header-custom">Customer Reviews</div>
                        <div class="card-body p-4">
                            <?php if (empty($reviews)): ?>
                                <div class="empty-state py-4">No reviews yet. Be the first to leave feedback.</div>
                            <?php else: ?>
                                <div class="review-list">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="review-item">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                                                <div><?= renderStars((float)$review['rating']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($review['created_at']) ?></small>
                                            </div>
                                            <p class="mb-0 review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body p-4">
                    <div class="empty-state mb-0">The requested product could not be found.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<div id="cart-toast-container"></div>
<script>
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

// Initialize color swatches selection (if present)
document.querySelectorAll('.color-swatches').forEach(function(container) {
    container.querySelectorAll('.color-swatch').forEach(function(swatch) {
        swatch.addEventListener('click', function() {
            var already = container.dataset.selected === this.dataset.color;
            container.querySelectorAll('.color-swatch').forEach(function(s) { s.classList.remove('selected'); });
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

function getSelectedColor() {
    var sw = document.querySelector('.color-swatches');
    return sw ? (sw.dataset.selected || '') : '';
}

async function addToCart(productId, btn) {
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
        fd.append('color', getSelectedColor());

        var res = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            btn.innerHTML = '✓ Added!';
            var badge = document.getElementById('shop-cart-badge');
            if (badge) {
                badge.textContent = parseInt(badge.textContent || 0) + qty;
            }
            setTimeout(function() { btn.innerHTML = origHTML; btn.disabled = false; }, 1200);
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
