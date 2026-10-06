<?php

$baseUrl = rtrim(getenv('SHOPPINGCART_BASE_URL') ?: 'http://127.0.0.1:8011', '/');
$failures = [];

function requestRoute(string $baseUrl, string $path, string $method = 'GET', string $content = ''): array
{
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $method === 'POST' ? "Content-Type: application/x-www-form-urlencoded\r\n" : '',
            'content' => $content,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ],
    ]);
    $body = @file_get_contents($baseUrl . '/' . ltrim($path, '/'), false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $matches);
    $status = isset($matches[1]) ? (int)$matches[1] : 0;
    $location = '';
    foreach ($headers as $header) {
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, strlen('Location:')));
        }
    }

    return ['status' => $status, 'location' => $location, 'headers' => $headers, 'body' => (string)$body];
}

function smokeCheck(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

foreach (['index.php', 'shop.php', 'browse.php', 'about_us.php', 'customer_login.php', 'customer_register.php', 'admin_login.php'] as $route) {
    $response = requestRoute($baseUrl, $route);
    smokeCheck($response['status'] === 200, $route . ' expected 200, received ' . $response['status'] . '.');
    smokeCheck(in_array('X-Content-Type-Options: nosniff', $response['headers'], true), $route . ' is missing the nosniff header.');
    smokeCheck(!str_contains($response['body'], 'Database connection failed:'), $route . ' exposed a database connection error.');
}

$loginResponse = requestRoute($baseUrl, 'customer_login.php');
$setCookieHeaders = array_filter($loginResponse['headers'], static fn(string $header): bool => stripos($header, 'Set-Cookie:') === 0);
$cookieHeader = implode('; ', $setCookieHeaders);
smokeCheck(stripos($cookieHeader, 'HttpOnly') !== false, 'Session cookie is missing HttpOnly.');
smokeCheck(stripos($cookieHeader, 'SameSite=Lax') !== false, 'Session cookie is missing SameSite=Lax.');
smokeCheck(str_contains($loginResponse['body'], 'name="csrf_token"'), 'Customer login form is missing a CSRF token.');

$protectedRoutes = [
    'customer_dashboard.php' => 'customer_login.php',
    'customer_addresses.php' => 'customer_login.php',
    'customer_orders.php' => 'customer_login.php',
    'customer_profile.php' => 'customer_login.php',
    'checkout.php' => 'customer_login.php',
    'admin_dashboard.php' => 'admin_login.php',
    'products.php' => 'admin_login.php',
    'categories.php' => 'admin_login.php',
    'admin_orders.php' => 'admin_login.php',
    'customers.php' => 'admin_login.php',
    'admin_settings.php' => 'admin_login.php',
];

foreach ($protectedRoutes as $route => $location) {
    $response = requestRoute($baseUrl, $route);
    smokeCheck($response['status'] === 302, $route . ' expected 302 for a guest, received ' . $response['status'] . '.');
    smokeCheck($response['location'] === $location, $route . ' redirected to an unexpected location.');
}

foreach (['cart_actions.php', 'customer_logout.php'] as $postOnlyRoute) {
    $response = requestRoute($baseUrl, $postOnlyRoute);
    smokeCheck($response['status'] === 405, $postOnlyRoute . ' must reject GET with 405.');
}

$cartCsrf = requestRoute($baseUrl, 'cart_actions.php', 'POST', 'action=clear');
smokeCheck($cartCsrf['status'] === 403, 'Cart actions must reject a POST without a valid CSRF token.');
$logoutCsrf = requestRoute($baseUrl, 'customer_logout.php', 'POST', '');
smokeCheck($logoutCsrf['status'] === 403, 'Customer logout must reject a POST without a valid CSRF token.');

foreach (['setup_db.php', 'setup_security.php'] as $setupRoute) {
    $response = requestRoute($baseUrl, $setupRoute);
    smokeCheck($response['status'] === 404, $setupRoute . ' must not be available over HTTP.');
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "HTTP smoke checks passed.\n";
