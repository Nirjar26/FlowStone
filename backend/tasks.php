<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get filters from query parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$priority = isset($_GET['priority']) ? $_GET['priority'] : null;
$assignee_id = isset($_GET['assignee_id']) ? intval($_GET['assignee_id']) : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$created_date = isset($_GET['created_date']) ? trim($_GET['created_date']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$requestUser = $user_id > 0 ? flowstone_fetch_user($pdo, $user_id) : null;
$isAdmin = flowstone_is_admin_role($requestUser['role'] ?? null);

try {
    // Build query
    $sql = "
        SELECT 
            t.id,
            t.title,
            t.description,
            t.status,
            t.priority,
            t.deadline,
            t.created_at,
            t.updated_at,
            t.assignee_id,
            t.created_by,
            u.name as assignee_name,
            u.email as assignee_email,
            creator.name as created_by_name,
            (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) as comments_count,
            (SELECT COUNT(*) FROM task_attachments WHERE task_id = t.id) as attachments_count
        FROM tasks t
        LEFT JOIN users u ON t.assignee_id = u.id
        LEFT JOIN users creator ON t.created_by = creator.id
        WHERE 1=1
    ";
    
    $params = [];

    if (!$isAdmin && $user_id > 0) {
        $sql .= " AND (t.assignee_id = ? OR t.created_by = ?)";
        $params[] = $user_id;
        $params[] = $user_id;
    }
    
    // Add filters
    if ($status && in_array($status, ['pending', 'in-progress', 'review', 'completed'])) {
        $sql .= " AND t.status = ?";
        $params[] = $status;
    }
    
    if ($priority && in_array($priority, ['low', 'medium', 'high'])) {
        $sql .= " AND t.priority = ?";
        $params[] = $priority;
    }
    
    if ($assignee_id) {
        $sql .= " AND t.assignee_id = ?";
        $params[] = $assignee_id;
    }
    
    if ($search) {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($created_date) {
        $createdDateObj = DateTime::createFromFormat('Y-m-d', $created_date);
        if (!$createdDateObj || $createdDateObj->format('Y-m-d') !== $created_date) {
            echo json_encode(['success' => false, 'message' => 'Invalid created_date format. Use YYYY-MM-DD']);
            exit;
        }

        $sql .= " AND DATE(t.created_at) = ?";
        $params[] = $created_date;
    }
    
    // Order by the selected sort mode, then priority and title for stable results
    $orderBy = "t.created_at DESC";
    if ($sort === 'oldest') {
        $orderBy = "t.created_at ASC";
    } elseif ($sort === 'newest') {
        $orderBy = "t.created_at DESC";
    }

    $sql .= " ORDER BY {$orderBy}, CASE t.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END, t.title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countsSql = "
        SELECT 
            COUNT(*) AS all_count,
            SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN t.status = 'in-progress' THEN 1 ELSE 0 END) AS in_progress_count,
            SUM(CASE WHEN t.status = 'review' THEN 1 ELSE 0 END) AS review_count,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM tasks t
        WHERE 1=1
    ";
    $countParams = [];
    if (!$isAdmin && $user_id > 0) {
        $countsSql .= " AND (t.assignee_id = ? OR t.created_by = ?)";
        $countParams[] = $user_id;
        $countParams[] = $user_id;
    }
    $countStmt = $pdo->prepare($countsSql);
    $countStmt->execute($countParams);
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Format tasks
    $formattedTasks = array_map(function($task) {
        return [
            'id' => (int)$task['id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'status' => $task['status'],
            'priority' => $task['priority'],
            'assignee' => [
                'id' => $task['assignee_id'],
                'name' => $task['assignee_name'] ?? 'Unassigned',
                'email' => $task['assignee_email'] ?? null
            ],
            'createdBy' => [
                'id' => (int)$task['created_by'],
                'name' => $task['created_by_name']
            ],
            'deadline' => $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : null,
            'createdAt' => date('M d, Y', strtotime($task['created_at'])),
            'updatedAt' => date('M d, Y', strtotime($task['updated_at'])),
            'comments' => (int)$task['comments_count'],
            'attachments' => (int)$task['attachments_count']
        ];
    }, $tasks);
    
    echo json_encode([
        'success' => true,
        'tasks' => $formattedTasks,
        'total' => count($formattedTasks),
        'statusCounts' => [
            'all' => (int)($countRow['all_count'] ?? 0),
            'pending' => (int)($countRow['pending_count'] ?? 0),
            'in-progress' => (int)($countRow['in_progress_count'] ?? 0),
            'review' => (int)($countRow['review_count'] ?? 0),
            'completed' => (int)($countRow['completed_count'] ?? 0),
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>