<?php
require_once __DIR__ . '/includes/setup_guard.php';
requireCommandLineSetupApproval();
require_once 'config/db.php';
$conn = getConnection();

$conn->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_email VARCHAR(255) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
if ($conn->error) echo "Error: " . $conn->error . "\n";
else echo "admin_activity_logs table created\n";

$conn->query("ALTER TABLE admins ADD COLUMN failed_attempts INT DEFAULT 0, ADD COLUMN is_locked TINYINT(1) DEFAULT 0, ADD COLUMN locked_until DATETIME DEFAULT NULL");
if ($conn->error) echo "Error: " . $conn->error . "\n";
else echo "admins table altered\n";

$conn->query("ALTER TABLE admins ADD COLUMN lockout_threshold INT DEFAULT 3, ADD COLUMN lockout_duration INT DEFAULT 15");
if ($conn->error) echo "Error: " . $conn->error . "\n";
else echo "admins table altered again\n";

$conn->query("CREATE TABLE IF NOT EXISTS admin_security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL
)");
if ($conn->error) echo "Error: " . $conn->error . "\n";
else {
    echo "admin_security_settings table created\n";
    $conn->query("INSERT IGNORE INTO admin_security_settings (setting_name, setting_value) VALUES ('lockout_threshold', '5')");
    $conn->query("INSERT IGNORE INTO admin_security_settings (setting_name, setting_value) VALUES ('lockout_duration', '15')");
}
