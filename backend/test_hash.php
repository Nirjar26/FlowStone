<?php
// Generate a fresh password hash for password123
$password = 'password123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\n";

// Verify it works
if (password_verify($password, $hash)) {
    echo "✓ Hash verification successful!\n";
} else {
    echo "✗ Hash verification failed!\n";
}
?>