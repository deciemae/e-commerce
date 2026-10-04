<?php
session_start();
require_once 'config/db.php';
require_once 'includes/customer_system.php';

requireCustomerLogin();

$conn = getConnection();
ensureCustomerTables($conn);

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

$editAddressId = (int)($_GET['edit'] ?? 0);
$editAddress = null;

if ($editAddressId > 0) {
    $stmt = $conn->prepare('SELECT * FROM customer_addresses WHERE address_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $editAddressId, $customerId);
    $stmt->execute();
    $editAddress = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM customer_addresses WHERE address_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $addressId, $customerId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['customer_flash_success'] = 'Address deleted successfully.';
        header('Location: customer_addresses.php');
        exit;
    }

    if ($action === 'set_default') {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE customer_addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $addressId, $customerId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['customer_flash_success'] = 'Default address updated.';
        header('Location: customer_addresses.php');
        exit;
    }

    $addressId = (int)($_POST['address_id'] ?? 0);
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
    $stmt = null;

    if ($recipientName === '' || $phoneNumber === '' || $addressLine1 === '' || $city === '') {
        $error = 'Recipient, phone number, address line 1, and city are required.';
    } else {
        if ($addressId > 0) {
            $stmt = $conn->prepare('SELECT address_id FROM customer_addresses WHERE address_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $addressId, $customerId);
            $stmt->execute();
            $ownedAddress = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$ownedAddress) {
                $error = 'Address not found.';
            } else {
                $stmt = $conn->prepare(
                    'UPDATE customer_addresses
                     SET label = ?, recipient_name = ?, phone_number = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, is_default = ?
                     WHERE address_id = ? AND user_id = ?'
                );
                $stmt->bind_param('sssssssssiii', $label, $recipientName, $phoneNumber, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault, $addressId, $customerId);
            }
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO customer_addresses (user_id, label, recipient_name, phone_number, address_line1, address_line2, city, state, postal_code, country, is_default)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssssssssi', $customerId, $label, $recipientName, $phoneNumber, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault);
        }

        if (empty($error) && $stmt->execute()) {
            if ($isDefault) {
                if ($addressId > 0) {
                    $defaultId = $addressId;
                } else {
                    $defaultId = (int)$conn->insert_id;
                }
                $stmtDefault = $conn->prepare('UPDATE customer_addresses SET is_default = CASE WHEN address_id = ? THEN 1 ELSE 0 END WHERE user_id = ?');
                $stmtDefault->bind_param('ii', $defaultId, $customerId);
                $stmtDefault->execute();
                $stmtDefault->close();
            }

            $_SESSION['customer_flash_success'] = $addressId > 0 ? 'Address updated successfully.' : 'Address added successfully.';
            $stmt->close();
            $conn->close();
            header('Location: customer_addresses.php');
            exit;
        }

        if (empty($error)) {
            $error = 'Failed to save address: ' . $stmt->error;
        }

        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
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
    <title>Delivery Addresses — Bloom &amp; Basket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/customer.css">
</head>
<body class="customer-ui customer-page">

<?php $customerActivePage = 'addresses'; require_once 'includes/customer_nav.php'; ?>

<div id="main-content">
    <div class="page-topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Delivery Addresses</h1>
            <div class="form-muted">Add, edit, and choose the addresses customers can use at checkout.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="customer_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
            <a href="customer_orders.php" class="btn btn-outline-primary btn-sm">Orders</a>
            <a href="customer_logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
        </div>
    </div>

    <div class="page-content">
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="customer-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Saved Addresses</span>
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
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="address_id" value="<?= (int)$address['address_id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this address?')">Delete</button>
                                                </form>
                                                <?php if (empty($address['is_default'])): ?>
                                                    <form method="post" class="d-inline">
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
                </div>
            </div>

            <div class="col-lg-5">
                <div class="customer-card h-100">
                    <div class="card-header"><?= $editAddress ? 'Edit Address' : 'Add Address' ?></div>
                    <div class="card-body p-4">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="save">
                            <?php if ($editAddress): ?>
                                <input type="hidden" name="address_id" value="<?= (int)$editAddress['address_id'] ?>">
                            <?php endif; ?>
                            <div class="col-12" style="margin-top: -15px;">
                                <label class="form-label" for="label">Label</label>
                                <input type="text" class="form-control" id="label" name="label" value="<?= htmlspecialchars($_POST['label'] ?? ($editAddress['label'] ?? 'Home')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="recipient_name">Recipient Name</label>
                                <input type="text" class="form-control" id="recipient_name" name="recipient_name" required value="<?= htmlspecialchars($_POST['recipient_name'] ?? ($editAddress['recipient_name'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone_number">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" required value="<?= htmlspecialchars($_POST['phone_number'] ?? ($editAddress['phone_number'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="country">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($_POST['country'] ?? ($editAddress['country'] ?? 'Philippines')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line1">Address Line 1</label>
                                <input type="text" class="form-control" id="address_line1" name="address_line1" required value="<?= htmlspecialchars($_POST['address_line1'] ?? ($editAddress['address_line1'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="address_line2">Address Line 2</label>
                                <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= htmlspecialchars($_POST['address_line2'] ?? ($editAddress['address_line2'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="city">City</label>
                                <input type="text" class="form-control" id="city" name="city" required value="<?= htmlspecialchars($_POST['city'] ?? ($editAddress['city'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="state">State / Province</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?= htmlspecialchars($_POST['state'] ?? ($editAddress['state'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="postal_code">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? ($editAddress['postal_code'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="is_default" name="is_default" <?= !empty($_POST['is_default']) || !empty($editAddress['is_default']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_default">Set as default</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2 justify-content-end">
                                <?php if ($editAddress): ?><a href="customer_addresses.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                                <button type="submit" class="btn btn-primary"><?= $editAddress ? 'Update Address' : 'Add Address' ?></button>
                            </div>
                        </form>
                    </div>
                </div>
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
