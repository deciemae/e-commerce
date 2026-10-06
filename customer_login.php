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

$error = $_SESSION['customer_flash_error'] ?? '';
$success = $_SESSION['customer_flash_success'] ?? '';
unset($_SESSION['customer_flash_error'], $_SESSION['customer_flash_success']);
$csrfToken = customerCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your sign-in session expired. Please refresh the page and try again.';
    } elseif ($email === '' || $password === '') {
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
    <title>Sign In &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-auth customer-auth-login">
<main class="auth-shell">
    <div class="auth-layout">
        <section class="auth-visual" aria-label="Bloom and Basket storefront">
            <img src="assets/hero-photo.png" alt="Everyday fashion and lifestyle products" class="auth-visual-image">
            <div class="auth-visual-overlay"></div>
            <div class="auth-visual-content">
                <p class="auth-visual-eyebrow">Bloom &amp; Basket</p>
                <h2>Welcome back to your everyday essentials.</h2>
                <p>Sign in to review your cart, saved addresses, and orders.</p>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="sign-in-title">
            <div class="auth-card">
                <div class="auth-brand-row">
                    <a href="index.php" class="auth-brand">Bloom &amp; Basket</a>
                    <a href="shop.php" class="auth-back-link">Back to shop</a>
                </div>

                <p class="auth-kicker">Customer account</p>
                <h1 id="sign-in-title">Sign in</h1>
                <p class="auth-intro">Enter your account details to continue.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger auth-alert" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success auth-alert" role="status"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <div>
                        <label class="form-label" for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="current-password">
                    </div>
                    <button type="submit" class="auth-submit">Sign in</button>
                </form>

                <p class="auth-switch">New to Bloom &amp; Basket? <a href="customer_register.php">Create an account</a></p>
            </div>
        </section>
    </div>
</main>
</body>
</html>
