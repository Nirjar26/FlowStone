<?php
require_once 'config.php';

function reports_decode_token_user_id(string $authorizationHeader): ?int
{
    $token = trim($authorizationHeader);

    if ($token === '') {
        return null;
    }

    if (stripos($token, 'Bearer ') === 0) {
        $token = trim(substr($token, 7));
    }

    if ($token === '' || strpos($token, '.') === false) {
        return null;
    }

    $parts = explode('.', $token);
    if (count($parts) < 2) {
        return null;
    }

    $payload = $parts[1];
    $remainder = strlen($payload) % 4;
    if ($remainder > 0) {
        $payload .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($payload, '-_', '+/'));
    if ($decoded === false) {
        return null;
    }

    $payloadData = json_decode($decoded, true);
    if (!is_array($payloadData)) {
        return null;
    }

    foreach (['user_id', 'id', 'sub'] as $key) {
        if (isset($payloadData[$key]) && is_numeric($payloadData[$key])) {
            $value = (int) $payloadData[$key];
            if ($value > 0) {
                return $value;
            }
        }
    }

    return null;
}

function reports_parse_date_or_null(?string $value): ?DateTimeImmutable
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', trim($value));
    if (!$parsed || $parsed->format('Y-m-d') !== trim($value)) {
        return null;
    }

    return $parsed;
}

function reports_percent_change(float $current, float $previous): int
{
    if ($previous == 0.0) {
        return $current > 0 ? 100 : 0;
    }

    return (int) round((($current - $previous) / $previous) * 100);
}

function reports_signed_percent(int $value): string
{
    return ($value >= 0 ? '+' : '') . $value . '%';
}

