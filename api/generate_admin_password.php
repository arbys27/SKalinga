<?php
/**
 * Admin Password Hash Generator
 * Use this to create secure bcrypt hashes for admin passwords
 * Run once, copy the hash, and update in the database
 */

// Generate a bcrypt hash for a password
$password = 'AdminPass123'; // Change this to your desired password

$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Password: " . $password . "\n";
echo "Bcrypt Hash: " . $hashedPassword . "\n\n";

// Use this SQL to insert/update:
echo "SQL for database insertion:\n";
echo "INSERT INTO admins (username, email, password_hash, role, status) \n";
echo "VALUES ('admin', 'admin@skalinga.local', '" . $hashedPassword . "', 'superadmin', 'active');\n";
?>
