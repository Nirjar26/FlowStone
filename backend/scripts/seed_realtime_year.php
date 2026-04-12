<?php
require_once __DIR__ . '/../config.php';

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI: php backend/scripts/seed_realtime_year.php [--append]\n";
    exit(1);
}

$appendMode = in_array('--append', $argv, true);

function random_datetime_in_month(DateTimeImmutable $monthStart): DateTimeImmutable
{
    $daysInMonth = (int) $monthStart->format('t');
    $day = random_int(1, $daysInMonth);
    $hour = random_int(8, 19);
    $minute = random_int(0, 59);

    return $monthStart->setDate(
        (int) $monthStart->format('Y'),
        (int) $monthStart->format('m'),
        $day
    )->setTime($hour, $minute, 0);
}

function clamp_to_now(DateTimeImmutable $value, DateTimeImmutable $now): DateTimeImmutable
{
    return $value > $now ? $now : $value;
}

try {
    if (!$appendMode) {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE activities');
        $pdo->exec('TRUNCATE TABLE notifications');
        $pdo->exec('TRUNCATE TABLE task_attachments');
        $pdo->exec('TRUNCATE TABLE task_comments');
        $pdo->exec('TRUNCATE TABLE tasks');
        $pdo->exec('TRUNCATE TABLE approvals');
        $pdo->exec('TRUNCATE TABLE resources');
        $pdo->exec('TRUNCATE TABLE users');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $pdo->beginTransaction();

    $passwordPlain = 'password123';
    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

    $users = [
        ['admin@flowstone.com', 'Admin User', 'Administrator', 'Management'],
        ['ops.lead@flowstone.com', 'Ops Lead', 'Member', 'Operations'],
        ['it.lead@flowstone.com', 'IT Lead', 'Member', 'IT'],
        ['hr.rep@flowstone.com', 'HR Representative', 'Member', 'HR'],
        ['finance.rep@flowstone.com', 'Finance Rep', 'Member', 'Finance'],
        ['project.coord@flowstone.com', 'Project Coordinator', 'Member', 'Projects'],
        ['support.exec@flowstone.com', 'Support Executive', 'Member', 'Support'],
    ];

    $insertUser = $pdo->prepare('INSERT INTO users (email, password, name, role, department, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $userIds = [];
    $userMeta = [];
    $now = new DateTimeImmutable('now');

    foreach ($users as $index => $user) {
        $createdAt = $now->modify('-' . (20 - $index) . ' months')->format('Y-m-d H:i:s');
        $insertUser->execute([$user[0], $passwordHash, $user[1], $user[2], $user[3], $createdAt]);
        $id = (int) $pdo->lastInsertId();
        $userIds[] = $id;
        $userMeta[$id] = ['name' => $user[1], 'department' => $user[3], 'role' => $user[2]];
    }

    $adminId = $userIds[0];
    $memberIds = array_slice($userIds, 1);

    $taskTitles = [
        'Prepare sprint backlog',
        'Review onboarding checklist',
        'Update access permissions',
        'Validate resource allocation plan',
        'Audit approval queue',
        'Compile weekly compliance report',
        'Resolve service desk escalation',
        'Coordinate release notes',
        'Refine QA signoff criteria',
        'Align cross-team handover plan',
    ];

    $approvalTypes = ['Budget Request', 'Procurement Request', 'Leave Request', 'Policy Exception', 'Access Change'];
    $resourceTypes = ['device', 'software', 'room', 'equipment'];
    $resourcePrefixes = [
        'device' => ['Laptop', 'Desktop', 'Tablet', 'Workstation'],
        'software' => ['Adobe License', 'IDE Seat', 'Analytics Subscription', 'Security Suite'],
        'room' => ['Meeting Room', 'War Room', 'Training Room', 'Workshop Space'],
        'equipment' => ['Projector', 'Camera Kit', 'Printer', 'Testing Rig'],
    ];

    $insertTask = $pdo->prepare(
        'INSERT INTO tasks (title, description, status, priority, assignee_id, created_by, deadline, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertComment = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, content, created_at) VALUES (?, ?, ?, ?)');
    $insertApproval = $pdo->prepare(
        'INSERT INTO approvals (type, requested_by, department, description, status, approved_by, approved_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertResource = $pdo->prepare(
        'INSERT INTO resources (name, type, status, assigned_to, location, description, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertNotification = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertActivity = $pdo->prepare(
        'INSERT INTO activities (user_id, type, description, related_id, related_type, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $totalTasks = 0;
    $totalApprovals = 0;
    $totalResources = 0;

    // Seed last 12 months relative to current month.
    for ($offset = 11; $offset >= 0; $offset--) {
        $monthStart = (new DateTimeImmutable('first day of this month'))->modify('-' . $offset . ' months')->setTime(9, 0, 0);

        $tasksPerMonth = random_int(4, 5);
        for ($i = 0; $i < $tasksPerMonth; $i++) {
            $createdAt = random_datetime_in_month($monthStart);
            $assigneeId = $memberIds[array_rand($memberIds)];
            $createdBy = random_int(0, 100) < 70 ? $adminId : $memberIds[array_rand($memberIds)];

            $priorityRoll = random_int(1, 100);
            $priority = $priorityRoll <= 30 ? 'low' : ($priorityRoll <= 75 ? 'medium' : 'high');

            $statusRoll = random_int(1, 100);
            if ($statusRoll <= 45) {
                $status = 'completed';
            } elseif ($statusRoll <= 70) {
                $status = 'in-progress';
            } elseif ($statusRoll <= 85) {
                $status = 'review';
            } else {
                $status = 'pending';
            }

            $deadline = $createdAt->modify('+' . random_int(5, 20) . ' days');

            // Priority influences completion time to create meaningful complexity trends.
            if ($status === 'completed') {
                $baseDuration = $priority === 'high' ? random_int(8, 20) : ($priority === 'medium' ? random_int(4, 12) : random_int(2, 8));
                if (random_int(1, 100) <= 12) {
                    $baseDuration += random_int(10, 20); // outliers
                }
                $updatedAt = clamp_to_now($createdAt->modify('+' . $baseDuration . ' days'), $now);
            } else {
                $ageDays = random_int(1, 18);
                if (random_int(1, 100) <= 25) {
                    $ageDays += random_int(7, 15); // intentionally "stuck" tasks
                }
                $updatedAt = clamp_to_now($createdAt->modify('+' . $ageDays . ' days'), $now);
                if ($deadline > $now && random_int(1, 100) <= 35) {
                    $deadline = $createdAt->modify('+' . random_int(3, 7) . ' days');
                }
            }

            $title = $taskTitles[array_rand($taskTitles)];
            $description = 'Generated task for realistic monthly trend analysis and workload simulation.';

            $insertTask->execute([
                $title,
                $description,
                $status,
                $priority,
                $assigneeId,
                $createdBy,
                $deadline->format('Y-m-d'),
                $createdAt->format('Y-m-d H:i:s'),
                $updatedAt->format('Y-m-d H:i:s'),
            ]);
            $taskId = (int) $pdo->lastInsertId();
            $totalTasks++;

            if (random_int(1, 100) <= 65) {
                $commentAt = clamp_to_now($createdAt->modify('+' . random_int(0, 5) . ' days'), $now);
                $insertComment->execute([
                    $taskId,
                    $assigneeId,
                    'Progress update added by seeded data generator.',
                    $commentAt->format('Y-m-d H:i:s'),
                ]);
            }

            $activityType = $status === 'completed' ? 'task_completed' : 'task_assigned';
            $insertActivity->execute([
                $createdBy,
                $activityType,
                $status === 'completed'
                    ? ('completed ' . $title)
                    : ('assigned ' . $title . ' to ' . $userMeta[$assigneeId]['name']),
                $taskId,
                'task',
                $updatedAt->format('Y-m-d H:i:s'),
            ]);
        }

        $approvalsPerMonth = random_int(4, 5);
        for ($i = 0; $i < $approvalsPerMonth; $i++) {
            $createdAt = random_datetime_in_month($monthStart);
            $requestedBy = $memberIds[array_rand($memberIds)];
            $statusRoll = random_int(1, 100);
            $status = $statusRoll <= 40 ? 'approved' : ($statusRoll <= 70 ? 'pending' : 'rejected');

            $approvedBy = null;
            $approvedAt = null;
            $updatedAt = $createdAt;

            if ($status !== 'pending') {
                $approvedBy = $adminId;
                $approvedAt = clamp_to_now($createdAt->modify('+' . random_int(1, 10) . ' days'), $now);
                $updatedAt = $approvedAt;
            }

            $insertApproval->execute([
                $approvalTypes[array_rand($approvalTypes)],
                $requestedBy,
                $userMeta[$requestedBy]['department'],
                'Seeded approval request generated for monthly trend realism.',
                $status,
                $approvedBy,
                $approvedAt ? $approvedAt->format('Y-m-d H:i:s') : null,
                $createdAt->format('Y-m-d H:i:s'),
                $updatedAt->format('Y-m-d H:i:s'),
            ]);
            $approvalId = (int) $pdo->lastInsertId();
            $totalApprovals++;

            $insertActivity->execute([
                $requestedBy,
                'approval_requested',
                'submitted ' . $status . ' approval request',
                $approvalId,
                'approval',
                $createdAt->format('Y-m-d H:i:s'),
            ]);
        }

        $resourcesPerMonth = random_int(4, 5);
        for ($i = 0; $i < $resourcesPerMonth; $i++) {
            $createdAt = random_datetime_in_month($monthStart);
            $type = $resourceTypes[array_rand($resourceTypes)];
            $statusRoll = random_int(1, 100);
            $status = $statusRoll <= 50 ? 'assigned' : ($statusRoll <= 80 ? 'available' : 'maintenance');
            $assignedTo = $status === 'assigned' ? $memberIds[array_rand($memberIds)] : null;
            $updatedAt = clamp_to_now($createdAt->modify('+' . random_int(0, 20) . ' days'), $now);

            $prefix = $resourcePrefixes[$type][array_rand($resourcePrefixes[$type])];
            $resourceName = $prefix . ' ' . $monthStart->format('M') . '-' . random_int(100, 999);

            $insertResource->execute([
                $resourceName,
                $type,
                $status,
                $assignedTo,
                'Block ' . chr(random_int(65, 70)) . ', Floor ' . random_int(1, 5),
                'Seeded resource for utilization and distribution analytics.',
                $createdAt->format('Y-m-d H:i:s'),
                $updatedAt->format('Y-m-d H:i:s'),
            ]);
            $resourceId = (int) $pdo->lastInsertId();
            $totalResources++;

            $insertActivity->execute([
                $adminId,
                'resource_booked',
                'updated resource allocation for ' . $resourceName,
                $resourceId,
                'resource',
                $updatedAt->format('Y-m-d H:i:s'),
            ]);
        }

        $notificationsPerMonth = random_int(4, 5);
        for ($i = 0; $i < $notificationsPerMonth; $i++) {
            $createdAt = random_datetime_in_month($monthStart);
            $userId = $userIds[array_rand($userIds)];
            $notifType = ['success', 'warning', 'info', 'task'][array_rand(['success', 'warning', 'info', 'task'])];

            $insertNotification->execute([
                $userId,
                $notifType,
                'System activity update',
                'Generated monthly notification event for timeline realism.',
                random_int(0, 1),
                $createdAt->format('Y-m-d H:i:s'),
            ]);
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo "Yearly realtime-like seed complete.\n";
    echo "Mode: " . ($appendMode ? 'append' : 'reset') . "\n";
    echo "Users: " . count($userIds) . "\n";
    echo "Tasks: {$totalTasks}\n";
    echo "Approvals: {$totalApprovals}\n";
    echo "Resources: {$totalResources}\n";
    echo "Notifications: generated for each month\n";
    echo "\nLogin credentials:\n";
    echo "Email: admin@flowstone.com\n";
    echo "Password: {$passwordPlain}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $ignored) {
    }

    echo 'Failed to seed realtime year data: ' . $e->getMessage() . "\n";
    exit(1);
}
