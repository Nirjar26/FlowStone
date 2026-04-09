<?php
require_once 'config.php';

echo "Creating Tasks Management Schema\n";
echo "=================================\n\n";

try {
    flowstone_execute_sql_file($pdo, __DIR__ . '/tasks_schema.sql');
    
    echo "\n✓ Tasks schema created successfully!\n\n";
    
    // Verify the tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'tasks'");
    if ($stmt->rowCount() > 0) {
        echo "Tables created:\n";
        echo "  - tasks\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  - Sample tasks: {$result['count']}\n";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'task_comments'");
    if ($stmt->rowCount() > 0) {
        echo "  - task_comments\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM task_comments");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  - Sample comments: {$result['count']}\n";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'task_attachments'");
    if ($stmt->rowCount() > 0) {
        echo "  - task_attachments\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM task_attachments");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  - Sample attachments: {$result['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>