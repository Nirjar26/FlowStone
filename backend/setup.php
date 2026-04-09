<?php
// Database setup script
$host = 'localhost';
$username = 'root';
$password = ''; // Change if your MySQL has a password

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL\n";
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS flowstone_db");
    echo "✓ Database 'flowstone_db' created/verified\n";
    
    // Select the database
    $pdo->exec("USE flowstone_db");
    
    // Create users table
    $createTable = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($createTable);
    echo "✓ Table 'users' created/verified\n";
    
    // Generate password hash
    $passwordPlain = 'password123';
    $hashedPassword = password_hash($passwordPlain, PASSWORD_DEFAULT);
    
    // Insert sample user
    $stmt = $pdo->prepare("INSERT INTO users (email, password, name) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE password = ?, name = ?");
    $stmt->execute([
        'admin@flowstone.com',
        $hashedPassword,
        'Admin User',
        $hashedPassword,
        'Admin User'
    ]);
    
    echo "✓ Sample user inserted/updated\n";
    echo "\nLogin credentials:\n";
    echo "Email: admin@flowstone.com\n";
    echo "Password: password123\n";
    
    // Verify the user
    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE email = ?");
    $stmt->execute(['admin@flowstone.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "\n✓ User verified in database:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Name: {$user['name']}\n";
    }
    
    echo "\nSetup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>