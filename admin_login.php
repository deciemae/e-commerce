<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin_auth.php';

if (adminIsLoggedIn()) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = $_SESSION['admin_login_error'] ?? '';
unset($_SESSION['admin_login_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $conn = getConnection();
        
        // Fetch security settings
        $threshold = 5;
        $duration = 15;
        $settings_res = $conn->query("SELECT setting_name, setting_value FROM admin_security_settings");
        if ($settings_res) {
            while ($row = $settings_res->fetch_assoc()) {
                if ($row['setting_name'] === 'lockout_threshold') $threshold = (int)$row['setting_value'];
                if ($row['setting_name'] === 'lockout_duration') $duration = (int)$row['setting_value'];
            }
        }

        $stmt = $conn->prepare('SELECT email, password, failed_attempts, is_locked, locked_until FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin) {
            if ($admin['is_locked'] && $admin['locked_until']) {
                $locked_until_time = strtotime($admin['locked_until']);
                if (time() < $locked_until_time) {
                    $error = 'Account is locked. Please try again later.';
                    $conn->close();
                } else {
                    // Unlock account
                    $stmt = $conn->prepare("UPDATE admins SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE email = ?");
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $stmt->close();
                    $admin['failed_attempts'] = 0;
                    $admin['is_locked'] = 0;
                }
            }
        }

        if (!$error && $admin) {
            if (password_verify($password, $admin['password'])) {
                // Success, reset failed attempts
                if ($admin['failed_attempts'] > 0 || $admin['is_locked']) {
                    $stmt = $conn->prepare("UPDATE admins SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE email = ?");
                    $stmt->bind_param('s', $email);
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->close();
                adminLoginUser($admin['email']);
                header('Location: admin_dashboard.php');
                exit;
            } else {
                // Failed attempt
                $attempts = $admin['failed_attempts'] + 1;
                if ($attempts >= $threshold) {
                    $locked_until = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
                    $stmt = $conn->prepare("UPDATE admins SET failed_attempts = ?, is_locked = 1, locked_until = ? WHERE email = ?");
                    $stmt->bind_param('iss', $attempts, $locked_until, $email);
                    $stmt->execute();
                    $stmt->close();
                    $error = 'Account is locked due to too many failed login attempts. Please try again later.';
                } else {
                    $stmt = $conn->prepare("UPDATE admins SET failed_attempts = ? WHERE email = ?");
                    $stmt->bind_param('is', $attempts, $email);
                    $stmt->execute();
                    $stmt->close();
                    $error = 'Invalid admin email or password.';
                }
                $conn->close();
            }
        } elseif (!$error) {
            $error = 'Invalid admin email or password.';
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Bloom &amp; Basket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/design-system.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }

        .admin-login-card {
            width: min(100%, 440px);
            background: #fff;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            padding: 2rem;
        }

        .admin-login-brand {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .admin-login-brand h1 {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #1e293b;
            margin: 0;
        }

        .admin-login-brand small {
            display: block;
            margin-top: .4rem;
            color: #64748b;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .admin-credentials {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: .75rem .9rem;
            margin-top: 1rem;
            font-size: .82rem;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="admin-login-card">
        <div class="admin-login-brand">
            <h1>Bloom &amp; Basket</h1>
            <small>Admin Access</small>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? 'admin@bloomandbasket.com') ?>" required maxlength="255" autocomplete="username">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter admin password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}" title="Use at least 8 characters with letters, numbers, and special characters." autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary w-100">Login to Admin Panel</button>
        </form>
    </div>
</body>
</html>
