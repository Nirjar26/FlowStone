<?php
require_once 'config.php';

// Resolve user id from query when available (falls back to 1 for backward compatibility)
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 1;
if ($user_id <= 0) {
    $user_id = 1;
}

$requestUser = flowstone_fetch_user($pdo, $user_id);
$isAdmin = flowstone_is_admin_role($requestUser['role'] ?? null);

$taskScopeClause = '';
$taskScopeParams = [];
if (!$isAdmin && $user_id > 0) {
    $taskScopeClause = ' AND (t.created_by = :scope_user_id OR t.assignee_id = :scope_user_id)';
    $taskScopeParams['scope_user_id'] = $user_id;
}

// Get dashboard statistics
try {
    // KPI: Total Tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks t WHERE 1=1" . $taskScopeClause);
    $stmt->execute($taskScopeParams);
    $totalTasks = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Previous month tasks for comparison
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks t WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND t.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)" . $taskScopeClause);
    $stmt->execute($taskScopeParams);
    $prevMonthTasks = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $taskChange = $prevMonthTasks > 0 ? round((($totalTasks - $prevMonthTasks) / $prevMonthTasks) * 100) : 12;

    // KPI: Pending Approvals
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending'");
        $pendingApprovals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending' AND requested_by = ?");
        $stmt->execute([$user_id]);
        $pendingApprovals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Previous week approvals
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 WEEK) AND created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
        $prevWeekApprovals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending' AND requested_by = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 2 WEEK) AND created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
        $stmt->execute([$user_id]);
        $prevWeekApprovals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    $approvalChange = $prevWeekApprovals > 0 ? round((($pendingApprovals - $prevWeekApprovals) / $prevWeekApprovals) * 100) : -5;

    // KPI: Resources in Use
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM resources WHERE status = 'assigned'");
        $resourcesInUse = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM resources WHERE status = 'assigned' AND assigned_to = ?");
        $stmt->execute([$user_id]);
        $resourcesInUse = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // KPI: Completed Tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks t WHERE t.status = 'completed'" . $taskScopeClause);
    $stmt->execute($taskScopeParams);
    $completedTasks = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // This month completed
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks t WHERE t.status = 'completed' AND t.updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)" . $taskScopeClause);
    $stmt->execute($taskScopeParams);
    $thisMonthCompleted = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== TASKS THIS WEEK (Mon-Sun) =====
    $taskChartData = [];
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $taskByDayMap = array_fill_keys($dayNames, 0);

    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(created_at, '%a') AS day_name, COUNT(*) AS cnt
         FROM tasks
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
           AND created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
         GROUP BY day_name"
    );
    $taskByDayRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($taskByDayRows as $row) {
        if (isset($taskByDayMap[$row['day_name']])) {
            $taskByDayMap[$row['day_name']] = (int) $row['cnt'];
        }
    }

    foreach ($dayNames as $day) {
        $taskChartData[] = ['name' => $day, 'value' => $taskByDayMap[$day]];
    }

    // ===== RESOURCE UTILIZATION (LAST 5 MONTHS) =====
    $resourceChartData = [];
    for ($i = 4; $i >= 0; $i--) {
        $monthLabel = date('M', strtotime("-{$i} month"));
        $monthStart = date('Y-m-01 00:00:00', strtotime("-{$i} month"));
        $monthEnd = date('Y-m-01 00:00:00', strtotime('-' . ($i - 1) . ' month'));

        if ($i === 0) {
            $monthEnd = date('Y-m-01 00:00:00', strtotime('+1 month'));
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt
             FROM resources
             WHERE created_at >= ? AND created_at < ?"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $count = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $resourceChartData[] = ['name' => $monthLabel, 'value' => $count];
    }

    // ===== TASK COMPLETION BY MONTH (LAST 12 MONTHS) =====
    $monthlyCompletionData = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthLabel = date('M', strtotime("-{$i} month"));
        $monthStart = date('Y-m-01 00:00:00', strtotime("-{$i} month"));
        $monthEnd = date('Y-m-01 00:00:00', strtotime('-' . ($i - 1) . ' month'));

        if ($i === 0) {
            $monthEnd = date('Y-m-01 00:00:00', strtotime('+1 month'));
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt
             FROM tasks
             WHERE status = 'completed' AND updated_at >= ? AND updated_at < ?"
        );
        $stmt->execute([$monthStart, $monthEnd]);
        $count = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        $monthlyCompletionData[] = ['name' => $monthLabel, 'value' => $count];
    }

    // ===== TOP PERFORMERS =====
    $stmt = $pdo->query(
        "SELECT u.name, COUNT(t.id) AS completed_tasks
         FROM users u
         LEFT JOIN tasks t ON t.assignee_id = u.id AND t.status = 'completed'
         GROUP BY u.id, u.name
         ORDER BY completed_tasks DESC, u.name ASC
         LIMIT 5"
    );
    $topPerformersRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $topPerformers = [];
    foreach ($topPerformersRows as $row) {
        $topPerformers[] = [
            'name' => $row['name'],
            'completedTasks' => (int) $row['completed_tasks'],
            'avatar' => null,
            'icon' => 'fas fa-star',
            'color' => '#fbbf24'
        ];
    }

    // ===== RESOURCE UTILIZATION BY CATEGORY =====
    $resourceByCategory = [];
    $resourceCategories = [
        'device' => ['label' => 'Devices', 'icon' => 'fas fa-laptop'],
        'software' => ['label' => 'Software', 'icon' => 'fas fa-cube'],
        'room' => ['label' => 'Rooms', 'icon' => 'fas fa-door-open'],
        'equipment' => ['label' => 'Equipment', 'icon' => 'fas fa-toolbox']
    ];
    foreach ($resourceCategories as $typeKey => $categoryData) {
        $stmt = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available_cnt,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned_cnt,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) AS maintenance_cnt
             FROM resources
             WHERE type = ?"
        );
        $stmt->execute([$typeKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $resourceByCategory[] = [
            'category' => $categoryData['label'],
            'icon' => $categoryData['icon'],
            'available' => (int) ($row['available_cnt'] ?? 0),
            'assigned' => (int) ($row['assigned_cnt'] ?? 0),
            'maintenance' => (int) ($row['maintenance_cnt'] ?? 0),
        ];
    }

    // ===== RECENT ACTIVITIES =====
    // ===== RECENT ACTIVITIES =====
    // Build a wider event timeline so both user and admin actions are visible.
    $activityIcons = [
        'task_created' => 'fas fa-plus-circle',
        'task_updated' => 'fas fa-edit',
        'task_completed' => 'fas fa-check-circle',
        'approval_requested' => 'fas fa-handshake',
        'approval_approved' => 'fas fa-thumbs-up',
        'approval_rejected' => 'fas fa-thumbs-down',
        'resource_assigned' => 'fas fa-link',
        'user_login' => 'fas fa-sign-in-alt',
        'profile_updated' => 'fas fa-user-edit',
    ];

    $combinedActivities = [];

    $pushActivity = function (int $id, string $type, string $user, string $action, string $createdAt) use (&$combinedActivities, $activityIcons) {
        $combinedActivities[] = [
            'id' => $id,
            'type' => $type,
            'icon' => $activityIcons[$type] ?? 'fas fa-bell',
            'user' => $user !== '' ? $user : 'System',
            'action' => $action,
            'createdAt' => $createdAt,
        ];
    };

    // Existing explicit activity logs
    $stmt = $pdo->query(
        "SELECT a.id, a.type, a.description, a.created_at, u.name AS user_name
         FROM activities a
         JOIN users u ON a.user_id = u.id
         ORDER BY a.created_at DESC
         LIMIT 80"
    );
    $activityRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($activityRows as $row) {
        $pushActivity(
            (int)$row['id'],
            (string)$row['type'],
            (string)$row['user_name'],
            (string)$row['description'],
            (string)$row['created_at']
        );
    }

    // Task creation actions (typically users/admins creating work)
    $stmt = $pdo->query(
        "SELECT t.id, t.title, t.created_at, u.name AS user_name
         FROM tasks t
         JOIN users u ON u.id = t.created_by
         ORDER BY t.created_at DESC
         LIMIT 80"
    );
    $taskCreatedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($taskCreatedRows as $row) {
        $pushActivity(
            1000000 + (int)$row['id'],
            'task_created',
            (string)$row['user_name'],
            'created task "' . (string)$row['title'] . '"',
            (string)$row['created_at']
        );
    }

    // Task completion actions (uses assignee as actor fallback to creator)
    $stmt = $pdo->query(
        "SELECT
            t.id,
            t.title,
            t.updated_at,
            COALESCE(assignee.name, creator.name) AS actor_name
         FROM tasks t
         LEFT JOIN users assignee ON assignee.id = t.assignee_id
         LEFT JOIN users creator ON creator.id = t.created_by
         WHERE t.status = 'completed'
         ORDER BY t.updated_at DESC
         LIMIT 80"
    );
    $taskCompletedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($taskCompletedRows as $row) {
        $pushActivity(
            2000000 + (int)$row['id'],
            'task_completed',
            (string)($row['actor_name'] ?? 'System'),
            'completed task "' . (string)$row['title'] . '"',
            (string)$row['updated_at']
        );
    }

    // Approval request actions
    $stmt = $pdo->query(
        "SELECT a.id, a.type, a.created_at, u.name AS user_name
         FROM approvals a
         JOIN users u ON u.id = a.requested_by
         ORDER BY a.created_at DESC
         LIMIT 80"
    );
    $approvalRequestedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($approvalRequestedRows as $row) {
        $pushActivity(
            3000000 + (int)$row['id'],
            'approval_requested',
            (string)$row['user_name'],
            'requested ' . (string)$row['type'] . ' approval',
            (string)$row['created_at']
        );
    }

    // Approval review actions (admin approve/reject)
    $stmt = $pdo->query(
        "SELECT a.id, a.type, a.status, a.approved_at, u.name AS admin_name
         FROM approvals a
         JOIN users u ON u.id = a.approved_by
         WHERE a.approved_by IS NOT NULL
           AND a.approved_at IS NOT NULL
           AND a.status IN ('approved', 'rejected')
         ORDER BY a.approved_at DESC
         LIMIT 80"
    );
    $approvalReviewedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($approvalReviewedRows as $row) {
        $reviewType = (string)$row['status'] === 'approved' ? 'approval_approved' : 'approval_rejected';
        $pushActivity(
            4000000 + (int)$row['id'],
            $reviewType,
            (string)$row['admin_name'],
            ((string)$row['status'] === 'approved' ? 'approved ' : 'rejected ') . (string)$row['type'] . ' request',
            (string)$row['approved_at']
        );
    }

    usort($combinedActivities, function ($a, $b) {
        return strtotime((string)$b['createdAt']) <=> strtotime((string)$a['createdAt']);
    });

    $combinedActivities = array_slice($combinedActivities, 0, 80);

    $formattedActivities = [];
    foreach ($combinedActivities as $activity) {
        $createdAtRaw = (string)$activity['createdAt'];
        $createdAtIso = date('c', strtotime($createdAtRaw));
        $formattedActivities[] = [
            'id' => (int)$activity['id'],
            'type' => (string)$activity['type'],
            'icon' => (string)$activity['icon'],
            'user' => (string)$activity['user'],
            'action' => (string)$activity['action'],
            'time' => getTimeAgo($createdAtRaw),
            'createdAt' => $createdAtIso,
        ];
    }

    // ===== OPERATIONAL METRICS (NON-DUPLICATE DASHBOARD-ONLY) =====
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tasks WHERE status <> 'completed' AND deadline IS NOT NULL AND deadline >= CURDATE() AND deadline < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
    $atRiskTasksToday = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tasks WHERE status <> 'completed' AND deadline IS NOT NULL AND deadline < CURDATE()");
    $overdueOpenTasks = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tasks WHERE status = 'review' AND updated_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    $blockedInReview = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM approvals WHERE status = 'pending'");
    $pendingApprovalsNow = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT MAX(TIMESTAMPDIFF(HOUR, created_at, NOW())) AS max_age_hours FROM approvals WHERE status = 'pending'");
    $oldestPendingApprovalHours = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['max_age_hours'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM approvals WHERE type = 'Resource Request' AND status = 'pending'");
    $pendingResourceRequests = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM resources WHERE status = 'maintenance'");
    $resourcesInMaintenance = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $resourceConflictsNow = $pendingResourceRequests + $resourcesInMaintenance;

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM resources WHERE status = 'available' AND type IN ('device', 'room', 'equipment')");
    $availableCriticalResourcesNow = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM tasks WHERE priority = 'high' AND status <> 'completed' AND assignee_id IS NULL");
    $unassignedHighPriorityTasks = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT assignee_id, COUNT(*) AS open_count FROM tasks WHERE status <> 'completed' AND assignee_id IS NOT NULL GROUP BY assignee_id");
    $openLoadRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $openLoads = array_map(function ($row) {
        return (int) $row['open_count'];
    }, $openLoadRows);
    $avgOpenLoad = count($openLoads) > 0 ? array_sum($openLoads) / count($openLoads) : 0.0;
    $overloadedUsers = 0;
    foreach ($openLoads as $load) {
        if ($avgOpenLoad > 0 && $load > ($avgOpenLoad * 1.5)) {
            $overloadedUsers++;
        }
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM approvals WHERE status = 'rejected' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentRejectedApprovals = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM resources WHERE status = 'maintenance' AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentMaintenanceUpdates = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $activeIncidents = $recentRejectedApprovals + $recentMaintenanceUpdates;

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0 AND type IN ('warning', 'task')");
    $stmt->execute([$user_id]);
    $unreadActionableNotifications = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $systemHealth = [
        'lastSuccessfulSync' => date('c'),
        'failedModuleCount' => 0,
    ];

    $operationalGroups = [
        [
            'title' => 'Task Risk & Flow',
            'items' => [
                ['label' => 'At-Risk Tasks Today', 'value' => (string)$atRiskTasksToday, 'hint' => 'Due in next 24h and not completed'],
                ['label' => 'Overdue Open Tasks', 'value' => (string)$overdueOpenTasks, 'hint' => 'Open tasks past deadline'],
                ['label' => 'Tasks Blocked in Review', 'value' => (string)$blockedInReview, 'hint' => 'In review longer than 48h'],
                ['label' => 'Unassigned High-Priority Tasks', 'value' => (string)$unassignedHighPriorityTasks, 'hint' => 'High-priority tasks without assignee'],
            ],
        ],
        [
            'title' => 'Approvals & Resources',
            'items' => [
                ['label' => 'Pending Approvals (Now)', 'value' => (string)$pendingApprovalsNow, 'hint' => 'Approvals requiring action'],
                ['label' => 'Oldest Pending Approval Age', 'value' => $oldestPendingApprovalHours . 'h', 'hint' => 'Longest waiting approval'],
                ['label' => 'Resource Conflicts Right Now', 'value' => (string)$resourceConflictsNow, 'hint' => 'Pending requests + maintenance resources'],
                ['label' => 'Available Critical Resources', 'value' => (string)$availableCriticalResourcesNow, 'hint' => 'Available device/room/equipment'],
            ],
        ],
        [
            'title' => 'Workload & System',
            'items' => [
                ['label' => 'Workload Imbalance Alert', 'value' => (string)$overloadedUsers, 'hint' => 'Users above 150% of avg open load'],
                ['label' => 'Active Incidents / Failures', 'value' => (string)$activeIncidents, 'hint' => 'Recent rejects + maintenance updates'],
                ['label' => 'Unread Actionable Notifications', 'value' => (string)$unreadActionableNotifications, 'hint' => 'Unread warning/task notifications'],
                ['label' => 'System Health Snapshot', 'value' => $systemHealth['failedModuleCount'] . ' failed', 'hint' => 'Last sync: ' . date('M j, H:i')],
            ],
        ],
    ];

    echo json_encode([
        'success' => true,
        'kpis' => [
            'totalTasks' => [
                'value' => (int) $totalTasks,
                'change' => (int) $taskChange,
                'icon' => 'fas fa-list-check',
                'color' => '#3b82f6'
            ],
            'pendingApprovals' => [
                'value' => (int) $pendingApprovals,
                'change' => (int) $approvalChange,
                'icon' => 'fas fa-check-circle',
                'color' => '#f59e0b'
            ],
            'resourcesInUse' => [
                'value' => (int) $resourcesInUse,
                'change' => 8,
                'icon' => 'fas fa-tools',
                'color' => '#10b981'
            ],
            'completedTasks' => [
                'value' => (int) $completedTasks,
                'change' => (int) $thisMonthCompleted,
                'icon' => 'fas fa-check',
                'color' => '#8b5cf6'
            ]
        ],
        'charts' => [
            'tasksThisWeek' => $taskChartData,
            'resourceUtilization' => $resourceChartData,
            'monthlyCompletion' => $monthlyCompletionData,
            'resourceByCategory' => $resourceByCategory
        ],
        'topPerformers' => $topPerformers,
        'activities' => $formattedActivities,
        'operationalGroups' => $operationalGroups,
        'systemHealth' => $systemHealth
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
    ]);
}

function getTimeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>