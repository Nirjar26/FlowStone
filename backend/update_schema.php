<?php
// Update database schema to add profile fields
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=flowstone_db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Updating database schema...\n\n";
    
    // Add new columns to users table
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'User'",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "✓ Executed: " . substr($query, 0, 60) . "...\n";
        } catch (PDOException $e) {
            // Column might already exist, that's okay
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "⚠ Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Update existing user with profile data
    $stmt = $pdo->prepare("
        UPDATE users 
        SET 
            phone = ?,
            role = ?,
            department = ?,
            bio = ?
        WHERE email = ?
    ");
    
    $stmt->execute([
        '+1 (555) 123-4567',
        'Administrator',
        'Management',
        'Experienced system administrator with over 10 years in resource management and workflow optimization.',
        'admin@flowstone.com'
    ]);
    
    echo "\n✓ Updated user profile data\n";
    
    // Verify the update
    $stmt = $pdo->prepare("SELECT id, email, name, phone, role, department FROM users WHERE email = ?");
    $stmt->execute(['admin@flowstone.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "\nUser Profile:\n";
        echo "  ID: {$user['id']}\n";
        echo "  Name: {$user['name']}\n";
        echo "  Email: {$user['email']}\n";
        echo "  Phone: {$user['phone']}\n";
        echo "  Role: {$user['role']}\n";
        echo "  Department: {$user['department']}\n";
    }
    
    echo "\nDatabase schema updated successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>