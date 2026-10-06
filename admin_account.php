<?php
require_once __DIR__ . '/includes/session.php';
startApplicationSession();
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

$passwordPolicy = [
    'password_min_length' => 8,
    'password_require_special' => 1,
    'password_require_number' => 1,
    'password_require_uppercase' => 1,
];
$policyResult = $conn->query('SELECT setting_name, setting_value FROM admin_security_settings');
if ($policyResult) {
    while ($policyRow = $policyResult->fetch_assoc()) {
        if (array_key_exists($policyRow['setting_name'], $passwordPolicy)) {
            $passwordPolicy[$policyRow['setting_name']] = (int)$policyRow['setting_value'];
        }
    }
}
$passwordPolicy['password_min_length'] = min(72, max(8, (int)$passwordPolicy['password_min_length']));

// Keep whatever the admin typed on a failed submit so the form doesn't clear
$fullNameInput = $admin['full_name'] ?? '';
$emailInput = $admin['email'] ?? '';

// ---- Handle form submissions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminId) {
    adminRequireValidCsrf('admin_account.php');
    $formType = $_POST['form_type'] ?? '';

    if (!in_array($formType, ['profile', 'remove_photo', 'password'], true)) {
        adminSetFlash('error', 'Invalid account action.');
        adminRedirect('admin_account.php');
    }

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
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $tmpPath = (string)($_FILES['profile_photo']['tmp_name'] ?? '');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $tmpPath !== '' && is_uploaded_file($tmpPath) ? $finfo->file($tmpPath) : false;
                $originalExtension = strtolower(pathinfo((string)($_FILES['profile_photo']['name'] ?? ''), PATHINFO_EXTENSION));
                $maxBytes = 2 * 1024 * 1024; // 2MB

                if (!$mimeType || !isset($allowedTypes[$mimeType]) || !in_array($originalExtension, $allowedExtensions, true)) {
                    $profileErrors['profile_photo'] = 'Profile photo must be a JPG, PNG, or WEBP image.';
                } elseif ($_FILES['profile_photo']['size'] > $maxBytes) {
                    $profileErrors['profile_photo'] = 'Profile photo must be 2MB or smaller.';
                } else {
                    $ext = $allowedTypes[$mimeType];
                    $uploadDir = __DIR__ . '/assets/uploads/admins/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fileName = 'admin_' . bin2hex(random_bytes(16)) . '.' . $ext;
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
                $stmt = $conn->prepare('UPDATE admins SET full_name = ?, email = ?, photo_path = ? WHERE admin_id = ?');
                $stmt->bind_param('sssi', $fullNameInput, $emailInput, $newPhotoPath, $adminId);
            } else {
                $stmt = $conn->prepare('UPDATE admins SET full_name = ?, email = ? WHERE admin_id = ?');
                $stmt->bind_param('ssi', $fullNameInput, $emailInput, $adminId);
            }
            $saved = $stmt->execute();
            $stmt->close();

            if (!$saved) {
                adminRemoveStoredUpload($newPhotoPath, __DIR__ . '/assets/uploads/admins');
                $profileErrors['email'] = 'The profile could not be updated. The email may already be in use.';
            } else {
                if ($newPhotoPath) {
                    adminRemoveStoredUpload($admin['photo_path'] ?? null, __DIR__ . '/assets/uploads/admins');
                }

            $admin['full_name'] = $fullNameInput;
            $admin['email'] = $emailInput;
            if ($newPhotoPath) {
                $admin['photo_path'] = $newPhotoPath;
            }

            // Login is tracked by email, so keep the session in sync if it changed
                $_SESSION['admin_email'] = strtolower(trim($emailInput));
                logAdminActivity('Update Profile', 'Updated administrator profile details.');
                $conn->close();
                adminSetFlash('success', 'Your profile has been updated.');
                adminRedirect('admin_account.php');
            }
        }
    }

    // ---- Remove photo ----
    if ($formType === 'remove_photo') {
        adminRemoveStoredUpload($admin['photo_path'] ?? null, __DIR__ . '/assets/uploads/admins');
        $stmt = $conn->prepare('UPDATE admins SET photo_path = NULL WHERE admin_id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();

        logAdminActivity('Remove Profile Photo', 'Removed administrator profile photo.');
        $conn->close();
        adminSetFlash('success', 'Your profile photo has been removed.');
        adminRedirect('admin_account.php');
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
        } elseif (strlen($newPassword) < $passwordPolicy['password_min_length'] || strlen($newPassword) > 72) {
            $passwordErrors['new_password'] = 'New password must be between ' . $passwordPolicy['password_min_length'] . ' and 72 characters.';
        } elseif (password_verify($newPassword, $admin['password'] ?? '')) {
            $passwordErrors['new_password'] = 'New password must be different from your current password.';
        } elseif ($passwordPolicy['password_require_number'] && !preg_match('/\d/', $newPassword)) {
            $passwordErrors['new_password'] = 'New password must include at least one number.';
        } elseif ($passwordPolicy['password_require_uppercase'] && !preg_match('/[A-Z]/', $newPassword)) {
            $passwordErrors['new_password'] = 'New password must include at least one uppercase letter.';
        } elseif ($passwordPolicy['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $passwordErrors['new_password'] = 'New password must include at least one special character.';
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

            session_regenerate_id(true);
            logAdminActivity('Change Password', 'Changed administrator password.');
            $conn->close();
            adminSetFlash('success', 'Your password has been changed.');
            adminRedirect('admin_account.php');
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
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-ui">

<?php $activePage = ''; require_once 'includes/sidebar.php'; ?>

<div id="main-content">
    <?php $pageTitle = 'My Account'; require_once 'includes/admin_topbar.php'; ?>

    <div class="page-content">
        <div class="account-wrap">
            <div class="account-grid">

                <!-- ==== MAIN COLUMN: Profile Identity & Details ==== -->
                <div class="account-col-main">

                    <!-- Profile Information Card (Photo + Name + Email) -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Profile Information</h2>
                            <p class="section-hint">Update your profile picture, display name, and contact email.</p>
                        </div>

                        <?php if ($profileSuccess): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                <span><?= htmlspecialchars($profileSuccess) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Avatar Photo Section -->
                        <div class="avatar-row mb-4 pb-4 border-bottom">
                            <?php if ($photoPath): ?>
                                <img id="avatarPreview" src="<?= htmlspecialchars($photoPath) ?>" alt="Profile photo" class="avatar-preview">
                            <?php else: ?>
                                <div id="avatarPreview" class="avatar-preview">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                                </div>
                            <?php endif; ?>

                            <div class="avatar-info">
                                <div class="avatar-name"><?= htmlspecialchars($displayName ?: 'Administrator') ?></div>
                                <div class="avatar-status">
                                    <?= $photoPath ? 'Custom photo is active and shown in the top navigation.' : 'No photo uploaded yet &mdash; using default avatar.' ?>
                                </div>

                                <div class="avatar-actions">
                                    <form method="post" enctype="multipart/form-data" class="needs-validation" id="profileForm" novalidate>
                                        <?= adminCsrfInput() ?>
                                        <input type="hidden" name="form_type" value="profile">
                                        <input type="hidden" name="full_name" value="<?= htmlspecialchars($fullNameInput) ?>">
                                        <input type="hidden" name="email" value="<?= htmlspecialchars($emailInput) ?>">
                                        <label for="profilePhotoInput" class="btn btn-outline-secondary btn-sm">Upload Photo</label>
                                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/png, image/jpeg, image/webp" hidden>
                                    </form>

                                    <?php if ($photoPath): ?>
                                        <form method="post" onsubmit="return confirm('Remove your profile photo?');">
                                            <?= adminCsrfInput() ?>
                                            <input type="hidden" name="form_type" value="remove_photo">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="invalid-feedback d-block mt-2" id="photoError"><?= htmlspecialchars($profileErrors['profile_photo'] ?? '') ?></div>
                                <div class="form-hint">Supports JPG, PNG, or WEBP up to 2MB.</div>
                            </div>
                        </div>

                        <!-- Name & Email Form -->
                        <form method="post" class="needs-validation" novalidate>
                            <?= adminCsrfInput() ?>
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

                            <div class="mb-4">
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
                                <div class="form-hint">Your email is used to log in and receive administrative alerts.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save Profile Changes</button>
                        </form>
                    </div>

                </div>

                <!-- ==== SIDE COLUMN: Account Status & Password Security ==== -->
                <div class="account-col-side">

                    <!-- Account Overview Details -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Account Overview</h2>
                            <p class="section-hint">System credentials and access role.</p>
                        </div>

                        <div class="meta-list">
                            <div class="meta-row">
                                <span class="meta-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                                    Display Name
                                </span>
                                <span class="meta-value"><?= htmlspecialchars($displayName ?: '—') ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25zM3.5 7l8.5 6 8.5-6"/></svg>
                                    Email
                                </span>
                                <span class="meta-value"><?= htmlspecialchars($displayEmail ?: '—') ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/></svg>
                                    Role
                                </span>
                                <span class="meta-value"><span class="role-badge"><?= htmlspecialchars($displayRole) ?></span></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                    Last Login
                                </span>
                                <span class="meta-value"><?= htmlspecialchars($lastLoginDisplay) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="account-card">
                        <div class="account-card-header">
                            <h2>Change Password</h2>
                            <p class="section-hint">Update your account security password.</p>
                        </div>

                        <?php if ($passwordSuccess): ?>
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                                <span><?= htmlspecialchars($passwordSuccess) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="needs-validation" id="passwordForm" novalidate>
                            <?= adminCsrfInput() ?>
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

                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New password</label>
                                <input
                                    type="password"
                                    class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>"
                                    id="newPassword"
                                    name="new_password"
                                    placeholder="At least <?= (int)$passwordPolicy['password_min_length'] ?> characters"
                                    required
                                    minlength="<?= (int)$passwordPolicy['password_min_length'] ?>"
                                    maxlength="72"
                                    title="Follow the password requirements shown below.">
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($passwordErrors['new_password'] ?? 'Password must follow policy requirements.') ?>
                                </div>
                            </div>

                            <div class="mb-3">
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

                            <div class="password-requirements">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zM8 11V7a4 4 0 1 1 8 0v4"/></svg>
                                <div>
                                    <h3>Password requirements</h3>
                                    <ul>
                                        <li>At least <?= (int)$passwordPolicy['password_min_length'] ?> characters long</li>
                                        <?php if ($passwordPolicy['password_require_number']): ?><li>Includes at least one number</li><?php endif; ?>
                                        <?php if ($passwordPolicy['password_require_uppercase']): ?><li>Includes at least one uppercase letter</li><?php endif; ?>
                                        <?php if ($passwordPolicy['password_require_special']): ?><li>Includes at least one special character</li><?php endif; ?>
                                        <li>Different from your current password</li>
                                    </ul>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4 w-100">Update Password</button>
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
