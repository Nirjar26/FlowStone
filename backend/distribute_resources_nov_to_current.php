<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI: php distribute_resources_nov_to_current.php\n";
    exit(1);
}

try {
    $resourceIds = $pdo->query("SELECT id FROM resources ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($resourceIds)) {
        echo "No resources found to redistribute.\n";
        exit(0);
    }

    // Build month anchors: Nov, Dec, Jan, Feb, current month.
    // For Jan/Feb/current we use current year; Nov/Dec are previous year when current month is Jan/Feb/Mar/etc.
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

    $update = $pdo->prepare("UPDATE resources SET created_at = ?, updated_at = ? WHERE id = ?");

    // Generate random-but-smooth monthly counts (not equal, not spiky).
    $count = count($resourceIds);
    $monthCount = count($months);
    $weights = [];
    for ($i = 0; $i < $monthCount; $i++) {
        // Keep randomness bounded for a natural line shape.
        $weights[] = rand(85, 115) / 100;
    }

    $weightSum = array_sum($weights);
    $targetCounts = [];
    for ($i = 0; $i < $monthCount; $i++) {
        $targetCounts[$i] = (int) floor(($weights[$i] / $weightSum) * $count);
    }

    // Reconcile rounding to exact total.
    $allocated = array_sum($targetCounts);
    $remaining = $count - $allocated;
    while ($remaining > 0) {
        $idx = array_rand($targetCounts);
        $targetCounts[$idx]++;
        $remaining--;
    }

    // Smooth adjacent jumps so chart doesn't look too up/down.
    $avg = $count / $monthCount;
    $maxJump = (int) ceil($avg * 0.35);
    for ($pass = 0; $pass < 3; $pass++) {
        for ($i = 1; $i < $monthCount; $i++) {
            $diff = $targetCounts[$i] - $targetCounts[$i - 1];
            if ($diff > $maxJump) {
                $shift = (int) floor(($diff - $maxJump) / 2);
                if ($targetCounts[$i] - $shift >= 1) {
                    $targetCounts[$i] -= $shift;
                    $targetCounts[$i - 1] += $shift;
                }
            } elseif ($diff < -$maxJump) {
                $shift = (int) floor((abs($diff) - $maxJump) / 2);
                if ($targetCounts[$i - 1] - $shift >= 1) {
                    $targetCounts[$i - 1] -= $shift;
                    $targetCounts[$i] += $shift;
                }
            }
        }
    }

    // Final sum correction after smoothing.
    $delta = $count - array_sum($targetCounts);
    while ($delta !== 0) {
        $idx = array_rand($targetCounts);
        if ($delta > 0) {
            $targetCounts[$idx]++;
            $delta--;
        } else {
            if ($targetCounts[$idx] > 1) {
                $targetCounts[$idx]--;
                $delta++;
            }
        }
    }

    // Shuffle IDs so month assignment is random across rows.
    shuffle($resourceIds);

    $idIndex = 0;
    for ($monthIndex = 0; $monthIndex < $monthCount; $monthIndex++) {
        $base = $months[$monthIndex];
        $toAssign = $targetCounts[$monthIndex];

        for ($j = 0; $j < $toAssign; $j++) {
            if (!isset($resourceIds[$idIndex])) {
                break;
            }

            $id = (int) $resourceIds[$idIndex];
            $day = rand(1, 26);
            $hour = rand(8, 18);
            $minute = rand(0, 59);

            $createdAt = $base->setDate(
                (int) $base->format('Y'),
                (int) $base->format('m'),
                $day
            )->setTime($hour, $minute, 0);

            $updatedAt = $createdAt->modify('+' . rand(0, 10) . ' days');
            if ($updatedAt > $now) {
                $updatedAt = $now;
            }

            $update->execute([
                $createdAt->format('Y-m-d H:i:s'),
                $updatedAt->format('Y-m-d H:i:s'),
                $id,
            ]);

            $idIndex++;
        }
    }

    echo "Redistributed {$count} resources from Nov to current month.\n";

    $summary = $pdo->query(" 
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month_key, COUNT(*) as cnt
        FROM resources
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_key
        ORDER BY month_key
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "Month distribution:\n";
    foreach ($summary as $row) {
        echo $row['month_key'] . ': ' . $row['cnt'] . "\n";
    }

} catch (Throwable $e) {
    echo 'Failed: ' . $e->getMessage() . "\n";
    exit(1);
}
