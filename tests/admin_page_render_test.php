<?php

$route = $argv[1] ?? '';
$allowedRoutes = ['products.php', 'categories.php', 'admin_dashboard.php'];

if (!in_array($route, $allowedRoutes, true)) {
    fwrite(STDERR, "Choose products.php, categories.php, or admin_dashboard.php.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/session.php';
ini_set('session.save_path', sys_get_temp_dir());
startApplicationSession();

$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_email'] = 'qa@example.invalid';
$_SESSION['admin_name'] = 'QA Reviewer';
$_SESSION['admin_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require __DIR__ . '/../' . $route;
$html = (string)ob_get_clean();

if (!str_contains($html, '<body class="admin-ui">')) {
    fwrite(STDERR, "FAIL: {$route} did not render the administrator page shell.\n");
    exit(1);
}

if (!str_contains($html, '<section class="admin-page-intro"')) {
    fwrite(STDERR, "FAIL: {$route} did not render its content-level page heading.\n");
    exit(1);
}

$topbarMarkup = '';
if (preg_match('/<header class="page-topbar">([\s\S]*?)<\/header>/', $html, $topbarMatches)) {
    $topbarMarkup = $topbarMatches[1];
}

if ($topbarMarkup === '' || str_contains($topbarMarkup, '<h1')) {
    fwrite(STDERR, "FAIL: {$route} rendered its page heading inside the utility header.\n");
    exit(1);
}

if ($route === 'products.php' && !str_contains($html, 'product-admin-table')) {
    fwrite(STDERR, "FAIL: Product layout was not rendered.\n");
    exit(1);
}

if ($route === 'categories.php' && !str_contains($html, 'category-editor-card')) {
    fwrite(STDERR, "FAIL: Category editor layout was not rendered.\n");
    exit(1);
}

if ($route === 'admin_dashboard.php' && !str_contains($html, 'dashboard-metrics')) {
    fwrite(STDERR, "FAIL: Dashboard metric layout was not rendered.\n");
    exit(1);
}

unset($_SESSION['admin_logged_in'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_id']);
session_destroy();

echo "{$route} authenticated render passed.\n";
