<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/validation.php';

requireCustomerLogin();

$conn = getConnection();
ensureCustomerTables($conn);

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$error = $_SESSION['customer_flash_error'] ?? '';
$success = $_SESSION['customer_flash_success'] ?? '';
unset($_SESSION['customer_flash_error'], $_SESSION['customer_flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'First name, last name, and email are required.';
    } elseif (strlen($firstName) > 100 || strlen($lastName) > 100) {
        $error = 'First name and last name must not exceed 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!phoneNumberIsValid($phoneNumber)) {
        $error = 'Please enter a valid phone number.';
    } elseif ($newPassword !== '' && !passwordMeetsPolicy($newPassword)) {
        $error = strongPasswordMessage();
    } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ?');
        $stmt->bind_param('si', $email, $customer['user_id']);
        $stmt->execute();
        $emailTaken = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($emailTaken) {
            $error = 'That email address is already in use.';
        } else {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?, email = ?, phone_number = ?, password = ?
                     WHERE user_id = ?'
                );
                $stmt->bind_param('sssssi', $firstName, $lastName, $email, $phoneNumber, $hash, $customer['user_id']);
            } else {
                $stmt = $conn->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?, email = ?, phone_number = ?
                     WHERE user_id = ?'
                );
                $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phoneNumber, $customer['user_id']);
            }

            if ($stmt->execute()) {
                $_SESSION['customer_flash_success'] = 'Profile updated successfully.';
                $_SESSION['customer_name'] = trim($firstName . ' ' . $lastName);
                $_SESSION['customer_email'] = $email;
                $stmt->close();
                $conn->close();
                header('Location: customer_profile.php');
                exit;
            }

            $error = 'Failed to update profile: ' . $stmt->error;
            $stmt->close();
        }
    }
}

$customer = fetchCurrentCustomer($conn) ?: $customer;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Profile — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'profile'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Customer Profile</h1>
            <div class="form-muted">Update your personal information and password.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="customer_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
            <a href="customer_addresses.php" class="btn btn-outline-primary btn-sm">Addresses</a>
            <a href="customer_logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>

    <div class="page-content">
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="customer-card">
            <div class="card-header">Account Details</div>
            <div class="card-body p-4" style="margin-top: -25px;">
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required maxlength="100" value="<?= htmlspecialchars($_POST['first_name'] ?? $customer['first_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required maxlength="100" value="<?= htmlspecialchars($_POST['last_name'] ?? $customer['last_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="255" value="<?= htmlspecialchars($_POST['email'] ?? $customer['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone_number">Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" inputmode="tel" maxlength="20" pattern="[0-9+()\-\s]{7,20}" title="Use 7 to 20 digits and may include spaces, +, -, or parentheses." value="<?= htmlspecialchars($_POST['phone_number'] ?? ($customer['phone_number'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave blank to keep current password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}" title="Use at least 8 characters with letters, numbers, and special characters.">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8">
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a href="customer_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
