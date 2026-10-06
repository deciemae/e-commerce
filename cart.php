<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/product_colors.php';

$conn    = getConnection();
$cart_id = getOrCreateCart($conn);

// ── Fetch cart items ──────────────────────────────────────────
$s = $conn->prepare(
    'SELECT ci.cart_item_id, ci.product_id, ci.quantity, ci.color,
            p.product_name, c.category_name, p.price, p.image_url,
            p.stock_quantity
     FROM   cart_items ci
     INNER JOIN products    p ON ci.product_id  = p.product_id
     INNER JOIN categories  c ON p.category_id  = c.category_id
     WHERE  ci.cart_id = ?
     ORDER  BY ci.added_at ASC'
);
$s->bind_param('i', $cart_id);
$s->execute();
$cart_items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s->close();

$productColorMap = fetchProductColorsForIds($conn, array_column($cart_items, 'product_id'));

$conn->close();

$total_qty   = array_sum(array_column($cart_items, 'quantity'));
$grand_total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart_items));
$activePage  = 'cart';

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
    <title>Customer Shopping Cart — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
    <link rel="stylesheet" href="assets/cart.css">
</head>
<body class="customer-ui customer-page cart-page">

<?php $customerActivePage = 'cart'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content">

    <!-- ── Topbar ─────────────────────────────────────────── -->
    <header class="page-topbar cart-page-heading d-flex align-items-center justify-content-between">
        <div>
            <p class="cart-page-eyebrow">Your bag</p>
            <h1>Shopping Cart</h1>
            <p class="cart-page-intro">Review your products and choose which items to take to checkout.</p>
        </div>
        <div class="cart-page-heading-actions d-flex align-items-center gap-3">
            <a href="shop.php" class="cart-continue-link">
                &larr; Continue Shopping
            </a>
            <span class="topbar-cart-badge" id="topbar-cart-count">
                <span class="badge-icon"><?= renderCartIcon('cart-icon') ?></span>
                <span id="topbar-qty"><?= $total_qty ?></span> items
            </span>
        </div>
    </header>

    <div class="page-content">

        <div class="row g-4 align-items-start">

            <!-- ── Left: Cart Table ────────────────────────── -->
            <div class="col-xl-8">

                <section class="card cart-items-panel" id="cart-section" aria-labelledby="cart-items-title">
                    <div class="card-header-custom d-flex align-items-center justify-content-between">
                        <span id="cart-items-title">Selected items (<span id="cart-row-count-badge"><?= count($cart_items) ?></span>)</span>
                        <button type="button" class="btn-clear-all" id="btn-clear-all"
                                onclick="clearCart()"
                                style="<?= empty($cart_items) ? 'display:none' : '' ?>">
                            Clear cart
                        </button>
                    </div>
                    <div class="card-body p-0">

                        <!-- Empty state -->
                        <div id="cart-empty-state" class="cart-empty-state"
                             style="<?= !empty($cart_items) ? 'display:none' : '' ?>">
                            <div class="empty-icon"><?= renderCartIcon('cart-icon') ?></div>
                            <h2 class="empty-title">Your cart is ready for something new</h2>
                            <p class="empty-sub">Explore the catalog and add products you would like to purchase.</p>
                            <a href="shop.php" class="cart-empty-action">Shop Products</a>
                        </div>

                        <!-- Cart table -->
                        <div id="cart-table-wrap"
                             style="<?= empty($cart_items) ? 'display:none' : '' ?>">
                            <div class="table-responsive">
                                <table class="table mb-0 cart-items-table" id="cart-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40px; text-align: center;"><input type="checkbox" class="form-check-input" id="select-all-cb" checked aria-label="Select all cart items" onchange="toggleAllCheckboxes(this)"></th>
                                            <th style="width:64px">Image</th>
                                            <th>Product Name</th>
                                            <th>Unit Price</th>
                                            <th style="width:120px">Color</th>
                                            <th style="width:148px">Quantity</th>
                                            <th>Subtotal</th>
                                            <th style="width:44px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cart-tbody">
                                        <?php foreach ($cart_items as $item):
                                            $subtotal = $item['price'] * $item['quantity'];
                                            $item_colors = $productColorMap[(int)$item['product_id']] ?? [];
                                        ?>
                                        <tr class="cart-row"
                                            id="cart-row-<?= $item['cart_item_id'] ?>"
                                            data-cart-item-id="<?= $item['cart_item_id'] ?>"
                                            data-price="<?= $item['price'] ?>">

                                            <td style="text-align: center;">
                                                <input type="checkbox" class="form-check-input item-select-cb" value="<?= $item['cart_item_id'] ?>" name="selected_items[]" checked aria-label="Select <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?> for checkout" onchange="recalcTotals()">
                                            </td>

                                            <td>
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>"
                                                         class="product-thumb" alt="<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>">
                                                <?php else: ?>
                                                    <div class="thumb-placeholder" aria-hidden="true"><?= renderCartIcon('cart-icon') ?></div>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div class="cart-product-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <span class="cat-badge"><?= htmlspecialchars($item['category_name']) ?></span>
                                            </td>

                                            <td class="unit-price-cell">&#8369;<?= number_format($item['price'], 2) ?></td>

                                            <td>
                                                <?php if (!empty($item_colors)): ?>
                                                <select class="form-select form-select-sm cart-color-select"
                                                        aria-label="Color for <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>"
                                                        onchange="updateCartItem(<?= $item['cart_item_id'] ?>, null, this.value)">
                                                    <option value="">Standard</option>
                                                    <?php foreach ($item_colors as $clr): ?>
                                                    <option value="<?= htmlspecialchars($clr) ?>"
                                                            <?= ($item['color'] === $clr) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($clr) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php else: ?>
                                                <span class="no-color-label">N/A</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div class="cart-qty-control">
                                                    <button type="button" class="qty-btn" aria-label="Decrease quantity for <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>" onclick="changeQty(<?= $item['cart_item_id'] ?>, -1)">−</button>
                                                    <input  type="number"
                                                            class="qty-input"
                                                            id="qty-<?= $item['cart_item_id'] ?>"
                                                            value="<?= $item['quantity'] ?>"
                                                            min="1"
                                                            max="<?= max(1, (int)$item['stock_quantity']) ?>"
                                                            aria-label="Quantity for <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>"
                                                            onchange="updateCartItem(<?= $item['cart_item_id'] ?>, parseInt(this.value) || 1, null)">
                                                    <button type="button" class="qty-btn" aria-label="Increase quantity for <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>" onclick="changeQty(<?= $item['cart_item_id'] ?>, 1)">+</button>
                                                </div>
                                            </td>

                                            <td class="item-subtotal">&#8369;<?= number_format($subtotal, 2) ?></td>

                                            <td>
                                                <button type="button" class="btn-remove-item"
                                                        onclick="removeCartItem(<?= $item['cart_item_id'] ?>)"
                                                        aria-label="Remove <?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?> from cart">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

            </div><!-- /col-xl-8 -->

            <!-- ── Right: Order Summary ─────────────────────── -->
            <div class="col-xl-4">
                <aside class="cart-summary-panel" aria-labelledby="cart-summary-title">
                    <div class="summary-header">
                        <h2 class="summary-title" id="cart-summary-title">Order Summary</h2>
                        <!-- <span class="summary-icon">🧾</span> -->
                    </div>

                    <div class="summary-lines">
                        <div class="summary-line">
                            <span class="summary-line-label">Selected products</span>
                            <span class="summary-line-value" id="distinct-items-count"><?= count($cart_items) ?> line item<?= count($cart_items) !== 1 ? 's' : '' ?></span>
                        </div>
                        <div class="summary-line">
                            <span class="summary-line-label">Total quantity</span>
                            <span class="summary-line-value" id="total-items-count"><?= $total_qty ?> item<?= $total_qty !== 1 ? 's' : '' ?></span>
                        </div>
                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-grand-total">
                        <span>Grand Total</span>
                        <span id="grand-total-amount" aria-live="polite">&#8369;<?= number_format($grand_total, 2) ?></span>
                    </div>

                    <button type="button" class="btn-checkout" id="checkout-btn" onclick="submitCheckout()" <?= empty($cart_items) ? 'disabled' : '' ?>>
                        Proceed to Checkout
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h5.793L8.146 5.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 8.5H4.5A.5.5 0 0 1 4 8z"/></svg>
                    </button>
                    <p class="cart-summary-note">Final order details are reviewed before payment.</p>

                </aside>
            </div><!-- /col-xl-4 -->

        </div><!-- /row -->
    </div><!-- /page-content -->

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</main><!-- /main-content -->

