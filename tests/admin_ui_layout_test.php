<?php

$root = dirname(__DIR__);
$failures = [];

function adminUiCheck(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures[] = $message;
    }
}

$stylesheet = file_get_contents($root . '/assets/admin.css');
$products = file_get_contents($root . '/products.php');
$categories = file_get_contents($root . '/categories.php');
$sidebar = file_get_contents($root . '/includes/sidebar.php');
$topbar = file_get_contents($root . '/includes/admin_topbar.php');

adminUiCheck(str_contains((string)$stylesheet, '.modal-body .row .col-md-3'), 'The admin grid must map three-column modal fields.');
adminUiCheck(str_contains((string)$stylesheet, '.card-body .row .col-md-4'), 'The admin grid must map four-column card fields.');
adminUiCheck((bool)preg_match('/\.admin-ui \.card-body \.row,[\s\S]*?width:\s*100%;[\s\S]*?margin:\s*0;/', (string)$stylesheet), 'Admin form grids must neutralize Bootstrap negative row gutters.');
adminUiCheck(str_contains((string)$stylesheet, '.product-editor-modal'), 'The product editor needs its responsive modal treatment.');
adminUiCheck(str_contains((string)$stylesheet, '.product-admin-table'), 'The product table needs its scoped operational layout.');

adminUiCheck(str_contains((string)$products, 'modal-dialog-scrollable product-editor-modal'), 'The product modal must remain usable on short screens.');
adminUiCheck(str_contains((string)$products, 'product-admin-table'), 'The product list must use the compact table layout.');
adminUiCheck(str_contains((string)$products, '>Inventory</th>'), 'Product stock and colors must share the inventory column.');
adminUiCheck(str_contains((string)$products, '>Record Details</th>'), 'Product audit metadata must share the record-details column.');
adminUiCheck(!str_contains((string)$products, '<th>Last Updated By</th>'), 'The product table must not restore the oversized legacy audit columns.');

adminUiCheck(str_contains((string)$categories, 'category-editor-card'), 'The category editor must use its scoped responsive layout.');
adminUiCheck(str_contains((string)$categories, 'category-editor-actions'), 'Category actions must remain grouped responsively.');
adminUiCheck(str_contains((string)$categories, '<div class="category-editor-grid">'), 'The category editor needs its scoped grid.');
adminUiCheck(substr_count((string)$categories, '<div class="category-editor-field">') === 2, 'The category editor must contain two consistently sized fields.');
adminUiCheck(str_contains((string)$stylesheet, 'grid-template-columns: minmax(13rem, 1fr) minmax(16rem, 1.25fr) auto;'), 'The category editor must reserve a compact action column.');
adminUiCheck(!str_contains((string)$categories, 'btn btn-primary flex-grow-1'), 'The category submit action must not stretch across its full grid column.');

$dashboard = file_get_contents($root . '/admin_dashboard.php');
adminUiCheck(str_contains((string)$dashboard, 'row dashboard-metrics'), 'Dashboard metrics need their scoped compact grid.');
adminUiCheck(str_contains((string)$stylesheet, 'place-items: center;'), 'Administrator icon containers must center their icons.');
adminUiCheck((bool)preg_match('/\.admin-ui \.page-content > \.row > \* \{[\s\S]*?margin-top:\s*0;/', (string)$stylesheet), 'Admin page grids must neutralize Bootstrap child gutter margins.');
adminUiCheck(str_contains((string)$sidebar, 'class="nav-label"'), 'Sidebar labels must be independently hideable in compact mode.');
adminUiCheck((bool)preg_match('/\.admin-ui\.sidebar-collapsed #sidebar \{[\s\S]*?width:\s*4\.5rem;[\s\S]*?transform:\s*none;/', (string)$stylesheet), 'Desktop sidebar collapse must retain an icon rail.');
adminUiCheck((bool)preg_match('/\.admin-ui\.sidebar-collapsed #main-content \{[\s\S]*?margin-left:\s*4\.5rem;/', (string)$stylesheet), 'Main content must preserve space for the collapsed icon rail.');
adminUiCheck(!str_contains((string)$stylesheet, 'transform: translateX(-16rem);'), 'Desktop collapse must not remove the sidebar from view.');
adminUiCheck(str_contains((string)$topbar, '<section class="admin-page-intro"'), 'Page titles must render in the content-level introduction.');
adminUiCheck(!str_contains((string)$topbar, 'page-topbar-heading'), 'The utility header must not contain page headings or breadcrumb-style copy.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Administrator UI layout checks passed.\n";
