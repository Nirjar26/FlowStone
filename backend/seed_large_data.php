<?php
require_once 'config.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI.\n";
    echo "Example: php seed_large_data.php --users=120 --tasks=1500 --approvals=400 --notifications=1500 --activities=2000 --attachments=900 --truncate=0\n";
    exit(1);
}

function readIntOption(array $opts, string $key, int $default): int {
    if (!isset($opts[$key]) || $opts[$key] === false || $opts[$key] === '') {
        return $default;
    }
    $value = (int) $opts[$key];
    return $value < 0 ? 0 : $value;
}

function pick(array $arr) {
    return $arr[array_rand($arr)];
}

function pickWeightedIndex(array $weights): int {
    $total = array_sum($weights);
    if ($total <= 0) {
        return 0;
    }

    $roll = rand(1, $total);
    $running = 0;
    foreach ($weights as $idx => $weight) {
        $running += $weight;
        if ($roll <= $running) {
            return (int) $idx;
        }
    }

    return count($weights) - 1;
}

$options = getopt('', [
    'users::',
    'tasks::',
    'approvals::',
    'resources::',
    'notifications::',
    'activities::',
    'comments::',
    'attachments::',
    'truncate::',
]);

$usersCount = readIntOption($options, 'users', 80);
$tasksCount = readIntOption($options, 'tasks', 900);
$approvalsCount = readIntOption($options, 'approvals', 300);
$resourcesCount = readIntOption($options, 'resources', 180);
$notificationsCount = readIntOption($options, 'notifications', 1100);
$activitiesCount = readIntOption($options, 'activities', 1400);
$commentsCount = readIntOption($options, 'comments', 1200);
$attachmentsCount = readIntOption($options, 'attachments', 900);
$truncate = isset($options['truncate']) ? ((int) $options['truncate'] === 1) : false;

echo "FlowStone bulk seeder (real-world monthly + weekly)\n";
echo "===================================================\n";
echo "Users: {$usersCount}\n";
echo "Tasks: {$tasksCount}\n";
echo "Approvals: {$approvalsCount}\n";
echo "Resources: {$resourcesCount}\n";
echo "Notifications: {$notificationsCount}\n";
echo "Activities: {$activitiesCount}\n";
echo "Comments: {$commentsCount}\n";
echo "Attachments: {$attachmentsCount}\n";
echo "Truncate first: " . ($truncate ? 'Yes' : 'No') . "\n\n";

$requiredTables = [
    'users', 'tasks', 'task_comments', 'task_attachments', 'approvals', 'resources', 'notifications', 'activities'
];

foreach ($requiredTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() === 0) {
        echo "Missing table '{$table}'. Run setup first: php setup_database.php\n";
        exit(1);
    }
}

$firstNames = ['Aarav', 'Mia', 'Noah', 'Isha', 'Liam', 'Aanya', 'Ethan', 'Riya', 'Lucas', 'Diya', 'Mason', 'Nia', 'Arjun', 'Vihaan', 'Zara', 'Anaya', 'Reyansh', 'Nidhi', 'Kabir', 'Meera'];
$lastNames = ['Patel', 'Shah', 'Mehta', 'Trivedi', 'Joshi', 'Desai', 'Pandya', 'Bhatt', 'Kapoor', 'Verma', 'Rao', 'Singh', 'Sharma', 'Iyer', 'Nair', 'Mistry'];
$departments = ['Engineering', 'Design', 'Marketing', 'Sales', 'Operations', 'HR', 'Support', 'Finance'];
$roles = ['Developer', 'Designer', 'Manager', 'Coordinator', 'Analyst', 'Lead'];

$taskPrefixes = ['Weekly Sprint', 'Client Follow-up', 'Infra Check', 'Design Review', 'Bug Bash', 'Ops Cleanup', 'Release Prep', 'Roadmap Sync'];
$taskSubjects = ['API reliability', 'Landing page improvements', 'Access control updates', 'Data quality checks', 'Onboarding fixes', 'Dashboard polish', 'Notification optimization', 'Integration testing'];
$priorities = ['low', 'medium', 'high'];
$statuses = ['pending', 'in-progress', 'review', 'completed'];

