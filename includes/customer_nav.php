<?php
$customerActivePage = $customerActivePage ?? 'shop';
$customerIsLoggedIn = function_exists('currentCustomerId') ? (currentCustomerId() !== null) : false;

$navCartCount = 0;
if (function_exists('getConnection') && function_exists('getOrCreateCart')) {
    $navConn = getConnection();
    $navCartId = getOrCreateCart($navConn);
    
    $navStmt = $navConn->prepare('SELECT SUM(quantity) as total_qty FROM cart_items WHERE cart_id = ?');
    if ($navStmt) {
        $navStmt->bind_param('i', $navCartId);
        $navStmt->execute();
        $navRes = $navStmt->get_result()->fetch_assoc();
        $navCartCount = (int)($navRes['total_qty'] ?? 0);
        $navStmt->close();
    }
    $navConn->close();
}
?>
<header class="customer-nav">
    <div class="customer-nav-brand">
        <a href="index.php" class="customer-nav-logo">Bloom &amp; Basket</a>
    </div>

    <div class="customer-nav-right">
        <nav class="customer-nav-links">
            <a href="index.php" class="<?= $customerActivePage === 'home' ? 'active' : '' ?>">Home</a>
            <a href="shop.php" class="<?= $customerActivePage === 'shop' ? 'active' : '' ?>">Shop</a>
            <a href="about_us.php" class="<?= $customerActivePage === 'about' ? 'active' : '' ?>">About Us</a>
        </nav>

        <div class="customer-nav-icons" aria-label="Customer actions">
            <form class="customer-nav-search" action="shop.php" method="get" role="search" data-nav-search>
                <label class="visually-hidden" for="customer-nav-search-input">Search products</label>
                <input
                    id="customer-nav-search-input"
                    type="search"
                    name="q"
                    value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>"
                    class="customer-nav-search-input"
                    placeholder="Search products..."
                    autocomplete="off"
                    aria-label="Search products"
                >
            </form>
            <a href="cart.php" class="nav-icon-link <?= $customerActivePage === 'cart' ? 'active' : '' ?>" aria-label="Cart" style="position: relative;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 9.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.75L19 7H6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="10" cy="18" r="1.5" fill="currentColor"/>
                    <circle cx="17" cy="18" r="1.5" fill="currentColor"/>
                </svg>
                <?php if ($navCartCount > 0): ?>
                    <span class="position-absolute translate-middle badge bg-danger" style="top: 5px; left: 85%; font-size: 0.7rem; padding: 0; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 700;">
                        <?= $navCartCount > 99 ? '99+' : $navCartCount ?>
                        <span class="visually-hidden">items in cart</span>
                    </span>
                <?php endif; ?>
            </a>

            <div class="profile-menu-wrapper">
                <a href="<?= $customerIsLoggedIn ? 'customer_dashboard.php' : 'customer_login.php' ?>" class="nav-icon-link profile-trigger" aria-label="Profile">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="12" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </a>
                <div class="profile-dropdown">
                    <?php if ($customerIsLoggedIn): ?>
                        <a href="customer_dashboard.php">Dashboard</a>
                        <a href="customer_profile.php">Profile</a>
                        <a href="customer_addresses.php">Addresses</a>
                        <!-- <a href="cart.php">Shopping Cart</a> -->
                        <a href="customer_orders.php">Orders</a>
                        <a href="customer_logout.php">Logout</a>
                    <?php else: ?>
                        <a href="customer_login.php">Log In</a>
                        <a href="customer_register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
(function () {
    const searchForm = document.querySelector('[data-nav-search]');
    const searchInput = document.getElementById('customer-nav-search-input');

    if (!searchForm || !searchInput) {
        return;
    }

    let searchTimer = null;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);

        const query = this.value.trim();

        searchTimer = setTimeout(function () {
            if (query === '') {
                window.location.href = 'shop.php';
                return;
            }

            const params = new URLSearchParams({ q: query });
            window.location.href = 'shop.php?' + params.toString();
        }, 350);
    });

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(searchTimer);

        const query = searchInput.value.trim();
        const targetUrl = query === '' ? 'shop.php' : 'shop.php?q=' + encodeURIComponent(query);
        window.location.href = targetUrl;
    });
})();
</script>
