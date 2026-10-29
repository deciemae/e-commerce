<?php

$root = dirname(__DIR__);
$failures = [];

function storefrontCatalogCheck(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$shop = file_get_contents($root . '/shop.php');
$browse = file_get_contents($root . '/browse.php');
$customerNavigation = file_get_contents($root . '/includes/customer_nav.php');
$stylesheet = file_get_contents($root . '/assets/customer.css');
$cartStylesheet = file_get_contents($root . '/assets/cart.css');

storefrontCatalogCheck(str_contains((string)$shop, 'class="catalog-layout"'), 'The catalog needs a dedicated filter-and-results layout.');
storefrontCatalogCheck(str_contains((string)$shop, 'class="catalog-filter-panel"'), 'The category navigation must render in the catalog filter panel.');
storefrontCatalogCheck(str_contains((string)$shop, 'aria-label="Filter products by category"'), 'The category panel must retain an accessible label.');
storefrontCatalogCheck(str_contains((string)$shop, 'aria-current="page"'), 'The selected category must be exposed to assistive technology.');
storefrontCatalogCheck(!str_contains((string)$shop, 'Explore the shop'), 'The category rail must not include decorative eyebrow text.');
storefrontCatalogCheck(!str_contains((string)$shop, 'Browse categories'), 'The category rail must not repeat an unnecessary heading.');
storefrontCatalogCheck(!str_contains((string)$shop, 'Bloom &amp; Basket catalog'), 'The catalog heading must not repeat decorative brand copy.');
storefrontCatalogCheck(!str_contains((string)$shop, 'Browse everyday essentials'), 'The catalog heading must not include redundant helper text.');
storefrontCatalogCheck(!str_contains((string)$shop, 'catalog-results-bar'), 'The product grid must not include a duplicate results heading.');
storefrontCatalogCheck(!str_contains((string)$shop, 'catalog-product-description'), 'Shop product cards must not render catalog descriptions.');
storefrontCatalogCheck(!str_contains((string)$browse, 'catalog-product-description'), 'Browse product cards must not render catalog descriptions.');
storefrontCatalogCheck(!str_contains((string)$browse, 'Bloom &amp; Basket catalog'), 'Browse must not repeat decorative brand copy.');
storefrontCatalogCheck(!str_contains((string)$browse, 'Discover products, compare options'), 'Browse must not include generic explanatory copy.');
storefrontCatalogCheck(!str_contains((string)$browse, 'position-absolute top-0 start-0'), 'Category metadata must not cover catalog photography.');
storefrontCatalogCheck(!str_contains((string)$shop, 'class="page-topbar'), 'Shop must not render the hidden duplicate catalog toolbar.');
storefrontCatalogCheck(!str_contains((string)$browse, 'class="page-topbar'), 'Browse must not render the hidden duplicate catalog toolbar.');
storefrontCatalogCheck((bool)preg_match('/\.catalog-layout\s*\{[\s\S]*?grid-template-columns:\s*minmax\(12rem, 14rem\) minmax\(0, 1fr\);/', (string)$stylesheet), 'Desktop catalog layout must reserve a left filter rail.');
storefrontCatalogCheck((bool)preg_match('/\.catalog-filter-panel\s*\{[\s\S]*?background:\s*var\(--color-canvas\);/', (string)$stylesheet), 'The desktop category rail must use the flat white catalog treatment.');
storefrontCatalogCheck((bool)preg_match('/\.catalog-surface \.cat-badge\s*\{[\s\S]*?background:\s*transparent;[\s\S]*?border:\s*0;/', (string)$cartStylesheet), 'Catalog category metadata must use a flat text treatment.');
storefrontCatalogCheck((bool)preg_match('/@media \(max-width: 991\.98px\)[\s\S]*?\.catalog-layout\s*\{[\s\S]*?grid-template-columns:\s*1fr;/', (string)$stylesheet), 'The filter rail must collapse safely below desktop width.');
storefrontCatalogCheck(str_contains((string)$customerNavigation, 'M5.5 8.5h13l-.8 11H6.3l-.8-11Z'), 'Customer navigation must use the outlined shopping-bag cart icon.');
storefrontCatalogCheck(!str_contains((string)$customerNavigation, '<circle cx="10" cy="18"'), 'Customer navigation must not restore the wheeled cart icon.');
storefrontCatalogCheck((bool)preg_match('/\.customer-nav-cart-count\s*\{[\s\S]*?background:\s*transparent;/', (string)$stylesheet), 'The cart quantity must render inside the shopping-bag icon.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Storefront catalog layout checks passed.\n";
