<?php

$root = dirname(__DIR__);
$failures = [];

function favouriteFeatureCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$navigation = file_get_contents($root . '/includes/customer_nav.php');
$favouritesPage = file_get_contents($root . '/favourites.php');
$favouriteActions = file_get_contents($root . '/favourite_actions.php');
$favouriteHelpers = file_get_contents($root . '/includes/customer_favourites.php');
$login = file_get_contents($root . '/customer_login.php');
$register = file_get_contents($root . '/customer_register.php');
$migration = file_get_contents($root . '/database/migrations/202610292350_create_customer_favourites.sql');
$migrationRunner = file_get_contents($root . '/migrate.php');

favouriteFeatureCheck(str_contains((string)$navigation, 'href="favourites.php"'), 'Shared navigation must link to favourites.');
favouriteFeatureCheck(str_contains((string)$navigation, 'data-favourite-toggle'), 'Shared navigation must handle favourite toggles.');
favouriteFeatureCheck(str_contains((string)$favouritesPage, 'Save your favourites'), 'Guests need the focused favourites authentication gate.');
favouriteFeatureCheck(str_contains((string)$favouritesPage, 'customer_login.php?return_to=favourites.php'), 'Guest sign-in must return to favourites.');
favouriteFeatureCheck(str_contains((string)$favouritesPage, 'customer_register.php?return_to=favourites.php'), 'Guest registration must return to favourites.');
favouriteFeatureCheck(str_contains((string)$favouriteActions, "REQUEST_METHOD'] !== 'POST'"), 'Favourite mutations must reject non-POST requests.');
favouriteFeatureCheck(str_contains((string)$favouriteActions, 'customerCsrfIsValid'), 'Favourite mutations must validate CSRF.');
favouriteFeatureCheck(str_contains((string)$favouriteActions, 'currentCustomerId'), 'Favourite mutations must bind to the authenticated customer.');
favouriteFeatureCheck(str_contains((string)$favouriteHelpers, 'WHERE cf.user_id = ?'), 'Favourite reads must be customer scoped.');
favouriteFeatureCheck(str_contains((string)$login, 'customerSafeReturnPath'), 'Login return paths must be allowlisted.');
favouriteFeatureCheck(str_contains((string)$register, 'customer_login_return_to'), 'Registration must preserve the favourites destination.');
favouriteFeatureCheck(str_contains((string)$migration, 'PRIMARY KEY (user_id, product_id)'), 'Favourite migration must prevent duplicate saved products.');
favouriteFeatureCheck(substr_count((string)$migration, 'ON DELETE CASCADE') === 2, 'Favourite migration must clean up deleted customers and products.');
favouriteFeatureCheck(str_contains((string)$migrationRunner, 'hash_file'), 'Migration runner must verify migration checksums.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Favourites feature checks passed.\n";
