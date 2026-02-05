<?php
// Test password verification
$stored_hash = '$2y$10$N9qo8uLOickgx2ZMRZoMye4FxH.K5l7DxsTiUUmHupSCWH5GyFipy';
$test_passwords = [
    'AdminPass123',
    'admin',
    'Admin123',
    'password'
];

echo "Testing password verification:\n";
echo "Stored hash: " . $stored_hash . "\n\n";

foreach ($test_passwords as $password) {
    $result = password_verify($password, $stored_hash);
    echo "Password: '$password' => " . ($result ? "✓ MATCH" : "✗ NO MATCH") . "\n";
}

echo "\n\n--- Generating new hashes for known passwords ---\n";
$passwords = [
    'admin' => 'admin',
    'AdminPass123' => 'AdminPass123',
    'TestPassword123' => 'TestPassword123'
];

foreach ($passwords as $label => $pwd) {
    $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 10]);
    echo "Password: '$label'\n";
    echo "Hash: $hash\n";
    echo "Verify: " . (password_verify($pwd, $hash) ? "✓" : "✗") . "\n\n";
}
?>
