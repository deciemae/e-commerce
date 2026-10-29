<?php

$root = dirname(__DIR__);
$failures = [];

function productDetailLayoutCheck(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$productDetails = file_get_contents($root . '/product_details.php');
$stylesheet = file_get_contents($root . '/assets/customer.css');

productDetailLayoutCheck(str_contains((string)$productDetails, 'class="product-detail-layout"'), 'Product details must retain the shared two-column layout.');
productDetailLayoutCheck(str_contains((string)$productDetails, 'class="product-detail-image-stage"'), 'Product details must retain a dedicated image stage.');
productDetailLayoutCheck(str_contains((string)$productDetails, 'class="product-detail-title-row"'), 'Product identity and favourite action must share the compact title row.');
productDetailLayoutCheck((bool)preg_match('/\.product-detail-page \.page-content\s*\{[\s\S]*?width:\s*min\(100%, 1280px\);/', (string)$stylesheet), 'Desktop product details must use the restrained 1280px canvas.');
productDetailLayoutCheck((bool)preg_match('/\.product-detail-image-stage\s*\{[\s\S]*?aspect-ratio:\s*1 \/ 1;/', (string)$stylesheet), 'The main product image must use the compact square stage.');
productDetailLayoutCheck((bool)preg_match('/\.product-detail-page \.product-detail-title\s*\{[\s\S]*?font-size:\s*clamp\(1\.625rem, 2\.25vw, 2rem\);/', (string)$stylesheet), 'The desktop product title must remain capped at 32px.');
productDetailLayoutCheck((bool)preg_match('/@media \(max-width: 991\.98px\)[\s\S]*?\.product-detail-page \.product-detail-layout\s*\{[\s\S]*?grid-template-columns:\s*1fr;/', (string)$stylesheet), 'Product details must collapse to one column below desktop width.');
productDetailLayoutCheck((bool)preg_match('/@media \(max-width: 599\.98px\)[\s\S]*?\.product-detail-page \.product-detail-title\s*\{[\s\S]*?font-size:\s*1\.75rem;/', (string)$stylesheet), 'Mobile product headings must stay compact.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Product detail layout checks passed.\n";
