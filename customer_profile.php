<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/validation.php';
require_once 'includes/customer_toast.php';

requireCustomerLogin();

$conn = getConnection();

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$error = $_SESSION['customer_flash_error'] ?? '';
$success = $_SESSION['customer_flash_success'] ?? '';
unset($_SESSION['customer_flash_error'], $_SESSION['customer_flash_success']);
$csrfToken = customerCsrfToken();
$customerId = (int)$customer['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your profile session expired. Please refresh the page and try again.';
    } elseif ($firstName === '' || $lastName === '' || $email === '') {
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
        $stmt->bind_param('si', $email, $customerId);
        $stmt->execute();
        $emailTaken = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($emailTaken) {
            $error = 'That email address cannot be used. Please choose another address.';
        } else {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?, email = ?, phone_number = ?, password = ?
                     WHERE user_id = ?'
                );
                $stmt->bind_param('sssssi', $firstName, $lastName, $email, $phoneNumber, $hash, $customerId);
            } else {
                $stmt = $conn->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?, email = ?, phone_number = ?
                     WHERE user_id = ?'
                );
                $stmt->bind_param('ssssi', $firstName, $lastName, $email, $phoneNumber, $customerId);
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

            error_log('Customer profile update failed: ' . $stmt->error);
            $error = 'We could not update your profile right now. Please try again.';
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
    <title>Profile &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page customer-account-surface">

<?php $customerActivePage = 'profile'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content" class="customer-account-page">
    <div class="account-shell">
        <header class="account-page-header">
            <div>
                <p class="account-eyebrow">Customer account</p>
                <h1>Profile</h1>
                <p>Keep your contact details current and update your password when needed.</p>
            </div>
        </header>

        <?php $customerAccountPage = 'profile'; require 'includes/customer_account_nav.php'; ?>

        <div class="page-content account-content account-content-narrow">
            <?php if ($error): ?><div class="alert alert-danger account-alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <section class="customer-card account-card" aria-labelledby="account-details-title">
                <div class="card-header">
                    <div>
                        <h2 id="account-details-title">Account details</h2>
                        <p>Required fields are marked in the form.</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" class="account-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                        <div class="account-form-grid">
                            <div>
                                <label class="form-label" for="first_name">First name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required maxlength="100" autocomplete="given-name" value="<?= htmlspecialchars($_POST['first_name'] ?? $customer['first_name'], ENT_QUOTES) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="last_name">Last name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required maxlength="100" autocomplete="family-name" value="<?= htmlspecialchars($_POST['last_name'] ?? $customer['last_name'], ENT_QUOTES) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="email">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? $customer['email'], ENT_QUOTES) ?>">
                            </div>
                            <div>
                                <label class="form-label" for="phone_number">Phone number <span>(optional)</span></label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" inputmode="tel" maxlength="20" autocomplete="tel" pattern="[0-9+()\-\s]{7,20}" value="<?= htmlspecialchars($_POST['phone_number'] ?? ($customer['phone_number'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                        </div>

                        <div class="account-form-divider">
                            <h3>Password</h3>
                            <p>Leave both password fields blank to keep your current password.</p>
                        </div>

                        <div class="account-form-grid">
                            <div>
                                <label class="form-label" for="new_password">New password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}">
                            </div>
                            <div>
                                <label class="form-label" for="confirm_password">Confirm new password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="account-form-actions">
                            <a href="customer_dashboard.php" class="account-secondary-link">Cancel</a>
                            <button type="submit" class="account-primary-button">Save changes</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

</main>

<?php require __DIR__ . '/includes/customer_footer.php'; ?>

<?php renderCustomerSuccessToast($success); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
