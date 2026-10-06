<?php

require_once __DIR__ . '/session.php';
startApplicationSession();

function customerCsrfToken(): string
{
    if (empty($_SESSION['customer_csrf_token']) || !is_string($_SESSION['customer_csrf_token'])) {
        $_SESSION['customer_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['customer_csrf_token'];
}

function customerCsrfIsValid(?string $token): bool
{
    $storedToken = $_SESSION['customer_csrf_token'] ?? null;

    return is_string($storedToken)
        && is_string($token)
        && $token !== ''
        && hash_equals($storedToken, $token);
}

function currentCustomerId(): ?int
{
    return !empty($_SESSION['customer_user_id']) ? (int)$_SESSION['customer_user_id'] : null;
}

function currentCustomerName(): string
{
    return trim((string)($_SESSION['customer_name'] ?? ''));
}

function currentCustomerEmail(): string
{
    return trim((string)($_SESSION['customer_email'] ?? ''));
}

function customerLoginUser(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['customer_user_id'] = (int)$user['user_id'];
    $_SESSION['customer_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['customer_email'] = (string)($user['email'] ?? '');

    // A customer session must never carry a leftover admin identity.
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_email'], $_SESSION['admin_name'], $_SESSION['admin_id']);
}

function customerLogout(): void
{
    unset($_SESSION['customer_user_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['cart_id']);
}

function requireCustomerLogin(): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, private');
    }

    if (!currentCustomerId()) {
        $_SESSION['customer_flash_error'] = 'Please log in to continue.';
        header('Location: customer_login.php');
        exit;
    }
}

function fetchCurrentCustomer(mysqli $conn): ?array
{
    $customerId = currentCustomerId();
    if (!$customerId) {
        return null;
    }

    $stmt = $conn->prepare('SELECT user_id, first_name, last_name, email, phone_number, created_at FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $customer ?: null;
}

function attachSessionCartToCustomer(mysqli $conn, int $customerId): void
{
    if (empty($_SESSION['cart_id'])) {
        return;
    }

    $cartId = (int)$_SESSION['cart_id'];
    $stmt = $conn->prepare('UPDATE carts SET user_id = ? WHERE cart_id = ?');
    $stmt->bind_param('ii', $customerId, $cartId);
    $stmt->execute();
    $stmt->close();
}

function getOrCreateCart(mysqli $conn): int
{
    $customerId = currentCustomerId();

    if (!empty($_SESSION['cart_id'])) {
        $stmt = $conn->prepare('SELECT cart_id FROM carts WHERE cart_id = ?');
        $stmt->bind_param('i', $_SESSION['cart_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();

            if ($customerId) {
                attachSessionCartToCustomer($conn, $customerId);
            }

            return (int)$_SESSION['cart_id'];
        }
        $stmt->close();
    }

    if ($customerId) {
        $stmt = $conn->prepare('SELECT cart_id FROM carts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cart = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($cart) {
            $_SESSION['cart_id'] = (int)$cart['cart_id'];
            return (int)$cart['cart_id'];
        }
    }

    $sid = session_id();
    $stmt = $conn->prepare('INSERT INTO carts (user_id, session_id) VALUES (?, ?)');
    $stmt->bind_param('is', $customerId, $sid);
    $stmt->execute();
    $cartId = (int)$conn->insert_id;
    $stmt->close();

    $_SESSION['cart_id'] = $cartId;

    return $cartId;
}

function customerAddresses(mysqli $conn, int $customerId): array
{
    $stmt = $conn->prepare(
        'SELECT address_id, user_id, label, recipient_name, phone_number, address_line1, address_line2, city, state, postal_code, country, is_default, created_at
         FROM customer_addresses
         WHERE user_id = ?
         ORDER BY is_default DESC, created_at DESC'
    );
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $addresses = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $addresses;
}

function addressSnapshot(array $address): string
{
    $parts = [];

    $line1 = trim((string)($address['address_line1'] ?? ''));
    
    $line2 = trim((string)($address['address_line2'] ?? ''));
    $city = trim((string)($address['city'] ?? ''));
    $state = trim((string)($address['state'] ?? ''));
    $postal = trim((string)($address['postal_code'] ?? ''));
    $country = trim((string)($address['country'] ?? 'Philippines'));

    $streetParts = array_filter(
        [$line1, $line2],
        static fn($value) => $value !== ''
    );

    if (!empty($streetParts)) {
        $parts[] = implode(', ', $streetParts);
    }

    $localParts = array_filter(
        [$city, $state, $postal],
        static fn($value) => $value !== ''
    );

    if (!empty($localParts)) {
        $parts[] = implode(', ', $localParts);
    }

    if ($country !== '') {
        $parts[] = $country;
    }

    return implode(' | ', $parts);
}

function orderStatusOptions(): array
{
    return ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
}

function orderStatusBadgeClass(string $status): string
{
    return match (strtolower(trim($status))) {
        'confirmed' => 'status-confirmed',
        'processing' => 'status-processing',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        default => 'status-pending',
    };
}

function maskPhoneNumber(string $phoneNumber): string
{
    $len = strlen($phoneNumber);
    if ($len <= 4) {
        return $phoneNumber;
    }
    return str_repeat('*', $len - 4) . substr($phoneNumber, -4);
}
