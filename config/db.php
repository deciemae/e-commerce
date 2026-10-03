<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ShoppingCart');

function getConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('<div class="alert alert-error">Database connection failed: ' . htmlspecialchars($conn->connect_error) . '</div>');
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
