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
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your registration session expired. Please refresh the page and try again.';
    } elseif ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'First name, last name, email, password, and password confirmation are required.';
    } elseif (strlen($firstName) > 100 || strlen($lastName) > 100) {
        $error = 'First name and last name must not exceed 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!phoneNumberIsValid($phoneNumber)) {
        $error = 'Please enter a valid phone number.';
    } elseif (!passwordMeetsPolicy($password)) {
        $error = strongPasswordMessage();
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $error = 'We could not create an account with those details. Please review them or sign in if you already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'INSERT INTO users (first_name, last_name, email, password, phone_number)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $firstName, $lastName, $email, $hash, $phoneNumber);

            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                
                // Automatically log the new user in
                $newUser = [
                    'user_id' => $newUserId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email
                ];
                customerLoginUser($newUser);
                attachSessionCartToCustomer($conn, (int)$newUserId);

                $_SESSION['customer_flash_success'] = 'Account created successfully! You are now logged in.';
                $stmt->close();
                $conn->close();
                header('Location: index.php');
                exit;
            }

            error_log('Customer registration failed: ' . $stmt->error);
            $error = 'We could not create your account right now. Please try again.';
            $stmt->close();
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
    <title>Create Account &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-auth customer-auth-register">
<main class="auth-shell">
    <div class="auth-layout">
        <section class="auth-visual" aria-label="Bloom and Basket storefront">
            <img src="assets/hero-photo.png" alt="Everyday fashion and lifestyle products" class="auth-visual-image">
            <div class="auth-visual-overlay"></div>
            <div class="auth-visual-content">
                <p class="auth-visual-eyebrow">Bloom &amp; Basket</p>
                <h2>Make every checkout feel simpler.</h2>
                <p>Create one account for your cart, saved addresses, and order updates.</p>
            </div>
        </section>

        <section class="auth-panel" aria-labelledby="register-title">
            <div class="auth-card auth-card-wide">
                <div class="auth-brand-row">
                    <a href="index.php" class="auth-brand">Bloom &amp; Basket</a>
                    <a href="shop.php" class="auth-back-link">Back to shop</a>
                </div>

                <p class="auth-kicker">Customer account</p>
                <h1 id="register-title">Create your account</h1>
                <p class="auth-intro">Use your details to set up a secure customer account.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger auth-alert" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success auth-alert" role="status"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form auth-register-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                    <div class="auth-field-grid">
                        <div>
                            <label class="form-label" for="first_name">First name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name" value="<?= htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="last_name">Last name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required maxlength="100" autocomplete="family-name" value="<?= htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES) ?>">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="phone_number">Phone number <span>(optional)</span></label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" inputmode="tel" maxlength="20" autocomplete="tel" pattern="[0-9+()\-\s]{7,20}" title="Use 7 to 20 digits and may include spaces, +, -, or parentheses." value="<?= htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="auth-field-grid">
                        <div>
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}" aria-describedby="password-hint">
                        </div>
                        <div>
                            <label class="form-label" for="confirm_password">Confirm password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <p class="auth-field-hint" id="password-hint">Use at least 8 characters with letters, numbers, and a special character.</p>
                    <button type="submit" class="auth-submit">Create account</button>
                </form>

                <p class="auth-switch">Already registered? <a href="customer_login.php">Sign in</a></p>
            </div>
        </section>
    </div>
</main>
</body>
</html>
