<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'includes/customer_system.php';
require_once 'includes/validation.php';
require_once 'includes/customer_toast.php';

requireCustomerLogin();

class CustomerAddressValidationException extends RuntimeException
{
}

$conn = getConnection();

$customer = fetchCurrentCustomer($conn);
if (!$customer) {
    customerLogout();
    header('Location: customer_login.php');
    exit;
}

$customerId = (int)$customer['user_id'];
$error = $_SESSION['customer_flash_error'] ?? '';
$success = $_SESSION['customer_flash_success'] ?? '';
unset($_SESSION['customer_flash_error'], $_SESSION['customer_flash_success']);
$csrfToken = customerCsrfToken();

$editAddressId = (int)($_GET['edit'] ?? 0);
$editAddress = null;

if ($editAddressId > 0) {
    $stmt = $conn->prepare(
        'SELECT address_id, label, recipient_name, phone_number, address_line1, address_line2,
                city, state, postal_code, country, is_default, created_at
         FROM customer_addresses
         WHERE address_id = ? AND user_id = ?'
    );
    $stmt->bind_param('ii', $editAddressId, $customerId);
    $stmt->execute();
    $editAddress = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'save');

    if (!customerCsrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Your address session expired. Please refresh the page and try again.';
    } elseif (!in_array($action, ['save', 'delete', 'set_default'], true)) {
        $error = 'The requested address action is not valid.';
    } else {
        try {
            $conn->begin_transaction();
            $addressId = (int)($_POST['address_id'] ?? 0);

            if ($action === 'delete') {
                if ($addressId <= 0) {
                    throw new CustomerAddressValidationException('The selected address is not valid.');
                }

                $stmt = $conn->prepare('DELETE FROM customer_addresses WHERE address_id = ? AND user_id = ?');
                $stmt->bind_param('ii', $addressId, $customerId);
                $stmt->execute();
                $deleted = $stmt->affected_rows;
                $stmt->close();

                if ($deleted !== 1) {
                    throw new CustomerAddressValidationException('The selected address was not found.');
                }

                $conn->commit();
                $_SESSION['customer_flash_success'] = 'Address deleted successfully.';
                $conn->close();
                header('Location: customer_addresses.php');
                exit;
            }

            if ($action === 'set_default') {
                if ($addressId <= 0) {
                    throw new CustomerAddressValidationException('The selected address is not valid.');
                }

                $stmt = $conn->prepare(
                    'SELECT address_id FROM customer_addresses
                     WHERE address_id = ? AND user_id = ? FOR UPDATE'
                );
                $stmt->bind_param('ii', $addressId, $customerId);
                $stmt->execute();
                $ownedAddress = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$ownedAddress) {
                    throw new CustomerAddressValidationException('The selected address was not found.');
                }

                $stmt = $conn->prepare(
                    'UPDATE customer_addresses
                     SET is_default = CASE WHEN address_id = ? THEN 1 ELSE 0 END
                     WHERE user_id = ?'
                );
                $stmt->bind_param('ii', $addressId, $customerId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $_SESSION['customer_flash_success'] = 'Default address updated.';
                $conn->close();
                header('Location: customer_addresses.php');
                exit;
            }

            $label = trim($_POST['label'] ?? 'Home');
            $recipientName = trim($_POST['recipient_name'] ?? '');
            $phoneNumber = trim($_POST['phone_number'] ?? '');
            $addressLine1 = trim($_POST['address_line1'] ?? '');
            $addressLine2 = trim($_POST['address_line2'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postalCode = trim($_POST['postal_code'] ?? '');
            $country = trim($_POST['country'] ?? 'Philippines');
            $isDefault = !empty($_POST['is_default']) ? 1 : 0;

            if ($label === '' || $recipientName === '' || $phoneNumber === '' || $addressLine1 === '' || $city === '' || $country === '') {
                throw new CustomerAddressValidationException('Label, recipient, phone number, address line 1, city, and country are required.');
            }
            if (!phoneNumberIsValid($phoneNumber)) {
                throw new CustomerAddressValidationException('Please enter a valid phone number.');
            }
            if (strlen($label) > 50 || strlen($recipientName) > 100 || strlen($phoneNumber) > 20
                || strlen($addressLine1) > 150 || strlen($addressLine2) > 150 || strlen($city) > 100
                || strlen($state) > 100 || strlen($postalCode) > 20 || strlen($country) > 100) {
                throw new CustomerAddressValidationException('One or more address fields exceed the allowed length.');
            }

            if ($addressId > 0) {
                $stmt = $conn->prepare(
                    'SELECT address_id FROM customer_addresses
                     WHERE address_id = ? AND user_id = ? FOR UPDATE'
                );
                $stmt->bind_param('ii', $addressId, $customerId);
                $stmt->execute();
                $ownedAddress = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$ownedAddress) {
                    throw new CustomerAddressValidationException('The selected address was not found.');
                }

                $stmt = $conn->prepare(
                    'UPDATE customer_addresses
                     SET label = ?, recipient_name = ?, phone_number = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, is_default = ?
                     WHERE address_id = ? AND user_id = ?'
                );
                $stmt->bind_param('sssssssssiii', $label, $recipientName, $phoneNumber, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault, $addressId, $customerId);
                $stmt->execute();
                $stmt->close();
                $savedAddressId = $addressId;
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO customer_addresses (user_id, label, recipient_name, phone_number, address_line1, address_line2, city, state, postal_code, country, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isssssssssi', $customerId, $label, $recipientName, $phoneNumber, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault);
                $stmt->execute();
                $savedAddressId = (int)$conn->insert_id;
                $stmt->close();
            }

            if ($isDefault === 1) {
                $stmt = $conn->prepare(
                    'UPDATE customer_addresses
                     SET is_default = CASE WHEN address_id = ? THEN 1 ELSE 0 END
                     WHERE user_id = ?'
                );
                $stmt->bind_param('ii', $savedAddressId, $customerId);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $_SESSION['customer_flash_success'] = $addressId > 0 ? 'Address updated successfully.' : 'Address added successfully.';
            $conn->close();
            header('Location: customer_addresses.php');
            exit;
        } catch (CustomerAddressValidationException $exception) {
            $conn->rollback();
            $error = $exception->getMessage();
        } catch (Throwable $throwable) {
            $conn->rollback();
            error_log('Customer address operation failed: ' . $throwable->getMessage());
            $error = 'We could not update your addresses right now. Please try again.';
        }
    }
}

$stmt = $conn->prepare(
    'SELECT address_id, label, recipient_name, phone_number, address_line1, address_line2, city, state, postal_code, country, is_default, created_at
     FROM customer_addresses
     WHERE user_id = ?
     ORDER BY is_default DESC, created_at DESC'
);
$stmt->bind_param('i', $customerId);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Addresses &mdash; Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page customer-account-surface">

<?php $customerActivePage = 'addresses'; require_once 'includes/customer_nav.php'; ?>

<main id="main-content" class="customer-account-page">
    <div class="account-shell">
        <header class="account-page-header">
            <div>
                <p class="account-eyebrow">Customer account</p>
                <h1>Delivery addresses</h1>
                <p>Add, edit, and choose the addresses available during checkout.</p>
            </div>
        </header>

        <?php $customerAccountPage = 'addresses'; require 'includes/customer_account_nav.php'; ?>

        <div class="page-content account-content">
        <?php if ($error): ?><div class="alert alert-danger account-alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <section class="customer-card account-card h-100" aria-labelledby="saved-address-list-title">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 id="saved-address-list-title">Saved addresses</h2>
                        <span class="text-muted small"><?= count($addresses) ?> saved</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($addresses)): ?>
                            <div class="empty-panel">No delivery addresses saved yet.</div>
                        <?php else: ?>
                            <div class="d-grid gap-3">
                                <?php foreach ($addresses as $address): ?>
                                    <div class="address-card <?= !empty($address['is_default']) ? 'default' : '' ?> p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <div>
                                                        <span class="address-label-pill fw-bold"><?= htmlspecialchars($address['label']) ?></span>
                                                    </div>
                                                    <?php if (!empty($address['is_default'])): ?>
                                                        <span class="status-pill status-confirmed">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted"><?= htmlspecialchars(addressSnapshot($address)) ?></div>
                                            </div>
                                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                                <a href="customer_addresses.php?edit=<?= (int)$address['address_id'] ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="address_id" value="<?= (int)$address['address_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this address?')">Delete</button>
                                                </form>
                                                <?php if (empty($address['is_default'])): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                                                        <input type="hidden" name="action" value="set_default">
                                                        <input type="hidden" name="address_id" value="<?= (int)$address['address_id'] ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">Set Default</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="customer-card account-card h-100" aria-labelledby="address-form-title">
                    <div class="card-header"><h2 id="address-form-title"><?= $editAddress ? 'Edit address' : 'Add an address' ?></h2></div>
                    <div class="card-body">
                        <form method="post" class="row g-3 account-form account-address-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="save">
                            <?php if ($editAddress): ?>
                                <input type="hidden" name="address_id" value="<?= (int)$editAddress['address_id'] ?>">
                            <?php endif; ?>
                            <div class="col-12">
                                <label class="form-label" for="label">Label</label>
                                <input type="text" class="form-control" id="label" name="label" required maxlength="50" value="<?= htmlspecialchars($_POST['label'] ?? ($editAddress['label'] ?? 'Home'), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="recipient_name">Recipient Name</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name" required maxlength="100" autocomplete="name" value="<?= htmlspecialchars($_POST['recipient_name'] ?? ($editAddress['recipient_name'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone_number">Phone Number</label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" required maxlength="20" inputmode="tel" autocomplete="tel" pattern="[0-9+()\-\s]{7,20}" value="<?= htmlspecialchars($_POST['phone_number'] ?? ($editAddress['phone_number'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="country">Country</label>
                                <input type="text" class="form-control" id="country" name="country" required maxlength="100" autocomplete="country-name" value="<?= htmlspecialchars($_POST['country'] ?? ($editAddress['country'] ?? 'Philippines'), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Address Line 1</label>
                                <input type="text" class="form-control" id="address_line1" name="address_line1" required maxlength="150" autocomplete="address-line1" value="<?= htmlspecialchars($_POST['address_line1'] ?? ($editAddress['address_line1'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" maxlength="150" autocomplete="address-line2" value="<?= htmlspecialchars($_POST['address_line2'] ?? ($editAddress['address_line2'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="city">City</label>
                                <input type="text" class="form-control" id="city" name="city" required maxlength="100" autocomplete="address-level2" value="<?= htmlspecialchars($_POST['city'] ?? ($editAddress['city'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="state">State / Province</label>
                                <input type="text" class="form-control" id="state" name="state" maxlength="100" autocomplete="address-level1" value="<?= htmlspecialchars($_POST['state'] ?? ($editAddress['state'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="postal_code">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" maxlength="20" autocomplete="postal-code" value="<?= htmlspecialchars($_POST['postal_code'] ?? ($editAddress['postal_code'] ?? ''), ENT_QUOTES) ?>">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="is_default" name="is_default" <?= !empty($_POST['is_default']) || !empty($editAddress['is_default']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_default">Set as default</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2 justify-content-end">
                                <?php if ($editAddress): ?><a href="customer_addresses.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                <button type="submit" class="account-primary-button"><?= $editAddress ? 'Update address' : 'Add address' ?></button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
        </div>
    </div>

</main>

<?php require __DIR__ . '/includes/customer_footer.php'; ?>

<?php renderCustomerSuccessToast($success); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
