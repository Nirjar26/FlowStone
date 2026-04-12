<?php
require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI: php backend/scripts/seed_summary.php\n";
    exit(1);
}

$tables = ['tasks', 'approvals', 'resources', 'notifications'];

foreach ($tables as $table) {
    echo strtoupper($table) . PHP_EOL;

    $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
            FROM {$table}
            WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
            GROUP BY ym
            ORDER BY ym";

    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($rows as $row) {
        echo $row['ym'] . ': ' . $row['c'] . PHP_EOL;
    }

    echo PHP_EOL;
}