<!-- Toast container -->
<div id="cart-toast-container" aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

/* ── Recalculate totals from DOM ─────────────────────────────── */
function recalcTotals() {
    var totalQty   = 0;
    var grandTotal = 0;
    var rowCount   = 0;
    var cartRows   = Array.from(document.querySelectorAll('.cart-row'));

    cartRows.forEach(function(row) {
        var cb       = row.querySelector('.item-select-cb');
        var qty      = parseInt(row.querySelector('.qty-input').value) || 0;
        var price    = parseFloat(row.dataset.price) || 0;
        var subtotal = qty * price;
        row.querySelector('.item-subtotal').textContent =
            '₱' + subtotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
        if (cb && cb.checked) {
            totalQty   += qty;
            grandTotal += subtotal;
            rowCount++;
        }
    });

    document.getElementById('total-items-count').textContent   = totalQty + ' item' + (totalQty !== 1 ? 's' : '');
    document.getElementById('distinct-items-count').textContent = rowCount + ' line item' + (rowCount !== 1 ? 's' : '');
    document.getElementById('grand-total-amount').textContent  =
        '₱' + grandTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    var badgeCount = document.getElementById('cart-row-count-badge');
    if (badgeCount) badgeCount.textContent = rowCount;

    // Topbar
    var tb = document.getElementById('topbar-qty');
    if (tb) tb.textContent = totalQty;

    var masterCb = document.getElementById('select-all-cb');
    if (masterCb) {
        masterCb.checked = cartRows.length > 0 && rowCount === cartRows.length;
        masterCb.indeterminate = rowCount > 0 && rowCount < cartRows.length;
    }

    var checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) checkoutBtn.disabled = rowCount === 0;

    // Empty state toggle
    var emptyState   = document.getElementById('cart-empty-state');
    var tableWrap    = document.getElementById('cart-table-wrap');
    var clearAllBtn  = document.getElementById('btn-clear-all');
    var summClearBtn = document.getElementById('summary-clear-btn');

    if (cartRows.length === 0) {
        emptyState.style.display   = '';
        tableWrap.style.display    = 'none';
        if (clearAllBtn)  clearAllBtn.style.display  = 'none';
        if (summClearBtn) summClearBtn.style.display = 'none';
    } else {
        emptyState.style.display   = 'none';
        tableWrap.style.display    = '';
        if (clearAllBtn)  clearAllBtn.style.display  = '';
        if (summClearBtn) summClearBtn.style.display = '';
    }
}

