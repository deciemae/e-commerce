<?php
session_start();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

$conn = getConnection();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $threshold = (int)$_POST['lockout_threshold'];
    $duration = (int)$_POST['lockout_duration'];
    $mfaEnabled = isset($_POST['mfa_enabled']) ? '1' : '0';
    $pwdMinLength = (int)$_POST['password_min_length'];
    $pwdReqSpec = isset($_POST['password_require_special']) ? '1' : '0';
    $pwdReqNum = isset($_POST['password_require_number']) ? '1' : '0';
    $pwdReqUpper = isset($_POST['password_require_uppercase']) ? '1' : '0';

    if ($threshold > 0 && $duration > 0 && $pwdMinLength >= 4) {
        $updates = [
            'lockout_threshold' => (string)$threshold,
            'lockout_duration' => (string)$duration,
            'mfa_enabled' => $mfaEnabled,
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
        $message = '<div class="alert alert-success">Settings updated successfully.</div>';
    } else {
        $message = '<div class="alert alert-danger">Invalid values provided. Ensure password minimum length is at least 4.</div>';
    }
}

// Unlock account action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_account'])) {
    $unlock_email = trim($_POST['unlock_email']);
    $stmt = $conn->prepare("UPDATE admins SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->bind_param('s', $unlock_email);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $message = '<div class="alert alert-success">Account unlocked successfully.</div>';
        logAdminActivity('Unlock Account', "Unlocked account: $unlock_email");
    } else {
        $message = '<div class="alert alert-danger">Failed to unlock account or account not found.</div>';
    }
    $stmt->close();
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
    <style>
        :root {
            --ink: #2a2621;
            --muted: #8a8175;
            --line: rgba(42, 38, 33, 0.09);
            --paper: #fbf9f6;
            --rose: #c2477a;
            --rose-soft: rgba(194, 71, 122, 0.12);
        }
        #main-content { color: var(--ink); }
        .page-topbar h1 { color: var(--ink); margin-bottom: 4px; }
        .page-subtitle { color: var(--muted); font-size: 0.92rem; margin: 0; }
        
        .security-header {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1rem;
        }

        /* Pill Navigation Styles */
        .nav-pills-custom {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            /* Hide scrollbar */
            scrollbar-width: none; 
            -ms-overflow-style: none;
        }
        .nav-pills-custom::-webkit-scrollbar { 
            display: none; 
        }

        .nav-pills-custom .nav-link {
            background-color: #fff;
            color: #334155;
            border: 1px solid #e2e8f0;
            border-radius: 9999px; /* Fully rounded pill */
            padding: 6px 16px;
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: all 0.2s ease;
        }

        .nav-pills-custom .nav-link:hover {
            border-color: #cbd5e1;
            background-color: #f8fafc;
        }

        .nav-pills-custom .nav-link.active {
            background-color: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .settings-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px -1px rgba(42, 38, 33, 0.04);
            border: 1px solid var(--line);
            margin-bottom: 20px;
        }
        
        .settings-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .settings-card p.text-muted {
            font-size: 0.85rem;
            margin-bottom: 20px;
        }


        .form-check-custom {
            padding: 8px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: background 0.15s ease;
            font-size: 0.9rem;
        }
        .form-check-custom:hover {
            background: var(--paper);
        }
        .form-check-custom .form-check-input {
            margin-top: 0;
            margin-right: 10px;
            width: 1em;
            height: 1em;
        }
        .form-check-custom .form-check-label {
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            margin: 0;
        }

        .form-group-custom {
            background: var(--paper);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--line);
            margin-bottom: 16px;
        }
    </style>
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
                                    <input type="number" name="lockout_threshold" class="form-control" value="<?= htmlspecialchars((string)$settings['lockout_threshold']) ?>" min="1" required>
                                    <div class="form-text small">Number of failed login attempts allowed before the account gets temporarily locked.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Lockout Duration (Minutes)</label>
                                    <input type="number" name="lockout_duration" class="form-control" value="<?= htmlspecialchars((string)$settings['lockout_duration']) ?>" min="1" required>
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
                            <div class="form-check form-switch m-0 p-0" style="padding-left: 2rem !important;">
                                <input class="form-check-input ms-0" type="checkbox" role="switch" id="mfa_enabled" name="mfa_enabled" <?= $settings['mfa_enabled'] ? 'checked' : '' ?>>
                            </div>
                        </div>
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
                                <input type="number" name="password_min_length" class="form-control w-50" value="<?= htmlspecialchars((string)$settings['password_min_length']) ?>" min="4" required>
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
    <form method="POST" id="unlockForm_<?= md5($acc['email']) ?>" style="display:none;">
        <input type="hidden" name="unlock_email" value="<?= htmlspecialchars($acc['email']) ?>">
        <input type="hidden" name="unlock_account" value="1">
    </form>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
