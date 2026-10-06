<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

$conn = getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireValidCsrf('admin_settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $threshold = (int)($_POST['lockout_threshold'] ?? 0);
    $duration = (int)($_POST['lockout_duration'] ?? 0);
    $pwdMinLength = (int)($_POST['password_min_length'] ?? 0);
    $pwdReqSpec = isset($_POST['password_require_special']) ? '1' : '0';
    $pwdReqNum = isset($_POST['password_require_number']) ? '1' : '0';
    $pwdReqUpper = isset($_POST['password_require_uppercase']) ? '1' : '0';

    if ($threshold >= 1 && $threshold <= 20 && $duration >= 1 && $duration <= 1440 && $pwdMinLength >= 8 && $pwdMinLength <= 72) {
        $updates = [
            'lockout_threshold' => (string)$threshold,
            'lockout_duration' => (string)$duration,
            'password_min_length' => (string)$pwdMinLength,
            'password_require_special' => $pwdReqSpec,
            'password_require_number' => $pwdReqNum,
            'password_require_uppercase' => $pwdReqUpper,
        ];
        
        foreach ($updates as $key => $val) {
            $stmt = $conn->prepare("INSERT INTO admin_security_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param('sss', $key, $val, $val);
            $stmt->execute();
            $stmt->close();
        }

        logAdminActivity('Update Settings', "Updated security policies (Lockout, MFA, Password)");
        $conn->close();
        adminSetFlash('success', 'Settings updated successfully.');
        adminRedirect('admin_settings.php');
    } else {
        $message = '<div class="alert alert-danger" role="alert">Enter a lockout threshold from 1 to 20, a duration from 1 to 1440 minutes, and a password length from 8 to 72.</div>';
    }
}

// Unlock account action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_account'])) {
    $unlock_email = trim($_POST['unlock_email'] ?? '');
    if (!filter_var($unlock_email, FILTER_VALIDATE_EMAIL)) {
        $conn->close();
        adminSetFlash('error', 'Select a valid administrator account.');
        adminRedirect('admin_settings.php');
    }
    $stmt = $conn->prepare("UPDATE admins SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->bind_param('s', $unlock_email);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        adminSetFlash('success', 'Account unlocked successfully.');
        logAdminActivity('Unlock Account', "Unlocked account: $unlock_email");
    } else {
        adminSetFlash('error', 'The account could not be unlocked or is no longer locked.');
    }
    $stmt->close();
    $conn->close();
    adminRedirect('admin_settings.php');
}

// Fetch current settings
$settings = [
    'lockout_threshold' => 5,
    'lockout_duration' => 15,
    'mfa_enabled' => 0,
    'password_min_length' => 8,
    'password_require_special' => 1,
    'password_require_number' => 1,
    'password_require_uppercase' => 1,
];
$settings_res = $conn->query("SELECT setting_name, setting_value FROM admin_security_settings");
if ($settings_res) {
    while ($row = $settings_res->fetch_assoc()) {
        if (isset($settings[$row['setting_name']])) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
    }
}

