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
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $confirmPassword === '') {
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
            $error = 'An account with that email already exists.';
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

            $error = 'Registration failed: ' . $stmt->error;
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
    <title>Customer Registration — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui">
<div class="auth-shell">
    <div class="auth-layout d-flex justify-content-center w-100">
        <div class="auth-card" style="max-width: 600px; width: 100%;">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <a href="shop.php" class="brand-mark text-dark mb-3 text-decoration-none d-inline-block" style="font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16" class="me-1 mb-1">
                          <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5H11.5z"/>
                        </svg>
                        Bloom &amp; Basket
                    </a>
                    <h2 class="h3 fw-bold mb-2">Register</h2>
                    <p class="form-muted mb-0">Already have an account? <a href="customer_login.php">Log in</a>.</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-dark fw-medium mb-1" for="first_name">First Name</label>
                    <input type="text" class="form-control bg-light" id="first_name" name="first_name" required maxlength="100" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label text-dark fw-medium mb-1" for="last_name">Last Name</label>
                    <input type="text" class="form-control bg-light" id="last_name" name="last_name" required maxlength="100" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label text-dark fw-medium mb-1" for="email">Email Address</label>
                    <input type="email" class="form-control bg-light" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label text-dark fw-medium mb-1" for="phone_number">Phone Number</label>
                    <input type="tel" class="form-control bg-light" id="phone_number" name="phone_number" inputmode="tel" maxlength="20" pattern="[0-9+()\-\s]{7,20}" title="Use 7 to 20 digits and may include spaces, +, -, or parentheses." value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label text-dark fw-medium mb-1" for="password">Password</label>
                    <input type="password" class="form-control bg-light" id="password" name="password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}" title="Use at least 8 characters with letters, numbers, and special characters.">
                </div>
                 <div class="col-md-12">
                    <label class="form-label text-dark fw-medium mb-1" for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control bg-light" id="confirm_password" name="confirm_password" required minlength="8">
                </div>


                <div class="col-12 d-grid mt-3">
                    <button type="submit" class="btn btn-primary fw-bold" style="border-radius: 8px;">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
