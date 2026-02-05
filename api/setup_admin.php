<?php
/**
 * Admin Account Setup Script
 * Run this once to set up admin account with correct password hash
 */

require_once 'db_connect.php';

// Define test credentials
$admin_username = 'admin';
$admin_email = 'admin@skalinga.local';
$admin_password = 'admin'; // This will be hashed
$admin_role = 'superadmin';

// Generate proper bcrypt hash
$password_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Generated hash for password '$admin_password':\n";
echo $password_hash . "\n";
echo "Hash length: " . strlen($password_hash) . "\n\n";

// Delete existing admin if any
$deleteStmt = $conn->prepare("DELETE FROM admins WHERE username = ?");
$deleteStmt->bind_param("s", $admin_username);
$deleteStmt->execute();
echo "Deleted existing admin account.\n\n";

// Insert new admin account
$insertStmt = $conn->prepare("INSERT INTO admins (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'active')");
$insertStmt->bind_param("ssss", $admin_username, $admin_email, $password_hash, $admin_role);

if ($insertStmt->execute()) {
    echo "✓ Admin account created successfully!\n";
    echo "Username: $admin_username\n";
    echo "Password: $admin_password\n";
    echo "Email: $admin_email\n";
    echo "Role: $admin_role\n";
} else {
    echo "✗ Error creating admin account: " . $insertStmt->error . "\n";
}

// Verify the account
$verifyStmt = $conn->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
$verifyStmt->bind_param("s", $admin_username);
$verifyStmt->execute();
$result = $verifyStmt->get_result();
$admin = $result->fetch_assoc();

echo "\n--- Database Verification ---\n";
echo "Stored hash: " . $admin['password_hash'] . "\n";
echo "Stored hash length: " . strlen($admin['password_hash']) . "\n";
echo "Password verify: " . (password_verify($admin_password, $admin['password_hash']) ? "✓ PASS" : "✗ FAIL") . "\n";

$conn->close();
?>
