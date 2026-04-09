<?php
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }
}

function flowstone_execute_sql_file(PDO $pdo, string $sqlFile): void
{
    $sqlLines = file($sqlFile, FILE_IGNORE_NEW_LINES);

    if ($sqlLines === false) {
        throw new RuntimeException('Unable to read schema file: ' . $sqlFile);
    }

    $sql = implode("\n", array_filter($sqlLines, function ($line) {
        $trimmedLine = ltrim($line);

        if ($trimmedLine === '') {
            return false;
        }

        if (substr($trimmedLine, 0, 2) === '--') {
            return false;
        }

        if (substr($trimmedLine, 0, 1) === '#') {
            return false;
        }

        return true;
    }));

    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($statement) {
            return $statement !== '';
        }
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function flowstone_bootstrap_schema(PDO $pdo): void
{
    $requiredTables = [
        'users',
        'tasks',
        'task_comments',
        'task_attachments',
        'approvals',
        'resources',
        'notifications',
        'activities',
    ];

    $missingTables = [];

    foreach ($requiredTables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");

        if (!$stmt || $stmt->rowCount() === 0) {
            $missingTables[] = $table;
        }
    }

    if (!empty($missingTables)) {
        $schemaFile = __DIR__ . '/full_schema.sql';

        if (!file_exists($schemaFile)) {
            throw new RuntimeException('Schema file not found: ' . $schemaFile);
        }

        flowstone_execute_sql_file($pdo, $schemaFile);
    }
}

// Database configuration
$host = 'localhost';
$dbname = 'flowstone_db';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

try {
    $serverPdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $serverPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    flowstone_bootstrap_schema($pdo);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => 'Database initialization failed: ' . $e->getMessage()]);
    exit;
}
?>