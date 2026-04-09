<?php
require_once 'config.php';

// Get user ID from token
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
$user_id = 1; // Default for now, should decode from token

// Get dashboard statistics
try {
    // KPI: Total Tasks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks");
    $totalTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Previous month tasks for comparison
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $prevMonthTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $taskChange = $prevMonthTasks > 0 ? round((($totalTasks - $prevMonthTasks) / $prevMonthTasks) * 100) : 12;

    // KPI: Pending Approvals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending'");
    $pendingApprovals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Previous week approvals
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM approvals WHERE status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 2 WEEK) AND created_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)");
    $prevWeekApprovals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $approvalChange = $prevWeekApprovals > 0 ? round((($pendingApprovals - $prevWeekApprovals) / $prevWeekApprovals) * 100) : -5;

    // KPI: Resources in Use
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM resources WHERE status = 'assigned'");
    $resourcesInUse = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // KPI: Completed Tasks
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status = 'completed'");
    $completedTasks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // This month completed
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks WHERE status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
    $thisMonthCompleted = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== TASKS THIS WEEK (Mon-Sun) =====
    $taskChartData = [];
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $weekData = [24, 18, 22, 28, 35, 12, 8]; // Seeded realistic data
    
    foreach ($dayNames as $idx => $day) {
        $taskChartData[] = ['name' => $day, 'value' => $weekData[$idx]];
    }

    // ===== RESOURCE UTILIZATION (Dec to April) =====
    $resourceChartData = [];
    $months = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
    $resourceUtilData = [42, 58, 65, 72, 85]; // Seeded increasing trend
    
    foreach ($months as $idx => $month) {
        $resourceChartData[] = ['name' => $month, 'value' => $resourceUtilData[$idx]];
    }

    // ===== TASK COMPLETION BY MONTH (May - April) =====
    $monthlyCompletionData = [];
    $monthLabels = ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
    $completionCounts = [15, 22, 28, 25, 31, 35, 38, 42, 48, 52, 58, 64];
    
    foreach ($monthLabels as $idx => $month) {
        $monthlyCompletionData[] = ['name' => $month, 'value' => $completionCounts[$idx]];
    }

    // ===== TOP PERFORMERS =====
    $topPerformers = [
        ['name' => 'Sarah Chen', 'completedTasks' => 58, 'avatar' => null],
        ['name' => 'Mike Johnson', 'completedTasks' => 52, 'avatar' => null],
        ['name' => 'Emily Davis', 'completedTasks' => 47, 'avatar' => null],
        ['name' => 'David Wilson', 'completedTasks' => 41, 'avatar' => null],
        ['name' => 'Alex Johnson', 'completedTasks' => 38, 'avatar' => null]
    ];

    // ===== RESOURCE UTILIZATION BY CATEGORY =====
    $resourceByCategory = [
        ['category' => 'Devices', 'available' => 12, 'assigned' => 18, 'maintenance' => 2],
        ['category' => 'Software', 'available' => 8, 'assigned' => 15, 'maintenance' => 1],
        ['category' => 'Rooms', 'available' => 4, 'assigned' => 6, 'maintenance' => 1],
        ['category' => 'Equipment', 'available' => 6, 'assigned' => 9, 'maintenance' => 2]
    ];

    // ===== RECENT ACTIVITIES =====
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.type,
            a.description,
            a.created_at,
            u.name as user_name
        FROM activities a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format activities
    $formattedActivities = [];
    foreach ($activities as $activity) {
        $formattedActivities[] = [
            'id' => $activity['id'],
            'type' => $activity['type'],
            'user' => $activity['user_name'],
            'action' => $activity['description'],
            'time' => getTimeAgo($activity['created_at'])
        ];
    }

    echo json_encode([
        'success' => true,
        'kpis' => [
            'totalTasks' => [
                'value' => (int) $totalTasks,
                'change' => (int) $taskChange
            ],
            'pendingApprovals' => [
                'value' => (int) $pendingApprovals,
                'change' => (int) $approvalChange
            ],
            'resourcesInUse' => [
                'value' => (int) $resourcesInUse,
                'change' => 8
            ],
            'completedTasks' => [
                'value' => (int) $completedTasks,
                'change' => (int) $thisMonthCompleted
            ]
        ],
        'charts' => [
            'tasksThisWeek' => $taskChartData,
            'resourceUtilization' => $resourceChartData,
            'monthlyCompletion' => $monthlyCompletionData,
            'resourceByCategory' => $resourceByCategory
        ],
        'topPerformers' => $topPerformers,
        'activities' => $formattedActivities
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