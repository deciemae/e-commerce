<?php
require_once __DIR__ . '/includes/setup_guard.php';
requireCommandLineSetupApproval();
require_once 'config/db.php';
$conn = getConnection();
$conn->query("INSERT IGNORE INTO admin_security_settings (setting_name, setting_value) VALUES 
('mfa_enabled', '0'), 
('password_min_length', '8'), 
('password_require_special', '1'), 
('password_require_number', '1'), 
('password_require_uppercase', '1')");
echo "Inserted default security settings.";