/* ── Update cart item (qty or color) ─────────────────────────── */
async function updateCartItem(cartItemId, qty, color) {
    var row = document.getElementById('cart-row-' + cartItemId);
    if (!row) return;

    var currentQty = qty !== null && qty !== undefined
        ? qty
        : parseInt(row.querySelector('.qty-input').value) || 1;

    var colorSel = row.querySelector('.cart-color-select');
    var currentColor = (color !== null && color !== undefined)
        ? color
        : (colorSel ? colorSel.value : '');

    if (currentQty < 1) {
        await removeCartItem(cartItemId);
        return;
    }

    try {
        var fd = new FormData();
        fd.append('action',       'update');
        fd.append('cart_item_id', cartItemId);
        fd.append('quantity',     currentQty);
        fd.append('color',        currentColor);

        var res  = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            if (qty !== null && qty !== undefined) {
                row.querySelector('.qty-input').value = currentQty;
            }
            recalcTotals();
            showToast('Cart item updated.', 'success');
        } else {
            showToast(data.message || 'Update failed.', 'error');
        }
    } catch (e) {
        showToast('A network error occurred.', 'error');
    }
}

/* ── Change qty via +/− buttons ──────────────────────────────── */
function changeQty(cartItemId, delta) {
    var input  = document.getElementById('qty-' + cartItemId);
    var newQty = Math.max(1, (parseInt(input.value) || 1) + delta);
    input.value = newQty;
    updateCartItem(cartItemId, newQty, null);
}

/* ── Remove item ─────────────────────────────────────────────── */
async function removeCartItem(cartItemId) {
    try {
        var fd = new FormData();
        fd.append('action',       'remove');
        fd.append('cart_item_id', cartItemId);

        var res  = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            var row = document.getElementById('cart-row-' + cartItemId);
            if (row) {
                row.style.transition = 'opacity .3s, transform .3s';
                row.style.opacity    = '0';
                row.style.transform  = 'translateX(30px)';
                setTimeout(function() {
                    row.remove();
                    recalcTotals();
                }, 300);
            }
            showToast('Item removed from cart.', 'success');
        } else {
            showToast(data.message || 'Remove failed.', 'error');
        }
    } catch (e) {
        showToast('A network error occurred.', 'error');
    }
}

/* ── Clear cart ──────────────────────────────────────────────── */
function toggleAllCheckboxes(masterCb) {
    document.querySelectorAll('.item-select-cb').forEach(function(cb) {
        cb.checked = masterCb.checked;
    });
    recalcTotals();
}

function submitCheckout() {
    var selectedIds = [];
    document.querySelectorAll('.item-select-cb:checked').forEach(function(cb) {
        selectedIds.push(cb.value);
    });
    
    if (selectedIds.length === 0) {
        showToast('Please select at least one item to checkout.', 'error');
        return;
    }
    
    window.location.href = 'checkout.php?items=' + selectedIds.join(',');
}

async function clearCart() {
    if (!confirm('Are you sure you want to remove all items from your cart?')) return;
    try {
        var fd = new FormData();
        fd.append('action', 'clear');

        var res  = await fetch('cart_actions.php', { method: 'POST', body: fd });
        var data = await res.json();

        if (data.success) {
            document.querySelectorAll('.cart-row').forEach(function(row) {
                row.remove();
            });
            recalcTotals();
            showToast('Cart cleared successfully.', 'success');
        } else {
            showToast(data.message || 'Clear failed.', 'error');
        }
    } catch (e) {
        showToast('A network error occurred.', 'error');
    }
}
</script>
</body>
</html>
