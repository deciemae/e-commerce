<?php

function databaseEnvironmentValue(string $name, string $default): string
{
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

define('DB_HOST', databaseEnvironmentValue('SHOPPINGCART_DB_HOST', 'localhost'));
define('DB_PORT', (int)databaseEnvironmentValue('SHOPPINGCART_DB_PORT', '3306'));
define('DB_USER', databaseEnvironmentValue('SHOPPINGCART_DB_USER', 'root'));
define('DB_PASS', databaseEnvironmentValue('SHOPPINGCART_DB_PASS', ''));
define('DB_NAME', databaseEnvironmentValue('SHOPPINGCART_DB_NAME', 'ShoppingCart'));

function getConnection(): mysqli {
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        error_log('Bloom & Basket database connection failed: ' . $conn->connect_error);

        if (PHP_SAPI === 'cli') {
            throw new RuntimeException('Database connection unavailable.');
        }

        http_response_code(503);
        exit('The store is temporarily unavailable. Please try again later.');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