// Fetch locked accounts
$locked_accounts = [];
$res = $conn->query("SELECT email, locked_until FROM admins WHERE is_locked = 1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $locked_accounts[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php $activePage = 'settings'; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'Settings'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content pt-2">
        <?= $message ?>

        <div class="security-header">Security Features</div>
        
        <!-- Tabs Nav -->
        <ul class="nav nav-pills-custom" id="securityTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="lockout-tab" data-bs-toggle="pill" data-bs-target="#lockout" type="button" role="tab" aria-controls="lockout" aria-selected="true">Account Lockout Policy</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mfa-tab" data-bs-toggle="pill" data-bs-target="#mfa" type="button" role="tab" aria-controls="mfa" aria-selected="false">Multi-Factor Authentication</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pwd-tab" data-bs-toggle="pill" data-bs-target="#pwd" type="button" role="tab" aria-controls="pwd" aria-selected="false">Strong Password Policy</button>
            </li>
        </ul>

        <form method="POST">
            <?= adminCsrfInput() ?>
            <input type="hidden" name="update_settings" value="1">
            
            <!-- Tabs Content -->
            <div class="tab-content" id="securityTabsContent">
                
                <!-- Account Lockout Policy Tab -->
                <div class="tab-pane fade show active" id="lockout" role="tabpanel" aria-labelledby="lockout-tab">
                    <div class="settings-card">
                        <h3>Account Lockout Policy</h3>
                        <p class="text-muted">Protect administrative accounts from brute force attacks by automatically locking them after consecutive failed login attempts.</p>
                        
                        <div class="form-group-custom">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Failed Login Threshold</label>
                                    <input type="number" name="lockout_threshold" class="form-control" value="<?= htmlspecialchars((string)$settings['lockout_threshold']) ?>" min="1" max="20" required>
                                    <div class="form-text small">Number of failed login attempts allowed before the account gets temporarily locked.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Lockout Duration (Minutes)</label>
                                    <input type="number" name="lockout_duration" class="form-control" value="<?= htmlspecialchars((string)$settings['lockout_duration']) ?>" min="1" max="1440" required>
                                    <div class="form-text small">Duration in minutes for which the account remains locked.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Locked Accounts Manager -->
                        <h4 class="h5 mt-4 mb-3 fw-bold">Currently Locked Accounts</h4>
                        <?php if (empty($locked_accounts)): ?>
                            <div class="alert alert-light border text-muted">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle me-2 text-success" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg>
                                No accounts are currently locked.
                            </div>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($locked_accounts as $acc): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($acc['email']) ?></strong><br>
                                            <small class="text-danger">Locked until: <?= htmlspecialchars($acc['locked_until']) ?></small>
                                        </div>
                                        <button type="submit" form="unlockForm_<?= md5($acc['email']) ?>" class="btn btn-sm btn-success">Unlock Now</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Multi-Factor Authentication Tab -->
                <div class="tab-pane fade" id="mfa" role="tabpanel" aria-labelledby="mfa-tab">
                    <div class="settings-card">
                        <h3>Multi-Factor Authentication (MFA)</h3>
                        <p class="text-muted">Add an extra layer of security to your administrative panel by requiring an email verification code during sign-in.</p>
                        
                        <div class="form-group-custom d-flex align-items-center justify-content-between">
                            <div>
                                <strong class="d-block">Require Email Verification for all Admins</strong>
                                <span class="text-muted small">Administrators will be prompted to enter a verification code sent to their registered email address upon login.</span>
                            </div>
                            <div class="form-check form-switch m-0 p-0 settings-switch">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="mfa_enabled" disabled aria-describedby="mfa_status">
                            </div>
                        </div>
                        <p id="mfa_status" class="text-muted mb-0"><strong>DEFERRED:</strong> Email-code delivery and verification are not implemented, so this control cannot be enabled yet.</p>
                    </div>
                </div>

                <!-- Strong Password Policy Tab -->
                <div class="tab-pane fade" id="pwd" role="tabpanel" aria-labelledby="pwd-tab">
                    <div class="settings-card">
                        <h3>Strong Password Policy</h3>
                        <p class="text-muted">Enforce strict requirements for administrator passwords to prevent unauthorized access and credential stuffing.</p>
                        
                        <div class="form-group-custom">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Minimum Password Length</label>
                                <input type="number" name="password_min_length" class="form-control w-50" value="<?= htmlspecialchars((string)$settings['password_min_length']) ?>" min="8" max="72" required>
                                <div class="form-text small">We recommend a minimum of 8 characters for administrative accounts.</div>
                            </div>

                            <label class="form-label fw-bold mb-3">Password Complexity Requirements</label>
                            
                            <div class="form-check-custom">
                                <input class="form-check-input" type="checkbox" id="pwd_special" name="password_require_special" <?= $settings['password_require_special'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pwd_special">
                                    Require at least one special character <span class="text-muted fw-normal">(e.g., !@#$%^&*)</span>
                                </label>
                            </div>
                            
                            <div class="form-check-custom">
                                <input class="form-check-input" type="checkbox" id="pwd_num" name="password_require_number" <?= $settings['password_require_number'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pwd_num">
                                    Require at least one numeric digit <span class="text-muted fw-normal">(0-9)</span>
                                </label>
                            </div>
                            
                            <div class="form-check-custom">
                                <input class="form-check-input" type="checkbox" id="pwd_upper" name="password_require_uppercase" <?= $settings['password_require_uppercase'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pwd_upper">
                                    Require at least one uppercase letter <span class="text-muted fw-normal">(A-Z)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div class="d-flex justify-content-end mt-2 mb-4">
                <button type="submit" class="btn btn-primary px-4 shadow-sm">Save Security Settings</button>
            </div>
        </form>
    </div>

    <div class="page-footer">
        &copy; <?= date('Y') ?> Bloom &amp; Basket
    </div>
</div>

<!-- Forms for unlocking accounts -->
<?php foreach ($locked_accounts as $acc): ?>
    <form method="POST" id="unlockForm_<?= md5($acc['email']) ?>" class="unlock-form">
        <?= adminCsrfInput() ?>
        <input type="hidden" name="unlock_email" value="<?= htmlspecialchars($acc['email']) ?>">
        <input type="hidden" name="unlock_account" value="1">
    </form>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
