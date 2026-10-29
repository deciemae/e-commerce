<?php

$root = dirname(__DIR__);
$failures = [];

function homepageCategorySliderCheck(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$homepage = file_get_contents($root . '/index.php');
$stylesheet = file_get_contents($root . '/assets/customer.css');

homepageCategorySliderCheck(str_contains((string)$homepage, 'id="category-slider" role="region" aria-label="Product categories"'), 'The category slider must expose an accessible region label.');
homepageCategorySliderCheck(str_contains((string)$homepage, 'data-category-slider-previous'), 'The category slider must provide a previous control.');
homepageCategorySliderCheck(str_contains((string)$homepage, 'data-category-slider-next'), 'The category slider must provide a next control.');
homepageCategorySliderCheck(str_contains((string)$homepage, 'role="group" aria-label="Category slider controls"'), 'Category controls must be exposed as an accessible group.');
homepageCategorySliderCheck(str_contains((string)$homepage, "slider.scrollBy({"), 'Category controls must progressively scroll the existing slider.');
homepageCategorySliderCheck(str_contains((string)$homepage, "prefers-reduced-motion: reduce"), 'Category scrolling must respect reduced-motion preferences.');
homepageCategorySliderCheck(!str_contains((string)$homepage, '.category-cards-scroll {'), 'Category slider styles must remain in the shared customer stylesheet.');
homepageCategorySliderCheck((bool)preg_match('/\.category-cards-scroll\s*\{[\s\S]*?display:\s*flex;[\s\S]*?overflow-x:\s*auto;[\s\S]*?scroll-snap-type:\s*x proximity;/', (string)$stylesheet), 'Homepage categories must stay in one horizontally scrollable row.');
homepageCategorySliderCheck((bool)preg_match('/\.category-card\s*\{[\s\S]*?width:\s*calc\(\(100% - 1\.5rem\) \/ 3\.2\);[\s\S]*?flex:\s*0 0 auto;[\s\S]*?scroll-snap-align:\s*start;/', (string)$stylesheet), 'Desktop category cards must show three cards plus a partial next card.');
homepageCategorySliderCheck((bool)preg_match('/\.category-slider-button\s*\{[\s\S]*?width:\s*3rem;[\s\S]*?border-radius:\s*var\(--radius-pill\);/', (string)$stylesheet), 'Category controls must use accessible circular icon buttons.');
homepageCategorySliderCheck((bool)preg_match('/\.storefront-section-heading h2\s*\{[\s\S]*?font-size:\s*clamp\(1\.25rem, 1\.8vw, 1\.5rem\);/', (string)$stylesheet), 'Homepage section headings must remain capped at 24px.');
homepageCategorySliderCheck((bool)preg_match('/\.category-card-title\s*\{[\s\S]*?font-size:\s*\.875rem;/', (string)$stylesheet), 'Category card headings must use the compact 14px size.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Homepage category slider checks passed.\n";
