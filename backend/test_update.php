<?php
// Test the update profile endpoint
require_once 'config.php';

echo "Testing Update Profile Endpoint\n";
echo "================================\n\n";

// Simulate update request
$testData = [
    'id' => 1,
    'name' => 'Admin User Updated',
    'phone' => '+1 (555) 999-8888',
    'bio' => 'Updated bio: Senior system administrator with expertise in resource management.',
    'department' => 'IT Management'
];

echo "Test Data:\n";
print_r($testData);
echo "\n";

// Prepare the update
$updateFields = [];
$params = [];

if (isset($testData['name']) && !empty(trim($testData['name']))) {
    $updateFields[] = "name = ?";
    $params[] = trim($testData['name']);
}

if (isset($testData['phone'])) {
    $updateFields[] = "phone = ?";
    $params[] = !empty(trim($testData['phone'])) ? trim($testData['phone']) : null;
}

if (isset($testData['bio'])) {
    $updateFields[] = "bio = ?";
    $params[] = !empty(trim($testData['bio'])) ? trim($testData['bio']) : null;
}

if (isset($testData['department'])) {
    $updateFields[] = "department = ?";
    $params[] = !empty(trim($testData['department'])) ? trim($testData['department']) : null;
}

try {
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $params[] = $testData['id'];
    
    echo "SQL: $sql\n";
    echo "Params: " . implode(', ', $params) . "\n\n";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo "✓ Update executed successfully\n";
    echo "Rows affected: " . $stmt->rowCount() . "\n\n";
    
    // Fetch updated user
    $stmt = $pdo->prepare("SELECT id, email, name, phone, role, department, bio FROM users WHERE id = ?");
    $stmt->execute([$testData['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Updated User Profile:\n";
        foreach ($user as $key => $value) {
            echo "  $key: " . ($value ?? 'NULL') . "\n";
        }
    }
    
    // Reset to original values
    echo "\n\nResetting to original values...\n";
    $resetStmt = $pdo->prepare("
        UPDATE users 
        SET 
            name = 'Admin User',
            phone = '+1 (555) 123-4567',
            bio = 'Experienced system administrator with over 10 years in resource management and workflow optimization.',
            department = 'Management'
        WHERE id = ?
    ");
    $resetStmt->execute([1]);
    echo "✓ Reset complete\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>