$approvalTypes = ['Budget Request', 'Leave Request', 'Equipment Purchase', 'Training Request', 'Travel Request', 'Software License'];
$approvalStatuses = ['pending', 'approved', 'rejected'];

$resourceTypes = ['device', 'software', 'room', 'equipment'];
$resourceStatuses = ['available', 'assigned', 'maintenance'];

$notificationTypes = ['success', 'warning', 'info', 'task'];
$activityTypes = ['task_completed', 'task_assigned', 'approval_requested', 'resource_booked', 'comment_added', 'status_changed'];
$attachmentTypes = ['pdf', 'image', 'doc', 'other'];

$weekStart = new DateTimeImmutable('monday this week');
$weekEnd = $weekStart->modify('+6 days');
$reportingStart = (new DateTimeImmutable('first day of this month'))->modify('-11 months')->setTime(0, 0, 0);
$reportingEnd = (new DateTimeImmutable('last day of this month'))->setTime(23, 59, 59);
$now = new DateTimeImmutable();

$monthStarts = [];
for ($m = 0; $m < 12; $m++) {
    $monthStarts[] = $reportingStart->modify("+{$m} months");
}

// Slightly higher traffic in recent months for a more realistic trend.
$monthWeights = [5, 5, 6, 6, 7, 8, 8, 9, 10, 11, 13, 12];
$openStatuses = ['pending', 'in-progress', 'review'];

