<?php
// Database Setup Script
// Run this file directly in your browser: http://localhost:8000/setup_database.php

require_once 'config.php';

echo "<h1>FlowStone Database Setup</h1>";
echo "<pre>";

try {
    $sqlFile = __DIR__ . '/full_schema.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    echo "Found schema file: full_schema.sql\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    flowstone_execute_sql_file($pdo, $sqlFile);
    
    $successCount = 1;
        
    echo "\n";
    echo "========================================\n";
    echo "Database Setup Complete!\n";
    echo "========================================\n";
    echo "Successful operations: $successCount\n";
    echo "Errors (non-critical): $errorCount\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    $tables = ['users', 'tasks', 'task_comments', 'task_attachments', 'approvals', 
               'resources', 'notifications', 'activities'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "✓ Table '$table' exists with $count records\n";
        } else {
            echo "✗ Table '$table' NOT FOUND\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Setup successful! You can now use the application.\n";
    echo "========================================\n\n";
    
    echo "Default login credentials:\n";
    echo "Email: admin@flowstone.com\n";
    echo "Password: password123\n\n";
    
    echo "Other test users:\n";
    echo "- sarah@flowstone.com (password123)\n";
    echo "- mike@flowstone.com (password123)\n";
    echo "- emily@flowstone.com (password123)\n";
    echo "- david@flowstone.com (password123)\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='/'>← Back to Application</a></p>";
?>
