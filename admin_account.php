<?php
session_start();
require_once 'config/db.php';
require_once 'admin_auth.php';
requireAdminLogin();

/**
 * Your login (admin_auth.php) tracks the session by email, not by ID:
 *     $_SESSION['admin_email']
 * so we look the admin row up by that, then use its admin_id for updates.
 */

$conn = getConnection();
$adminEmail = $_SESSION['admin_email'] ?? null;

$profileErrors = [];
$profileSuccess = null;
$passwordErrors = [];
$passwordSuccess = null;

// ---- Fetch current admin info ----
$admin = null;
$adminId = null;
if ($adminEmail) {
    $stmt = $conn->prepare('SELECT admin_id, full_name, email, photo_path, password, role, last_login FROM admins WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $adminId = $admin['admin_id'] ?? null;
}

// Keep whatever the admin typed on a failed submit so the form doesn't clear
$fullNameInput = $admin['full_name'] ?? '';
$emailInput = $admin['email'] ?? '';

// ---- Handle form submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminId) {
    $formType = $_POST['form_type'] ?? '';

    // ---- Profile info + photo upload ----
    if ($formType === 'profile') {
        $fullNameInput = trim($_POST['full_name'] ?? '');
        $emailInput = trim($_POST['email'] ?? '');

        // Full name: required, 2–100 chars, letters/spaces/hyphens/apostrophes/periods only
        if ($fullNameInput === '') {
            $profileErrors['full_name'] = 'Full name is required.';
        } elseif (mb_strlen($fullNameInput) < 2 || mb_strlen($fullNameInput) > 100) {
            $profileErrors['full_name'] = 'Full name must be between 2 and 100 characters.';
        } elseif (!preg_match("/^[\p{L}\s'\-.]+$/u", $fullNameInput)) {
            $profileErrors['full_name'] = 'Full name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        }

        // Email: required, valid format, reasonable length
        if ($emailInput === '') {
            $profileErrors['email'] = 'Email address is required.';
        } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $profileErrors['email'] = 'Please enter a valid email address.';
        } elseif (mb_strlen($emailInput) > 150) {
            $profileErrors['email'] = 'Email address is too long.';
        }

        // ---- Handle photo upload (optional) ----
        $newPhotoPath = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                $profileErrors['profile_photo'] = 'The photo failed to upload. Please try again.';
            } else {
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $tmpPath = $_FILES['profile_photo']['tmp_name'];
                $mimeType = mime_content_type($tmpPath);
                $maxBytes = 2 * 1024 * 1024; // 2MB

                if (!isset($allowedTypes[$mimeType])) {
                    $profileErrors['profile_photo'] = 'Profile photo must be a JPG, PNG, or WEBP image.';
                } elseif ($_FILES['profile_photo']['size'] > $maxBytes) {
                    $profileErrors['profile_photo'] = 'Profile photo must be 2MB or smaller.';
                } else {
                    $ext = $allowedTypes[$mimeType];
                    $uploadDir = __DIR__ . '/assets/uploads/admins/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = 'admin_' . $adminId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($tmpPath, $uploadDir . $fileName)) {
                        $newPhotoPath = 'assets/uploads/admins/' . $fileName;
                    } else {
                        $profileErrors['profile_photo'] = 'Something went wrong uploading the photo. Please try again.';
                    }
                }
            }
        }

        if (empty($profileErrors)) {
            if ($newPhotoPath) {
                // Clean up the old photo file so uploads don't pile up
                if (!empty($admin['photo_path']) && file_exists(__DIR__ . '/' . $admin['photo_path'])) {
                    @unlink(__DIR__ . '/' . $admin['photo_path']);
                }
                $stmt = $conn->prepare('UPDATE admins SET full_name = ?, email = ?, photo_path = ? WHERE admin_id = ?');
                $stmt->bind_param('sssi', $fullNameInput, $emailInput, $newPhotoPath, $adminId);
            } else {
                $stmt = $conn->prepare('UPDATE admins SET full_name = ?, email = ? WHERE admin_id = ?');
                $stmt->bind_param('ssi', $fullNameInput, $emailInput, $adminId);
            }
            $stmt->execute();
            $stmt->close();

            $profileSuccess = 'Your profile has been updated.';

            $admin['full_name'] = $fullNameInput;
            $admin['email'] = $emailInput;
            if ($newPhotoPath) {
                $admin['photo_path'] = $newPhotoPath;
            }

            // Login is tracked by email, so keep the session in sync if it changed
            $_SESSION['admin_email'] = strtolower(trim($emailInput));
        }
    }

    // ---- Remove photo ----
    if ($formType === 'remove_photo') {
        if (!empty($admin['photo_path']) && file_exists(__DIR__ . '/' . $admin['photo_path'])) {
            @unlink(__DIR__ . '/' . $admin['photo_path']);
        }
        $stmt = $conn->prepare('UPDATE admins SET photo_path = NULL WHERE admin_id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        $admin['photo_path'] = null;
        $profileSuccess = 'Your profile photo has been removed.';
    }

    // ---- Change password ----
    if ($formType === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $passwordErrors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, $admin['password'] ?? '')) {
            $passwordErrors['current_password'] = 'Current password is incorrect.';
        }

        if ($newPassword === '') {
            $passwordErrors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $passwordErrors['new_password'] = 'New password must be between 8 and 72 characters.';
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).+$/', $newPassword)) {
            $passwordErrors['new_password'] = 'New password must include at least one letter and one number.';
        }

        if ($confirmPassword === '') {
            $passwordErrors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordErrors['confirm_password'] = 'New password and confirmation do not match.';
        }

        if (empty($passwordErrors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE admins SET password = ? WHERE admin_id = ?');
            $stmt->bind_param('si', $newHash, $adminId);
            $stmt->execute();
            $stmt->close();

            $passwordSuccess = 'Your password has been changed.';
        }
    }
}

