<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/validation.php';

if (currentCustomerId()) {
    header('Location: index.php');
    exit;
}

$conn = getConnection();
ensureCustomerTables($conn);

$error = $_SESSION['customer_flash_error'] ?? '';
$success = $_SESSION['customer_flash_success'] ?? '';
unset($_SESSION['customer_flash_error'], $_SESSION['customer_flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare(
            'SELECT user_id, first_name, last_name, email, password
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Incorrect login information.';
        } else {
            customerLoginUser($user);
            attachSessionCartToCustomer($conn, (int)$user['user_id']);
            $conn->close();
            header('Location: index.php');
            exit;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <title>Customer Login — Bloom &amp; Basket</title> -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui">
<div class="auth-shell">
    <div class="auth-layout d-flex justify-content-center w-100">
        <div class="auth-card" style="max-width: 440px; width: 100%;">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <a href="shop.php" class="brand-mark text-dark mb-3 text-decoration-none d-inline-block" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16" class="me-1 mb-1">
                          <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
                        </svg>
                        Bloom &amp; Basket
                    </a>
                    <h2 class="h3 fw-bold mb-2">Login</h2>
                    <p class="form-muted mb-0">New customer? <a href="customer_register.php">Create a Bloom &amp; Basket account</a>.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-12">
                    <label class="form-label text-dark fw-medium mb-1" for="email">Email Address</label>
                    <input type="email" class="form-control bg-light" id="email" name="email" required maxlength="255" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label text-dark fw-medium mb-1" for="password">Password</label>
                    <input type="password" class="form-control bg-light" id="password" name="password" required minlength="8" autocomplete="current-password">
                </div>
                <div class="col-12 d-grid mt-3">
                    <button type="submit" class="btn btn-primary fw-bold" style="border-radius: 8px;">Log In</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