function reports_bind_task_scope(PDOStatement $statement, array $params): void
{
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$tokenUserId = reports_decode_token_user_id($authorization);
$queryUserId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

$resolvedUserId = $tokenUserId ?: ($queryUserId > 0 ? $queryUserId : 0);
$resolvedUser = $resolvedUserId > 0 ? flowstone_fetch_user($pdo, $resolvedUserId) : null;
$isAdmin = flowstone_is_admin_role($resolvedUser['role'] ?? null);

$today = new DateTimeImmutable('today');
$defaultStart = $today->modify('first day of this month')->modify('-11 months');
$defaultEnd = $today;

$requestedStart = reports_parse_date_or_null($_GET['start_date'] ?? null);
$requestedEnd = reports_parse_date_or_null($_GET['end_date'] ?? null);

$rangeStartDate = $requestedStart ?? $defaultStart;
$rangeEndDate = $requestedEnd ?? $defaultEnd;

if ($rangeStartDate > $rangeEndDate) {
    [$rangeStartDate, $rangeEndDate] = [$rangeEndDate, $rangeStartDate];
}

$rangeStart = $rangeStartDate->setTime(0, 0, 0);
$rangeEndExclusive = $rangeEndDate->setTime(0, 0, 0)->modify('+1 day');

$periodDays = max(1, (int) $rangeStart->diff($rangeEndExclusive)->days);
$prevRangeEndExclusive = $rangeStart;
$prevRangeStart = $rangeStart->modify('-' . $periodDays . ' days');

$taskScopeClause = '';
$taskScopeParams = [];
if (!$isAdmin && $resolvedUserId > 0) {
    $taskScopeClause = ' AND (t.created_by = :scope_user_id OR t.assignee_id = :scope_user_id)';
    $taskScopeParams['scope_user_id'] = $resolvedUserId;
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$priorityFilter = strtolower(trim((string) ($_GET['priority'] ?? 'all')));
$allowedTaskStatuses = ['all', 'pending', 'in-progress', 'review', 'completed'];
$allowedTaskPriorities = ['all', 'low', 'medium', 'high'];

if (!in_array($statusFilter, $allowedTaskStatuses, true)) {
    $statusFilter = 'all';
}

if (!in_array($priorityFilter, $allowedTaskPriorities, true)) {
    $priorityFilter = 'all';
}

$taskFilterClause = '';
$taskFilterParams = [];
if ($statusFilter !== 'all') {
    $taskFilterClause .= ' AND t.status = :filter_status';
    $taskFilterParams['filter_status'] = $statusFilter;
}
if ($priorityFilter !== 'all') {
    $taskFilterClause .= ' AND t.priority = :filter_priority';
    $taskFilterParams['filter_priority'] = $priorityFilter;
}

$taskAllParams = array_merge($taskScopeParams, $taskFilterParams);

try {
    // KPI: Total Tasks Completed (current period)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :range_start
           AND t.updated_at < :range_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $totalCompleted = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // KPI: Total Tasks Completed (previous period)
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :prev_start
           AND t.updated_at < :prev_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'prev_start' => $prevRangeStart->format('Y-m-d H:i:s'),
        'prev_end' => $prevRangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $prevCompleted = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $completedChange = reports_percent_change((float) $totalCompleted, (float) $prevCompleted);

    // KPI: Active Users (current and previous periods)
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT t.created_by) as total
         FROM tasks t
         WHERE t.created_at >= :range_start
           AND t.created_at < :range_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $activeUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT t.created_by) as total
         FROM tasks t
         WHERE t.created_at >= :prev_start
           AND t.created_at < :prev_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'prev_start' => $prevRangeStart->format('Y-m-d H:i:s'),
        'prev_end' => $prevRangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $prevActiveUsers = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $usersChange = reports_percent_change((float) $activeUsers, (float) $prevActiveUsers);

    // KPI: Resource Utilization
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM resources WHERE status = 'assigned'");
    $assignedResources = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM resources");
    $totalResources = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $resourceUtilRate = $totalResources > 0 ? round(($assignedResources / $totalResources) * 100) : 0;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total
         FROM resources
         WHERE status = 'assigned'
           AND updated_at >= :range_start
           AND updated_at < :range_end"
    );
    $stmt->execute([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ]);
    $assignedCurrentPeriod = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total
         FROM resources
         WHERE status = 'assigned'
           AND updated_at >= :prev_start
           AND updated_at < :prev_end"
    );
    $stmt->execute([
        'prev_start' => $prevRangeStart->format('Y-m-d H:i:s'),
        'prev_end' => $prevRangeEndExclusive->format('Y-m-d H:i:s'),
    ]);
    $assignedPreviousPeriod = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $resourceChange = reports_percent_change((float) $assignedCurrentPeriod, (float) $assignedPreviousPeriod);

    // KPI: Average Completion Time
    $stmt = $pdo->prepare(
        "SELECT AVG(DATEDIFF(t.updated_at, t.created_at)) as avg_days
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :range_start
           AND t.updated_at < :range_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $avgCompletionTimeRaw = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'];
    $avgCompletionTime = $avgCompletionTimeRaw !== null ? round((float) $avgCompletionTimeRaw, 1) : 0.0;

    $stmt = $pdo->prepare(
        "SELECT AVG(DATEDIFF(t.updated_at, t.created_at)) as avg_days
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :prev_start
           AND t.updated_at < :prev_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'prev_start' => $prevRangeStart->format('Y-m-d H:i:s'),
        'prev_end' => $prevRangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $prevAvgCompletionRaw = $stmt->fetch(PDO::FETCH_ASSOC)['avg_days'];
    $prevAvgCompletion = $prevAvgCompletionRaw !== null ? (float) $prevAvgCompletionRaw : 0.0;
    $avgCompletionChange = reports_percent_change($avgCompletionTime, $prevAvgCompletion);

    // Simple add-on metrics
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total_with_deadline,
            SUM(CASE WHEN DATE(t.updated_at) <= t.deadline THEN 1 ELSE 0 END) AS on_time_count
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.deadline IS NOT NULL
           AND t.updated_at >= :range_start
           AND t.updated_at < :range_end" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($stmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $stmt->execute();
    $onTimeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $onTimeDenominator = (int) ($onTimeRow['total_with_deadline'] ?? 0);
    $onTimeCount = (int) ($onTimeRow['on_time_count'] ?? 0);
    $onTimeRate = $onTimeDenominator > 0 ? (int) round(($onTimeCount / $onTimeDenominator) * 100) : 0;

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM tasks t
         WHERE t.status IN ('pending', 'in-progress', 'review')
           AND t.deadline IS NOT NULL
            AND t.deadline < CURDATE()" . $taskScopeClause . $taskFilterClause
    );
        reports_bind_task_scope($stmt, $taskAllParams);
    $stmt->execute();
    $overdueOpenTasks = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM tasks t
            WHERE t.status IN ('pending', 'in-progress', 'review')" . $taskScopeClause . $taskFilterClause
    );
        reports_bind_task_scope($stmt, $taskAllParams);
    $stmt->execute();
    $workInProgressTasks = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    
    // Monthly Tasks Data (selected period)
    $createdStmt = $pdo->prepare(
        "SELECT
            DATE_FORMAT(t.created_at, '%Y-%m') as ym,
            COUNT(*) as total
         FROM tasks t
         WHERE t.created_at >= :range_start
                     AND t.created_at < :range_end" . $taskScopeClause . $taskFilterClause . "
         GROUP BY ym"
    );
    reports_bind_task_scope($createdStmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
        ], $taskAllParams));
    $createdStmt->execute();
    $createdRows = $createdStmt->fetchAll(PDO::FETCH_ASSOC);

    $completedStmt = $pdo->prepare(
        "SELECT
            DATE_FORMAT(t.updated_at, '%Y-%m') as ym,
            COUNT(*) as total
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :range_start
                     AND t.updated_at < :range_end" . $taskScopeClause . $taskFilterClause . "
         GROUP BY ym"
    );
    reports_bind_task_scope($completedStmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
        ], $taskAllParams));
    $completedStmt->execute();
    $completedRows = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

    $createdByMonth = [];
    foreach ($createdRows as $row) {
        $createdByMonth[$row['ym']] = (int) $row['total'];
    }

    $completedByMonth = [];
    foreach ($completedRows as $row) {
        $completedByMonth[$row['ym']] = (int) $row['total'];
    }

    // Build month series for the selected date range so charts always render consistently.
    $monthlyTasks = [];
    $startMonth = $rangeStartDate->modify('first day of this month');
    $endMonth = $rangeEndDate->modify('first day of this month');
    $monthDate = $startMonth;
    while ($monthDate <= $endMonth) {
        $ym = $monthDate->format('Y-m');
        $monthlyTasks[] = [
            'name' => $monthDate->format('M'),
            'created' => $createdByMonth[$ym] ?? 0,
            'completed' => $completedByMonth[$ym] ?? 0,
        ];
        $monthDate = $monthDate->modify('+1 month');
    }
    
    // User Performance (all performers by completed tasks in selected period)
    $userJoinFilterClause = '';
    $userJoinFilterParams = [];
    if ($statusFilter !== 'all') {
        $userJoinFilterClause .= ' AND t.status = :join_filter_status';
        $userJoinFilterParams['join_filter_status'] = $statusFilter;
    } else {
        $userJoinFilterClause .= " AND t.status = 'completed'";
    }
    if ($priorityFilter !== 'all') {
        $userJoinFilterClause .= ' AND t.priority = :join_filter_priority';
        $userJoinFilterParams['join_filter_priority'] = $priorityFilter;
    }

    $userPerformanceScopeClause = '';
    $userPerformanceScopeParams = [];
    if (!$isAdmin && $resolvedUserId > 0) {
        $userPerformanceScopeClause = ' WHERE u.id = :scope_user_id';
        $userPerformanceScopeParams['scope_user_id'] = $resolvedUserId;
    }

    $stmt = $pdo->prepare(
        "SELECT
            u.name,
            COUNT(t.id) as tasks
        FROM users u
        LEFT JOIN tasks t
               ON t.assignee_id = u.id
              AND t.updated_at >= :range_start
              AND t.updated_at < :range_end" . $userJoinFilterClause . "
        " . $userPerformanceScopeClause . "
        GROUP BY u.id, u.name
        ORDER BY tasks DESC, u.name ASC"
    );
    $stmt->execute(array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $userJoinFilterParams, $userPerformanceScopeParams));
    $userPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format user performance
    $formattedUserPerformance = [];
    foreach ($userPerformance as $user) {
        $formattedUserPerformance[] = [
            'name' => $user['name'],
            'tasks' => (int)$user['tasks']
        ];
    }
    
    // Resource Utilization by Type
    if ($isAdmin) {
        $stmt = $pdo->query(" 
            SELECT 
                type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned
            FROM resources
            GROUP BY type
        ");
        $resourceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare(" 
            SELECT 
                type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'assigned' AND assigned_to = :scope_user_id THEN 1 ELSE 0 END) as assigned
            FROM resources
            GROUP BY type
        ");
        $stmt->execute(['scope_user_id' => $resolvedUserId]);
        $resourceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format resource utilization
    $resourceUtilization = [];
    $typeColors = [
        'device' => 'hsl(230, 45%, 28%)',
        'software' => 'hsl(174, 42%, 42%)',
        'room' => 'hsl(38, 92%, 50%)',
        'equipment' => 'hsl(158, 50%, 42%)'
    ];
    
    $typeNames = [
        'device' => 'Devices',
        'software' => 'Software',
        'room' => 'Rooms',
        'equipment' => 'Equipment'
    ];
    
    foreach ($resourceData as $data) {
        $type = $data['type'];
        $total = (int)$data['total'];
        $assigned = (int)$data['assigned'];
        $percentage = $total > 0 ? round(($assigned / $total) * 100) : 0;
        
        $resourceUtilization[] = [
            'name' => $typeNames[$type] ?? ucfirst($type),
            'value' => $percentage,
            'color' => $typeColors[$type] ?? 'hsl(200, 50%, 50%)'
        ];
    }
    
    // If no resources, provide defaults
    if (empty($resourceUtilization)) {
        $resourceUtilization = [
            ['name' => 'Devices', 'value' => 78, 'color' => 'hsl(230, 45%, 28%)'],
            ['name' => 'Software', 'value' => 92, 'color' => 'hsl(174, 42%, 42%)'],
            ['name' => 'Rooms', 'value' => 65, 'color' => 'hsl(38, 92%, 50%)'],
            ['name' => 'Equipment', 'value' => 54, 'color' => 'hsl(158, 50%, 42%)']
        ];
    }

    // Advanced Report 4: Complexity vs Completion Time (priority as complexity proxy)
    $complexityStmt = $pdo->prepare(
        "SELECT
            t.priority,
            COUNT(*) AS total,
            AVG(DATEDIFF(DATE(t.updated_at), DATE(t.created_at))) AS avg_days
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :range_start
           AND t.updated_at < :range_end" . $taskScopeClause . $taskFilterClause . "
         GROUP BY t.priority"
    );
    reports_bind_task_scope($complexityStmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
    ], $taskAllParams));
    $complexityStmt->execute();
    $complexityRows = $complexityStmt->fetchAll(PDO::FETCH_ASSOC);

    $avgByPriority = ['low' => 0.0, 'medium' => 0.0, 'high' => 0.0];
    foreach ($complexityRows as $row) {
        $priority = (string) ($row['priority'] ?? '');
        if (array_key_exists($priority, $avgByPriority)) {
            $avgByPriority[$priority] = $row['avg_days'] !== null ? round((float) $row['avg_days'], 1) : 0.0;
        }
    }

    $outlierBaseline = max(1.0, (float) $avgCompletionTime * 1.5);
    $outlierStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM tasks t
         WHERE t.status = 'completed'
           AND t.updated_at >= :range_start
           AND t.updated_at < :range_end
           AND DATEDIFF(DATE(t.updated_at), DATE(t.created_at)) > :outlier_days" . $taskScopeClause . $taskFilterClause
    );
    reports_bind_task_scope($outlierStmt, array_merge([
        'range_start' => $rangeStart->format('Y-m-d H:i:s'),
        'range_end' => $rangeEndExclusive->format('Y-m-d H:i:s'),
        'outlier_days' => (int) ceil($outlierBaseline),
    ], $taskAllParams));
    $outlierStmt->execute();
    $outlierCount = (int) (($outlierStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));

    $complexityTrend = round($avgByPriority['high'] - $avgByPriority['low'], 1);
    
    echo json_encode([
        'success' => true,
        'kpis' => [
            'totalCompleted' => [
                'value' => number_format($totalCompleted),
                'change' => reports_signed_percent($completedChange)
            ],
            'activeUsers' => [
                'value' => (string)$activeUsers,
                'change' => reports_signed_percent($usersChange)
            ],
            'resourceUtilization' => [
                'value' => $resourceUtilRate . '%',
                'change' => reports_signed_percent($resourceChange)
            ],
            'avgCompletionTime' => [
                'value' => $avgCompletionTime . ' days',
                'change' => reports_signed_percent($avgCompletionChange)
            ]
        ],
        'period' => [
            'startDate' => $rangeStartDate->format('Y-m-d'),
            'endDate' => $rangeEndDate->format('Y-m-d'),
            'label' => $rangeStartDate->format('M j, Y') . ' - ' . $rangeEndDate->format('M j, Y')
        ],
        'quickMetrics' => [
            [
                'title' => 'On-Time Completion',
                'value' => $onTimeRate . '%',
                'hint' => 'Completed before deadline'
            ],
            [
                'title' => 'Overdue Open Tasks',
                'value' => (string) $overdueOpenTasks,
                'hint' => 'Open tasks past deadline'
            ],
            [
                'title' => 'Work In Progress',
                'value' => (string) $workInProgressTasks,
                'hint' => 'Pending + in-progress + review'
            ]
        ],
        'monthlyTasks' => $monthlyTasks,
        'userPerformance' => $formattedUserPerformance,
        'resourceUtilization' => $resourceUtilization
        ,
        'advancedReports' => [
            'complexityVsTime' => [
                'avgDaysByPriority' => [
                    'low' => $avgByPriority['low'],
                    'medium' => $avgByPriority['medium'],
                    'high' => $avgByPriority['high'],
                ],
                'outlierCount' => $outlierCount,
                'complexityDelayTrend' => $complexityTrend,
            ],
        ],
        'appliedFilters' => [
            'status' => $statusFilter,
            'priority' => $priorityFilter,
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch reports data: ' . $e->getMessage()
    ]);
}
?>
