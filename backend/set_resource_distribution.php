<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI: php set_resource_distribution.php --counts=30,100,65,3,78\n";
    exit(1);
}

$options = getopt('', ['counts::']);
$countsArg = $options['counts'] ?? '30,100,65,3,78';
$parts = array_map('trim', explode(',', $countsArg));

if (count($parts) !== 5) {
    echo "Provide exactly 5 values for Nov,Dec,Jan,Feb,Current month.\n";
    echo "Example: php set_resource_distribution.php --counts=30,100,65,3,78\n";
    exit(1);
}

$targets = array_map('intval', $parts);
foreach ($targets as $v) {
    if ($v < 0) {
        echo "Counts must be non-negative.\n";
        exit(1);
    }
}

try {
    $now = new DateTimeImmutable('now');
    $currentYear = (int) $now->format('Y');
    $currentMonth = (int) $now->format('n');

    $novYear = $currentMonth >= 11 ? $currentYear : $currentYear - 1;
    $decYear = $novYear;

    $months = [
        new DateTimeImmutable("{$novYear}-11-01 10:00:00"),
        new DateTimeImmutable("{$decYear}-12-01 10:00:00"),
        new DateTimeImmutable("{$currentYear}-01-01 10:00:00"),
        new DateTimeImmutable("{$currentYear}-02-01 10:00:00"),
        new DateTimeImmutable($now->format('Y-m-01 10:00:00')),
    ];

    $monthKeys = array_map(static function ($d) {
        return $d->format('Y-m');
    }, $months);

    $totalNeeded = array_sum($targets);

    $resourceIds = $pdo->query("SELECT id FROM resources ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $resourceIds = array_map('intval', $resourceIds);
    $existingCount = count($resourceIds);

    $pdo->beginTransaction();

    if ($existingCount < $totalNeeded) {
        $insert = $pdo->prepare("INSERT INTO resources (name, type, status, assigned_to, location, description) VALUES (?, ?, ?, ?, ?, ?)");
        $types = ['device', 'software', 'room', 'equipment'];
        $statuses = ['available', 'assigned', 'maintenance'];

        for ($i = $existingCount + 1; $i <= $totalNeeded; $i++) {
            $type = $types[array_rand($types)];
            $status = $statuses[array_rand($statuses)];
            $insert->execute([
                ucfirst($type) . " Resource {$i}",
                $type,
                $status,
                null,
                'Block ' . chr(rand(65, 68)) . ', Floor ' . rand(1, 5),
                'Generated to satisfy requested dashboard distribution.'
            ]);
        }

        $resourceIds = $pdo->query("SELECT id FROM resources ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        $resourceIds = array_map('intval', $resourceIds);
    }

    shuffle($resourceIds);

    $update = $pdo->prepare("UPDATE resources SET created_at = ?, updated_at = ? WHERE id = ?");

    // Place extra resources outside last 6 months so they do not affect chart.
    $extraCount = count($resourceIds) - $totalNeeded;
    for ($i = 0; $i < $extraCount; $i++) {
        $id = $resourceIds[$i];
        $oldDate = (new DateTimeImmutable('first day of -2 year'))->setDate((int) date('Y') - 2, rand(1, 12), rand(1, 26))->setTime(rand(8, 18), rand(0, 59), 0);
        $update->execute([
            $oldDate->format('Y-m-d H:i:s'),
            $oldDate->format('Y-m-d H:i:s'),
            $id
        ]);
    }

    $cursor = $extraCount;
    for ($m = 0; $m < 5; $m++) {
        $base = $months[$m];
        $target = $targets[$m];

        for ($j = 0; $j < $target; $j++) {
            $id = $resourceIds[$cursor++];
            $createdAt = $base
                ->setDate((int) $base->format('Y'), (int) $base->format('m'), rand(1, 26))
                ->setTime(rand(8, 18), rand(0, 59), 0);

            $updatedAt = $createdAt->modify('+' . rand(0, 10) . ' days');
            if ($updatedAt > $now) {
                $updatedAt = $now;
            }

            $update->execute([
                $createdAt->format('Y-m-d H:i:s'),
                $updatedAt->format('Y-m-d H:i:s'),
                $id
            ]);
        }
    }

    $pdo->commit();

    echo "Applied requested distribution (Nov,Dec,Jan,Feb,Current): " . implode(', ', $targets) . "\n";

    $summaryStmt = $pdo->query(" 
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month_key, COUNT(*) as cnt
        FROM resources
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_key
        ORDER BY month_key
    ");

    $summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Dashboard month summary:\n";
    foreach ($summary as $row) {
        echo $row['month_key'] . ': ' . $row['cnt'] . "\n";
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'Failed: ' . $e->getMessage() . "\n";
    exit(1);
}