try {
    if ($truncate) {
        echo "Truncating existing data...\n";
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE activities');
        $pdo->exec('TRUNCATE TABLE notifications');
        $pdo->exec('TRUNCATE TABLE task_attachments');
        $pdo->exec('TRUNCATE TABLE task_comments');
        $pdo->exec('TRUNCATE TABLE tasks');
        $pdo->exec('TRUNCATE TABLE resources');
        $pdo->exec('TRUNCATE TABLE approvals');
        $pdo->exec('TRUNCATE TABLE users');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $pdo->beginTransaction();

    // Ensure one known admin for easy login.
    $adminEmail = 'admin@flowstone.com';
    $adminPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
    $adminInsert = $pdo->prepare(
        'INSERT INTO users (email, password, name, phone, role, department, bio, preferences) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $checkAdmin = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkAdmin->execute([$adminEmail]);
    $adminId = null;
    $existingAdmin = $checkAdmin->fetch(PDO::FETCH_ASSOC);
    if ($existingAdmin) {
        $adminId = (int) $existingAdmin['id'];
    } else {
        $adminInsert->execute([
            $adminEmail,
            $adminPasswordHash,
            'System Admin',
            '+1-555-0000',
            'Administrator',
            'Management',
            'Primary admin account for seeded data.',
            json_encode(['theme' => 'light', 'notifications' => true]),
        ]);
        $adminId = (int) $pdo->lastInsertId();
    }

    echo "Seeding users...\n";
    $userInsert = $pdo->prepare(
        'INSERT INTO users (email, password, name, phone, role, department, bio, preferences) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $usersCount; $i++) {
        $first = pick($firstNames);
        $last = pick($lastNames);
        $name = $first . ' ' . $last;
        $email = strtolower($first . '.' . $last . '.' . $i . '@flowstone.test');
        $phone = '+1-555-' . str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $role = pick($roles);
        $department = pick($departments);
        $bio = "{$role} in {$department} working on weekly delivery goals.";
        $preferences = json_encode([
            'theme' => rand(0, 1) ? 'dark' : 'light',
            'notifications' => true,
            'compactView' => (bool) rand(0, 1),
        ]);

        $userInsert->execute([
            $email,
            $adminPasswordHash,
            $name,
            $phone,
            $role,
            $department,
            $bio,
            $preferences,
        ]);
    }

    $users = $pdo->query('SELECT id, name, department FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $userIds = array_map(static function ($u) { return (int) $u['id']; }, $users);

    echo "Seeding tasks...\n";
    $taskInsert = $pdo->prepare(
        'INSERT INTO tasks (title, description, status, priority, assignee_id, created_by, deadline, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $tasksCount; $i++) {
        $assigneeId = pick($userIds);
        $createdBy = rand(1, 100) <= 80 ? $adminId : pick($userIds);

        $prefix = pick($taskPrefixes);
        $subject = pick($taskSubjects);
        $title = "{$prefix} #{$i}: {$subject}";
        $description = "Deliver {$subject} with planning notes, implementation details, and a handoff summary.";
        $priority = pick($priorities);

        // Guarantee at least one completed task per month for reporting (May -> current Apr pattern).
        if ($i <= 12) {
            $monthIndex = $i - 1;
            $status = 'completed';
        } else {
            $monthIndex = pickWeightedIndex($monthWeights);
            $monthsFromCurrent = 11 - $monthIndex;
            if ($monthsFromCurrent <= 1) {
                $completionChance = 35;
            } elseif ($monthsFromCurrent <= 3) {
                $completionChance = 50;
            } else {
                $completionChance = 68;
            }
            $status = rand(1, 100) <= $completionChance ? 'completed' : pick($openStatuses);
        }

        $monthStart = $monthStarts[$monthIndex]->setTime(0, 0, 0);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);
        if ($monthEnd > $now) {
            $monthEnd = $now;
        }

        $startTs = $monthStart->getTimestamp();
        $endTs = max($startTs, $monthEnd->getTimestamp());
        $createdAt = (new DateTimeImmutable())->setTimestamp(rand($startTs, $endTs));

        // Keep due dates and progress behavior close to real delivery flows.
        $deadline = $createdAt->modify('+' . rand(3, 18) . ' days')->format('Y-m-d');
        if ($status === 'completed') {
            $completionLagHours = rand(6, 24 * 20);
            $updatedAt = $createdAt->modify("+{$completionLagHours} hours");
            if ($updatedAt > $now) {
                $updatedAt = $now;
            }
        } else {
            $progressLagHours = rand(1, 24 * 5);
            $updatedAt = $createdAt->modify("+{$progressLagHours} hours");
            if ($updatedAt > $now) {
                $updatedAt = $now;
            }
        }

        $taskInsert->execute([
            $title,
            $description,
            $status,
            $priority,
            $assigneeId,
            $createdBy,
            $deadline,
            $createdAt->format('Y-m-d H:i:s'),
            $updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    $taskIds = $pdo->query('SELECT id FROM tasks')->fetchAll(PDO::FETCH_COLUMN);
    $taskIds = array_map('intval', $taskIds);

    echo "Seeding task comments...\n";
    $commentTemplates = [
        'Please add an update before standup.',
        'Looks good, pending final approval.',
        'Blocked by dependency, escalating.',
        'Completed testing for this item.',
        'Need better screenshots for documentation.',
        'Reviewed and approved from my side.',
    ];

    $commentInsert = $pdo->prepare('INSERT INTO task_comments (task_id, user_id, content) VALUES (?, ?, ?)');
    for ($i = 1; $i <= $commentsCount; $i++) {
        $commentInsert->execute([
            pick($taskIds),
            pick($userIds),
            pick($commentTemplates),
        ]);
    }

    echo "Seeding task attachments...\n";
    $attachmentInsert = $pdo->prepare(
        'INSERT INTO task_attachments (task_id, filename, file_type, file_size, file_path, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $attachmentsCount; $i++) {
        $taskId = pick($taskIds);
        $uploadedBy = pick($userIds);
        $fileType = pick($attachmentTypes);
        $filename = "attachment_{$taskId}_{$i}." . ($fileType === 'other' ? 'bin' : $fileType);
        $fileSize = rand(120, 4096) . ' KB';
        $filePath = '/uploads/attachments/' . $filename;

        $createdAt = (new DateTimeImmutable())->setTimestamp(rand($reportingStart->getTimestamp(), $reportingEnd->getTimestamp()));
        if ($createdAt > $now) {
            $createdAt = $now;
        }

        $attachmentInsert->execute([
            $taskId,
            $filename,
            $fileType,
            $fileSize,
            $filePath,
            $uploadedBy,
            $createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    echo "Seeding approvals...\n";
    $approvalInsert = $pdo->prepare(
        'INSERT INTO approvals (type, requested_by, department, description, status, approved_by, approved_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $approvalsCount; $i++) {
        $requester = pick($users);
        $status = pick($approvalStatuses);
        $approvedBy = $status === 'pending' ? null : $adminId;
        $approvedAt = $status === 'pending' ? null : (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $type = pick($approvalTypes);
        $description = "{$type} for week " . $weekStart->format('W') . " - request #{$i}.";

        $approvalInsert->execute([
            $type,
            (int) $requester['id'],
            $requester['department'],
            $description,
            $status,
            $approvedBy,
            $approvedAt,
        ]);
    }

    echo "Seeding resources...\n";
    $resourceInsert = $pdo->prepare(
        'INSERT INTO resources (name, type, status, assigned_to, location, description) VALUES (?, ?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $resourcesCount; $i++) {
        $type = pick($resourceTypes);
        $status = pick($resourceStatuses);
        $assignedTo = $status === 'assigned' ? pick($userIds) : null;
        $name = ucfirst($type) . " Resource {$i}";
        $location = 'Block ' . chr(rand(65, 68)) . ', Floor ' . rand(1, 5);
        $description = "Seeded {$type} resource for weekly operations and allocation tests.";

        $resourceInsert->execute([$name, $type, $status, $assignedTo, $location, $description]);
    }

    echo "Seeding notifications...\n";
    $notificationInsert = $pdo->prepare(
        'INSERT INTO notifications (user_id, type, title, message, is_read) VALUES (?, ?, ?, ?, ?)'
    );

    for ($i = 1; $i <= $notificationsCount; $i++) {
        $userId = pick($userIds);
        $type = pick($notificationTypes);
        $title = ucfirst($type) . " update #{$i}";
        $message = "Weekly notification for sprint week " . $weekStart->format('W') . ".";
        $isRead = rand(0, 100) <= 40 ? 1 : 0;
        $notificationInsert->execute([$userId, $type, $title, $message, $isRead]);
    }

    echo "Seeding activities...\n";
    $activityInsert = $pdo->prepare(
        'INSERT INTO activities (user_id, type, description, related_id, related_type) VALUES (?, ?, ?, ?, ?)'
    );

    $relatedTypes = ['task', 'approval', 'resource', 'comment'];

    for ($i = 1; $i <= $activitiesCount; $i++) {
        $userId = pick($userIds);
        $type = pick($activityTypes);
        $relatedType = pick($relatedTypes);
        $relatedId = $relatedType === 'task' ? pick($taskIds) : rand(1, max(10, $activitiesCount));
        $description = "{$type} activity logged during week " . $weekStart->format('W') . ".";

        $activityInsert->execute([$userId, $type, $description, $relatedId, $relatedType]);
    }

    $pdo->commit();

    echo "\nSeeding complete.\n";
    echo "Week window used: " . $weekStart->format('Y-m-d') . ' to ' . $weekEnd->format('Y-m-d') . "\n";
    echo "Reporting window used: " . $reportingStart->format('Y-m-d') . ' to ' . $reportingEnd->format('Y-m-d') . "\n\n";

    $counts = [
        'users', 'tasks', 'task_comments', 'task_attachments', 'approvals', 'resources', 'notifications', 'activities'
    ];
    foreach ($counts as $table) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo str_pad($table, 16) . ": {$count}\n";
    }

    echo "\nLogin you can use:\n";
    echo "Email: admin@flowstone.com\n";
    echo "Password: password123\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
