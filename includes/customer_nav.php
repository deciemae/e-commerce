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
        <a href="index.php" class="customer-nav-logo" aria-label="Bloom &amp; Basket">
            <img src="assets/logo.png" alt="Bloom &amp; Basket" class="customer-nav-logo-img" width="46" height="34">
        </a>
    </div>

    <button
        type="button"
        class="customer-nav-toggle"
        aria-controls="customer-navigation"
        aria-expanded="false"
        aria-label="Open navigation menu"
        data-nav-toggle
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="customer-nav-right" id="customer-navigation" data-nav-panel>
        <nav class="customer-nav-links" aria-label="Primary navigation">
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
            <a href="cart.php" class="nav-icon-link customer-nav-cart <?= $customerActivePage === 'cart' ? 'active' : '' ?>" aria-label="Cart" id="customer-nav-cart-btn">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 9.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.75L19 7H6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="10" cy="18" r="1.5" fill="currentColor"/>
                    <circle cx="17" cy="18" r="1.5" fill="currentColor"/>
                </svg>
                <span class="customer-nav-cart-count" id="customer-nav-cart-count" style="<?= $navCartCount > 0 ? '' : 'display:none;' ?>">
                    <span class="cart-count-number"><?= $navCartCount > 99 ? '99+' : $navCartCount ?></span>
                    <span class="visually-hidden">items in cart</span>
                </span>
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
                        <form method="post" action="customer_logout.php" class="customer-logout-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(customerCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit">Logout</button>
                        </form>
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
    const navToggle = document.querySelector('[data-nav-toggle]');
    const navPanel = document.querySelector('[data-nav-panel]');

    function closeNavigation() {
        if (!navToggle || !navPanel) return;
        navPanel.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Open navigation menu');
    }

    if (navToggle && navPanel) {
        navToggle.addEventListener('click', function () {
            const isOpen = navPanel.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', String(isOpen));
            navToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        });

        navPanel.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeNavigation);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNavigation();
                navToggle.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 991) closeNavigation();
        });
    }

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

/**
 * Real-time Cart Utilities
 */
window.updateNavCartCount = function (totalQty) {
    const qty = parseInt(totalQty, 10);
    const countBadge = document.getElementById('customer-nav-cart-count');
    const navCartBtn = document.getElementById('customer-nav-cart-btn');

    if (countBadge) {
        const numSpan = countBadge.querySelector('.cart-count-number') || countBadge;
        if (!isNaN(qty) && qty > 0) {
            numSpan.textContent = qty > 99 ? '99+' : qty;
            countBadge.style.display = '';
        } else {
            numSpan.textContent = '0';
            countBadge.style.display = 'none';
        }

        // Trigger bump animation on count badge
        countBadge.classList.remove('cart-bump');
        void countBadge.offsetWidth;
        countBadge.classList.add('cart-bump');
        setTimeout(function () {
            countBadge.classList.remove('cart-bump');
        }, 700);
    }

    if (navCartBtn) {
        navCartBtn.classList.remove('cart-bump');
        void navCartBtn.offsetWidth;
        navCartBtn.classList.add('cart-bump');
        setTimeout(function () {
            navCartBtn.classList.remove('cart-bump');
        }, 700);
    }

    // Sync any secondary catalog badge (e.g. #shop-cart-badge)
    const secondaryBadge = document.getElementById('shop-cart-badge');
    if (secondaryBadge && !isNaN(qty)) {
        secondaryBadge.textContent = qty;
    }
};

window.animateFlyToCart = function (sourceElement, clickPos) {
    try {
        if (!sourceElement && !clickPos) return;

        // Check prefers-reduced-motion
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const target = document.getElementById('customer-nav-cart-btn') || document.querySelector('.customer-nav-cart');
        if (!target) return;

        const targetRect = target.getBoundingClientRect();

        // Determine visual flyer element (product image or styled bag icon)
        let flyer;
        let imgSrc = '';
        if (sourceElement) {
            if (sourceElement.tagName && sourceElement.tagName.toLowerCase() === 'img') {
                imgSrc = sourceElement.src;
            } else if (sourceElement.querySelector) {
                const foundImg = sourceElement.querySelector('img');
                if (foundImg && foundImg.src) {
                    imgSrc = foundImg.src;
                }
            }
        }

        if (imgSrc) {
            flyer = document.createElement('img');
            flyer.src = imgSrc;
            flyer.alt = '';
        } else {
            flyer = document.createElement('div');
            flyer.innerHTML = '<span style="font-size:26px;line-height:1;">🛍️</span>';
            flyer.style.display = 'grid';
            flyer.style.placeItems = 'center';
        }

        flyer.className = 'cart-flying-clone';
        const startSize = 64;
        flyer.style.width = startSize + 'px';
        flyer.style.height = startSize + 'px';

        // Start coordinates: start from clicked point if available, else element rect
        let startX, startY;
        if (clickPos && typeof clickPos.clientX === 'number') {
            startX = clickPos.clientX - (startSize / 2);
            startY = clickPos.clientY - (startSize / 2);
        } else if (sourceElement && typeof sourceElement.getBoundingClientRect === 'function') {
            const rect = sourceElement.getBoundingClientRect();
            startX = rect.left + (rect.width / 2) - (startSize / 2);
            startY = rect.top + (rect.height / 2) - (startSize / 2);
        } else {
            startX = (window.innerWidth / 2) - (startSize / 2);
            startY = (window.innerHeight / 2) - (startSize / 2);
        }

        flyer.style.left = startX + 'px';
        flyer.style.top = startY + 'px';
        flyer.style.transform = 'translate3d(0, 0, 0) scale(1) rotate(0deg)';

        document.body.appendChild(flyer);

        const targetX = targetRect.left + (targetRect.width / 2) - (startSize / 2);
        const targetY = targetRect.top + (targetRect.height / 2) - (startSize / 2);
        const deltaX = targetX - startX;
        const deltaY = targetY - startY;

        // Animate towards basket with arc trajectory
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                flyer.classList.add('is-flying');
                flyer.style.transform = 'translate3d(' + deltaX + 'px, ' + deltaY + 'px, 0) scale(0.18) rotate(22deg)';
            });
        });

        setTimeout(function () {
            if (flyer && flyer.parentNode) {
                flyer.remove();
            }
            if (target) {
                target.classList.remove('cart-bump');
                void target.offsetWidth;
                target.classList.add('cart-bump');
                setTimeout(function () {
                    target.classList.remove('cart-bump');
                }, 700);
            }
        }, 750);
    } catch (err) {
        console.error('Error during fly-to-cart animation:', err);
    }
};
</script>