$conn->close();

$photoPath = $admin['photo_path'] ?? null;
$displayName = $admin['full_name'] ?? '';
$displayEmail = $admin['email'] ?? '';
$displayRole = $admin['role'] ?? 'Admin';
$lastLogin = $admin['last_login'] ?? null;
$lastLoginDisplay = $lastLogin ? date('M d, Y g:i A', strtotime($lastLogin)) : 'Never';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — Bloom &amp; Basket</title>
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

        .account-wrap { max-width: 1040px; }

        .account-card {
            background: #fff;
            border-radius: 14px;
            padding: 24px 26px;
            box-shadow: 0 1px 2px rgba(42, 38, 33, 0.04);
            border: 1px solid var(--line);
        }

        .account-card + .account-card {
            margin-top: 24px;
        }

        .account-card-header {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--line);
        }

        .account-card-header h2 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0 0 4px;
        }

        .account-card-header .section-hint {
            font-size: 0.85rem;
            color: var(--muted);
            margin: 0;
        }

        /* ---- Profile photo card ---- */
        .avatar-row {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .avatar-preview {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--rose-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--rose);
            border: 1px solid var(--line);
            flex-shrink: 0;
        }

        .avatar-name {
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 2px;
        }

        .avatar-status {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .avatar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .avatar-actions form { margin: 0; }

        .form-hint {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 12px;
        }

        /* ---- Account details rows ---- */
        .meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
            font-size: 0.9rem;
        }

        .meta-row:last-child { border-bottom: none; }

        .meta-row .meta-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
        }

        .meta-row .meta-label svg { flex-shrink: 0; color: var(--muted); }
        .meta-row .meta-value { font-weight: 600; color: var(--ink); text-align: right; }

        .role-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            background: var(--rose-soft);
            color: var(--rose);
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* ---- Password requirements panel ---- */
        .password-requirements {
            display: flex;
            gap: 12px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 18px;
        }

        .password-requirements svg {
            color: var(--rose);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .password-requirements h3 {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0 0 6px;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 18px;
            font-size: 0.82rem;
            color: var(--muted);
        }

        .password-requirements li { margin-bottom: 2px; }
    </style>
</head>
<body class="admin-ui">

<?php $activePage = ''; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'My Account'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content">
        <div class="account-wrap">
            <div class="row g-4">

                <!-- ==== LEFT COLUMN ==== -->
                <div class="col-lg-6">

                    <!-- ---- Profile photo ---- -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Profile photo</h2>
                            <p class="section-hint">Your avatar will reflect in the top navigation bar.</p>
                        </div>

                        <?php if ($profileSuccess): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($profileSuccess) ?></div>
                        <?php endif; ?>

                        <div class="avatar-row">
                            <?php if ($photoPath): ?>
                                <img id="avatarPreview" src="<?= htmlspecialchars($photoPath) ?>" alt="Profile photo" class="avatar-preview">
                            <?php else: ?>
                                <div id="avatarPreview" class="avatar-preview">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                                </div>
                            <?php endif; ?>

                            <div>
                                <div class="avatar-name"><?= htmlspecialchars($displayName ?: 'Admin') ?></div>
                                <div class="avatar-status">
                                    <?= $photoPath ? 'Custom photo is active.' : 'No photo set — using default avatar.' ?>
                                </div>

                                <div class="avatar-actions">
                                    <form method="post" enctype="multipart/form-data" class="needs-validation" id="profileForm" novalidate>
                                        <input type="hidden" name="form_type" value="profile">
                                        <input type="hidden" name="full_name" value="<?= htmlspecialchars($fullNameInput) ?>">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($emailInput) ?>">
                                        <label for="profilePhotoInput" class="btn btn-outline-secondary btn-sm">Upload new photo</label>
                                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/png, image/jpeg, image/webp" hidden>
                                    </form>

                                    <?php if ($photoPath): ?>
                                        <form method="post" onsubmit="return confirm('Remove your profile photo?');">
                                            <input type="hidden" name="form_type" value="remove_photo">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="invalid-feedback d-block" id="photoError"><?= htmlspecialchars($profileErrors['profile_photo'] ?? '') ?></div>
                            </div>
                        </div>

                        <div class="form-hint">Supports JPEG, PNG, or WEBP. Maximum size 2MB.</div>
                    </div>

                    <!-- ---- Account details ---- -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Account details</h2>
                            <p class="section-hint">Your registered administrator profile and system role.</p>
                        </div>

                        <div class="meta-row">
                            <span class="meta-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                                Full name
                            </span>
                            <span class="meta-value"><?= htmlspecialchars($displayName ?: '—') ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25zM3.5 7l8.5 6 8.5-6"/></svg>
                                Email address
                            </span>
                            <span class="meta-value"><?= htmlspecialchars($displayEmail ?: '—') ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>
                                System role
                            </span>
                            <span class="meta-value"><span class="role-badge"><?= htmlspecialchars($displayRole) ?></span></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                Last login
                            </span>
                            <span class="meta-value"><?= htmlspecialchars($lastLoginDisplay) ?></span>
                        </div>
                    </div>

                    <!-- Editable name/email lives in the form below so the photo card's
                         hidden inputs above always submit the current values -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Edit profile</h2>
                            <p class="section-hint">Update your name or email address.</p>
                        </div>

                        <form method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="form_type" value="profile">

                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input
                                    type="text"
                                    class="form-control <?= isset($profileErrors['full_name']) ? 'is-invalid' : '' ?>"
                                    id="fullName"
                                    name="full_name"
                                    value="<?= htmlspecialchars($fullNameInput) ?>"
                                    required
                                    minlength="2"
                                    maxlength="100"
                                    pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s'\-.]+"
                                    title="Letters, spaces, hyphens, apostrophes, and periods only.">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($profileErrors['full_name'] ?? 'Please enter your full name (letters only, 2–100 characters).') ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input
                                    type="email"
                                    class="form-control <?= isset($profileErrors['email']) ? 'is-invalid' : '' ?>"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars($emailInput) ?>"
                                    required
                                    maxlength="150">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($profileErrors['email'] ?? 'Please enter a valid email address.') ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </form>
                    </div>

                </div>

                <!-- ==== RIGHT COLUMN ==== -->
                <div class="col-lg-6">

                    <!-- ---- Change password ---- -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Change password</h2>
                            <p class="section-hint">Update the password used to sign in.</p>
                        </div>

                        <?php if ($passwordSuccess): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($passwordSuccess) ?></div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" id="passwordForm" novalidate>
                            <input type="hidden" name="form_type" value="password">

                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Current password</label>
                                <input
                                    type="password"
                                    class="form-control <?= isset($passwordErrors['current_password']) ? 'is-invalid' : '' ?>"
                                    id="currentPassword"
                                    name="current_password"
                                    placeholder="Enter current password"
                                    required>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($passwordErrors['current_password'] ?? 'Please enter your current password.') ?>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="newPassword" class="form-label">New password</label>
                                    <input
                                        type="password"
                                        class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>"
                                        id="newPassword"
                                        name="new_password"
                                        placeholder="At least 8 characters"
                                        required
                                        minlength="8"
                                        maxlength="72"
                                        pattern="(?=.*[A-Za-z])(?=.*\d).{8,}"
                                        title="At least 8 characters, including at least one letter and one number.">
                                    <div class="invalid-feedback">
                                        <?= htmlspecialchars($passwordErrors['new_password'] ?? 'Password must be at least 8 characters and include a letter and a number.') ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="confirmPassword" class="form-label">Confirm new password</label>
                                    <input
                                        type="password"
                                        class="form-control <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                                        id="confirmPassword"
                                        name="confirm_password"
                                        placeholder="Re-enter new password"
                                        required
                                        minlength="8"
                                        maxlength="72">
                                    <div class="invalid-feedback" id="confirmPasswordFeedback">
                                        <?= htmlspecialchars($passwordErrors['confirm_password'] ?? 'Passwords do not match.') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="password-requirements">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zM8 11V7a4 4 0 1 1 8 0v4"/></svg>
                                <div>
                                    <h3>Password requirements</h3>
                                    <ul>
                                        <li>At least 8 characters long</li>
                                        <li>Includes at least one letter and one number</li>
                                        <li>Different from your current password</li>
                                    </ul>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4">Update Password</button>
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
<script>
    // ---- Auto-submit the photo form as soon as a file is chosen, with client-side checks ----
    const photoInput = document.getElementById('profilePhotoInput');
    const photoError = document.getElementById('photoError');
    const MAX_PHOTO_BYTES = 2 * 1024 * 1024;
    const ALLOWED_PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    photoInput.addEventListener('change', function () {
        const file = this.files[0];
        photoError.textContent = '';
        if (!file) return;

        if (!ALLOWED_PHOTO_TYPES.includes(file.type)) {
            photoError.textContent = 'Profile photo must be a JPG, PNG, or WEBP image.';
            this.value = '';
            return;
        }
        if (file.size > MAX_PHOTO_BYTES) {
            photoError.textContent = 'Profile photo must be 2MB or smaller.';
            this.value = '';
            return;
        }

        document.getElementById('profileForm').submit();
    });

    // ---- Bootstrap-style validation feedback for both forms ----
    document.querySelectorAll('.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ---- Live "passwords match" check on the change-password form ----
    const newPasswordField = document.getElementById('newPassword');
    const confirmPasswordField = document.getElementById('confirmPassword');
    const confirmFeedback = document.getElementById('confirmPasswordFeedback');

    function checkPasswordsMatch() {
        if (confirmPasswordField.value && newPasswordField.value !== confirmPasswordField.value) {
            confirmPasswordField.setCustomValidity('Passwords do not match.');
            confirmFeedback.textContent = 'Passwords do not match.';
        } else {
            confirmPasswordField.setCustomValidity('');
        }
    }

    newPasswordField.addEventListener('input', checkPasswordsMatch);
    confirmPasswordField.addEventListener('input', checkPasswordsMatch);
</script>
</body>
</html>
