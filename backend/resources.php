<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

function resources_decode_request(array $input): array
{
    return is_array($input) ? $input : [];
}

function resources_decode_description(?string $description): array
{
    if ($description === null || trim($description) === '') {
        return [];
    }

    $decoded = json_decode($description, true);
    return is_array($decoded) ? $decoded : [];
}

function resources_encode_request_payload(int $resourceId, int $requestForUserId, string $note): string
{
    return json_encode([
        'resourceId' => $resourceId,
        'requestForUserId' => $requestForUserId,
        'note' => $note,
    ]);
}

function resources_require_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }

    $user = flowstone_fetch_user($pdo, $userId);
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    return $user;
}

function resources_require_admin(bool $isAdmin): void
{
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Only admins can perform this action']);
        exit;
    }
}

function resources_fetch_resource(PDO $pdo, int $resourceId): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, status FROM resources WHERE id = :id');
    $stmt->execute([':id' => $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resource ?: null;
}

function resources_extract_resource_id_from_payload(string $description): int
{
    $payload = resources_decode_description($description);
    return (int)($payload['resourceId'] ?? 0);
}

function resources_has_pending_request_for_resource(PDO $pdo, int $resourceId): bool
{
    $stmt = $pdo->prepare("SELECT description FROM approvals WHERE type = 'Resource Request' AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        if (resources_extract_resource_id_from_payload((string)$row['description']) === $resourceId) {
            return true;
        }
    }

    return false;
}

function resources_reject_pending_requests_for_resource(PDO $pdo, int $resourceId, int $reviewedBy): void
{
    $stmt = $pdo->prepare("SELECT id, description FROM approvals WHERE type = 'Resource Request' AND status = 'pending' ORDER BY created_at DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $pdo->prepare("UPDATE approvals SET status = 'rejected', approved_by = :approved_by, approved_at = NOW() WHERE id = :id");
    foreach ($rows as $row) {
        $rowResourceId = resources_extract_resource_id_from_payload((string)$row['description']);
        if ($rowResourceId !== $resourceId) {
            continue;
        }

        $updateStmt->execute([
            ':approved_by' => $reviewedBy,
            ':id' => (int)$row['id'],
        ]);
    }
}

try {
    $bodyData = [];
    if ($method !== 'GET') {
        $bodyData = resources_decode_request(json_decode(file_get_contents('php://input'), true));
    }

    $requestUserId = 0;
    if ($method === 'GET') {
        $requestUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    } else {
        $requestUserId = isset($bodyData['user_id']) ? intval($bodyData['user_id']) : 0;
    }

    $entity = $_GET['entity'] ?? 'resources';
    $isLegacyListMode = $method === 'GET' && $entity === 'resources' && $requestUserId <= 0;

    $requestUser = null;
    $isAdmin = false;
    if (!$isLegacyListMode) {
        $requestUser = resources_require_user($pdo, $requestUserId);
        $isAdmin = flowstone_is_admin_role($requestUser['role'] ?? null);
    }

    if ($method === 'GET') {
        if ($entity === 'requests') {
            $status = $_GET['status'] ?? 'all';
            $allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
            if (!in_array($status, $allowedStatuses, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request status filter']);
                exit;
            }

            $sql = "
                SELECT
                    a.id,
                    a.type,
                    a.description,
                    a.status,
                    a.created_at,
                    a.requested_by,
                    requester.name AS requested_by_name,
                    approver.name AS approved_by_name,
                    a.approved_at
                FROM approvals a
                JOIN users requester ON requester.id = a.requested_by
                LEFT JOIN users approver ON approver.id = a.approved_by
                WHERE a.type = 'Resource Request'
            ";

            $params = [];

            if (!$isAdmin) {
                $sql .= ' AND a.requested_by = :request_user_id';
                $params[':request_user_id'] = $requestUserId;
            }

            if ($status !== 'all') {
                $sql .= ' AND a.status = :status';
                $params[':status'] = $status;
            }

            $sql .= ' ORDER BY a.created_at DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $requestItems = [];
            foreach ($rows as $row) {
                $payload = resources_decode_description($row['description']);
                $resourceId = (int)($payload['resourceId'] ?? 0);
                $requestForUserId = (int)($payload['requestForUserId'] ?? (int)$row['requested_by']);
                $resourceName = '';
                if ($resourceId > 0) {
                    $resource = resources_fetch_resource($pdo, $resourceId);
                    $resourceName = $resource['name'] ?? '';
                }

                $requestForUser = $requestForUserId > 0 ? flowstone_fetch_user($pdo, $requestForUserId) : null;
                $isOwner = (int)$row['requested_by'] === $requestUserId;
                $isPending = $row['status'] === 'pending';

                $requestItems[] = [
                    'id' => (int)$row['id'],
                    'status' => $row['status'],
                    'resourceId' => $resourceId,
                    'resourceName' => $resourceName,
                    'requestedBy' => [
                        'id' => (int)$row['requested_by'],
                        'name' => $row['requested_by_name'],
                    ],
                    'requestForUser' => $requestForUser ? [
                        'id' => (int)$requestForUser['id'],
                        'name' => $requestForUser['name'],
                    ] : null,
                    'note' => trim((string)($payload['note'] ?? '')),
                    'approvedBy' => $row['approved_by_name'],
                    'approvedAt' => $row['approved_at'],
                    'createdAt' => $row['created_at'],
                    'canModify' => $isPending && ($isAdmin || $isOwner),
                    'canReview' => $isPending && $isAdmin,
                ];
            }

            echo json_encode([
                'success' => true,
                'requests' => $requestItems,
                'canReview' => $isAdmin,
            ]);
            exit;
        }

        // Get resources list
        $type = $_GET['type'] ?? 'all';
        $status = $_GET['status'] ?? 'all';
        $search = $_GET['search'] ?? '';

        $stmt = $pdo->query("\n            SELECT 
                r.id,
                r.name,
                r.type,
                r.status,
                r.location,
                r.description,
                r.assigned_to,
                u.name as assigned_to_name
            FROM resources r
            LEFT JOIN users u ON r.assigned_to = u.id
            ORDER BY r.created_at DESC
        ");
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $requestSql = "
            SELECT id, status, description, requested_by, approved_by, approved_at, created_at
            FROM approvals
            WHERE type = 'Resource Request'
            ORDER BY created_at DESC
        ";
        $requestStmt = $pdo->prepare($requestSql);
        $requestStmt->execute();
        $requestRows = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

        $latestRequestByResource = [];
        $pendingRequestByResource = [];

        foreach ($requestRows as $requestRow) {
            $resourceIdFromRequest = resources_extract_resource_id_from_payload((string)$requestRow['description']);
            if ($resourceIdFromRequest <= 0) {
                continue;
            }

            if (!isset($latestRequestByResource[$resourceIdFromRequest])) {
                $latestRequestByResource[$resourceIdFromRequest] = $requestRow;
            }

            if ($requestRow['status'] === 'pending' && !isset($pendingRequestByResource[$resourceIdFromRequest])) {
                $pendingRequestByResource[$resourceIdFromRequest] = $requestRow;
            }
        }

        $visibleResources = [];
        $filterCounts = [
            'types' => [
                'all' => 0,
                'device' => 0,
                'software' => 0,
                'room' => 0,
                'equipment' => 0,
            ],
            'statuses' => [
                'all' => 0,
                'available' => 0,
                'assigned' => 0,
                'maintenance' => 0,
                'requested' => 0,
                'rejected' => 0,
            ],
        ];

        // Format resources and apply visibility + derived statuses.
        foreach ($resources as $resource) {
            $resourceId = (int)$resource['id'];
            $baseStatus = (string)$resource['status'];
            $derivedStatus = $baseStatus;
            $requestId = null;
            $requestedBy = null;
            $requestedAt = null;

            $pendingRequest = $pendingRequestByResource[$resourceId] ?? null;
            $latestRequest = $latestRequestByResource[$resourceId] ?? null;

            if ($pendingRequest) {
                $derivedStatus = 'requested';
                $requestId = (int)$pendingRequest['id'];
                $requestedBy = flowstone_fetch_user($pdo, (int)$pendingRequest['requested_by']);
                $requestedAt = $pendingRequest['created_at'];
            } elseif ($isAdmin && $latestRequest && $latestRequest['status'] === 'rejected' && $baseStatus === 'available') {
                $derivedStatus = 'rejected';
                $requestId = (int)$latestRequest['id'];
                $requestedBy = flowstone_fetch_user($pdo, (int)$latestRequest['requested_by']);
                $requestedAt = $latestRequest['created_at'];
            }

            $visible = true;
            if (!$isAdmin) {
                $isOwnedAssignment = $baseStatus === 'assigned' && (int)($resource['assigned_to'] ?? 0) === $requestUserId;
                $isPendingOwnRequest = $pendingRequest && (int)$pendingRequest['requested_by'] === $requestUserId;
                $isRejectedOwnRequest = $latestRequest && $latestRequest['status'] === 'rejected' && (int)$latestRequest['requested_by'] === $requestUserId;

                $visible = $isOwnedAssignment
                    || $baseStatus === 'available'
                    || $baseStatus === 'maintenance'
                    || $isPendingOwnRequest
                    || $isRejectedOwnRequest;

                if ($isOwnedAssignment) {
                    $derivedStatus = 'assigned';
                } elseif ($isRejectedOwnRequest && $baseStatus === 'available') {
                    $derivedStatus = 'rejected';
                    $requestId = (int)$latestRequest['id'];
                    $requestedBy = flowstone_fetch_user($pdo, (int)$latestRequest['requested_by']);
                    $requestedAt = $latestRequest['created_at'];
                }
            }

            if (!$visible) {
                continue;
            }

            $viewResource = [
                'id' => $resourceId,
                'name' => $resource['name'],
                'type' => $resource['type'],
                'status' => $derivedStatus,
                'requestId' => $requestId,
                'requestedBy' => $requestedBy ? [
                    'id' => (int)$requestedBy['id'],
                    'name' => $requestedBy['name'],
                    'department' => $requestedBy['department'] ?? null,
                ] : null,
                'requestedAt' => $requestedAt,
                'location' => $resource['location'],
                'description' => $resource['description'],
                'assignedTo' => $resource['assigned_to_name'] ? [
                    'id' => (int)$resource['assigned_to'],
                    'name' => $resource['assigned_to_name'],
                ] : null
            ];

            $visibleResources[] = $viewResource;
            $filterCounts['types']['all']++;
            $filterCounts['statuses']['all']++;

            $resourceType = (string)$viewResource['type'];
            if (isset($filterCounts['types'][$resourceType])) {
                $filterCounts['types'][$resourceType]++;
            }

            $resourceStatus = (string)$viewResource['status'];
            if (isset($filterCounts['statuses'][$resourceStatus])) {
                $filterCounts['statuses'][$resourceStatus]++;
            }
        }

        $formattedResources = array_values(array_filter($visibleResources, function (array $resource) use ($type, $status, $search): bool {
            if ($type !== 'all' && $resource['type'] !== $type) {
                return false;
            }

            if ($status !== 'all' && $resource['status'] !== $status) {
                return false;
            }

            if ($search !== '' && stripos((string)$resource['name'], $search) === false) {
                return false;
            }

            return true;
        }));

        $userRows = [];
        if ($isAdmin) {
            $stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name ASC");
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode([
            'success' => true,
            'resources' => $formattedResources,
            'permissions' => [
                'isAdmin' => $isAdmin,
                'canAssign' => $isAdmin,
                'canManageResources' => $isAdmin,
                'canReviewRequests' => $isAdmin,
                'canCreateRequests' => true,
            ],
            'filterCounts' => $filterCounts,
            'users' => $userRows,
        ]);
        
    } elseif ($method === 'POST') {
        $data = $bodyData;
        
        if (isset($data['action'])) {
            // Resource management actions
            $action = $data['action'];
            $resourceId = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($action === 'assign') {
                resources_require_admin($isAdmin);
                $assignToId = isset($data['assignTo']) ? (int)$data['assignTo'] : 0;

                if ($resourceId <= 0 || $assignToId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Resource and user are required']);
                    exit;
                }

                $assignee = flowstone_fetch_user($pdo, $assignToId);
                if (!$assignee) {
                    echo json_encode(['success' => false, 'message' => 'Assignee not found']);
                    exit;
                }

                $resource = resources_fetch_resource($pdo, $resourceId);
                if (!$resource) {
                    echo json_encode(['success' => false, 'message' => 'Resource not found']);
                    exit;
                }

                if (!in_array($resource['status'], ['available', 'assigned', 'maintenance'], true)) {
                    echo json_encode(['success' => false, 'message' => 'Resource cannot be assigned right now']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE resources 
                    SET status = 'assigned', assigned_to = :assigned_to
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    ':assigned_to' => $assignToId,
                    ':id' => $resourceId
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Get resource name
                    $stmt = $pdo->prepare("SELECT name FROM resources WHERE id = :id");
                    $stmt->execute([':id' => $resourceId]);
                    $resourceName = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
                    
                    // Log activity
                    $stmt = $pdo->prepare("
                        INSERT INTO activities (user_id, type, description, related_id, related_type)
                        VALUES (:user_id, 'resource_booked', :description, :resource_id, 'resource')
                    ");
                    $stmt->execute([
                        ':user_id' => $requestUserId,
                        ':description' => "booked $resourceName",
                        ':resource_id' => $resourceId
                    ]);
                    
                    // Create notification
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message)
                        VALUES (:user_id, 'success', 'Resource Assigned', :message)
                    ");
                    $stmt->execute([
                        ':user_id' => $assignToId,
                        ':message' => "$resourceName has been assigned to you"
                    ]);
                    
                    echo json_encode(['success' => true, 'message' => 'Resource assigned successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Resource assignment failed']);
                }
                
            } elseif ($action === 'release') {
                resources_require_admin($isAdmin);
                if ($resourceId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Resource ID required']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    UPDATE resources 
                    SET status = 'available', assigned_to = NULL
                    WHERE id = :id
                ");
                
                $stmt->execute([':id' => $resourceId]);
                
                echo json_encode(['success' => true, 'message' => 'Resource released successfully']);
                
            } elseif ($action === 'maintenance') {
                resources_require_admin($isAdmin);
                if ($resourceId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Resource ID required']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    UPDATE resources 
                    SET status = 'maintenance', assigned_to = NULL
                    WHERE id = :id
                ");
                
                $stmt->execute([':id' => $resourceId]);

                // Closing pending requests prevents the resource from remaining in Requested view.
                resources_reject_pending_requests_for_resource($pdo, $resourceId, $requestUserId);
                
                echo json_encode(['success' => true, 'message' => 'Resource marked for maintenance']);

            } elseif ($action === 'create_request') {
                $requestResourceId = isset($data['resourceId']) ? (int)$data['resourceId'] : 0;
                $note = trim((string)($data['note'] ?? ''));
                $requestForUserId = isset($data['requestForUserId']) ? (int)$data['requestForUserId'] : $requestUserId;

                if ($requestResourceId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Resource is required for request']);
                    exit;
                }

                if (!$isAdmin) {
                    $requestForUserId = $requestUserId;
                } elseif ($requestForUserId <= 0) {
                    $requestForUserId = $requestUserId;
                }

                $resource = resources_fetch_resource($pdo, $requestResourceId);
                if (!$resource) {
                    echo json_encode(['success' => false, 'message' => 'Resource not found']);
                    exit;
                }

                if ($resource['status'] !== 'available') {
                    echo json_encode(['success' => false, 'message' => 'Only available resources can be requested']);
                    exit;
                }

                if (resources_has_pending_request_for_resource($pdo, $requestResourceId)) {
                    echo json_encode(['success' => false, 'message' => 'This resource already has a pending request']);
                    exit;
                }

                $requestForUser = flowstone_fetch_user($pdo, $requestForUserId);
                if (!$requestForUser) {
                    echo json_encode(['success' => false, 'message' => 'Request target user not found']);
                    exit;
                }

                $payload = resources_encode_request_payload($requestResourceId, $requestForUserId, $note);

                $stmt = $pdo->prepare('
                    INSERT INTO approvals (type, requested_by, department, description, status)
                    VALUES (:type, :requested_by, :department, :description, :status)
                ');
                $stmt->execute([
                    ':type' => 'Resource Request',
                    ':requested_by' => $requestUserId,
                    ':department' => $requestUser['role'] ?? null,
                    ':description' => $payload,
                    ':status' => 'pending',
                ]);

                echo json_encode(['success' => true, 'message' => 'Resource request submitted successfully']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                exit;
            }
            
        } else {
            // Create new resource
            resources_require_admin($isAdmin);

            $name = $data['name'] ?? '';
            $type = $data['type'] ?? '';
            $location = $data['location'] ?? '';
            $description = $data['description'] ?? '';
            $assignTo = isset($data['assignTo']) ? (int)$data['assignTo'] : 0;
            
            if (empty($name) || empty($type)) {
                echo json_encode(['success' => false, 'message' => 'Name and type are required']);
                exit;
            }

            $status = 'available';
            if ($assignTo > 0) {
                $assignee = flowstone_fetch_user($pdo, $assignTo);
                if (!$assignee) {
                    echo json_encode(['success' => false, 'message' => 'Assigned user not found']);
                    exit;
                }
                $status = 'assigned';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO resources (name, type, status, location, description, assigned_to)
                VALUES (:name, :type, :status, :location, :description, :assigned_to)
            ");
            
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':status' => $status,
                ':location' => $location,
                ':description' => $description,
                ':assigned_to' => $assignTo > 0 ? $assignTo : null,
            ]);
            
            $resourceId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Resource created successfully',
                'id' => $resourceId
            ]);
        }

    } elseif ($method === 'PUT' || $method === 'PATCH') {
        $data = $bodyData;
        $action = $data['action'] ?? '';

        if ($action === 'update_resource') {
            resources_require_admin($isAdmin);

            $resourceId = isset($data['id']) ? (int)$data['id'] : 0;
            if ($resourceId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Resource ID is required']);
                exit;
            }

            $resource = resources_fetch_resource($pdo, $resourceId);
            if (!$resource) {
                echo json_encode(['success' => false, 'message' => 'Resource not found']);
                exit;
            }

            $name = trim((string)($data['name'] ?? ''));
            $type = trim((string)($data['type'] ?? ''));
            $location = trim((string)($data['location'] ?? ''));
            $description = trim((string)($data['description'] ?? ''));
            $status = trim((string)($data['status'] ?? $resource['status']));
            $assignTo = isset($data['assignTo']) ? (int)$data['assignTo'] : null;

            if ($name === '' || $type === '') {
                echo json_encode(['success' => false, 'message' => 'Name and type are required']);
                exit;
            }

            $allowedStatus = ['available', 'assigned', 'maintenance'];
            if (!in_array($status, $allowedStatus, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit;
            }

            if ($assignTo !== null && $assignTo > 0) {
                $assignee = flowstone_fetch_user($pdo, $assignTo);
                if (!$assignee) {
                    echo json_encode(['success' => false, 'message' => 'Assigned user not found']);
                    exit;
                }
                $status = 'assigned';
            }

            if ($assignTo !== null && $assignTo <= 0) {
                $assignTo = null;
                if ($status === 'assigned') {
                    $status = 'available';
                }
            }

            $stmt = $pdo->prepare('
                UPDATE resources
                SET name = :name,
                    type = :type,
                    status = :status,
                    location = :location,
                    description = :description,
                    assigned_to = :assigned_to
                WHERE id = :id
            ');
            $stmt->execute([
                ':name' => $name,
                ':type' => $type,
                ':status' => $status,
                ':location' => $location,
                ':description' => $description,
                ':assigned_to' => $assignTo,
                ':id' => $resourceId,
            ]);

            echo json_encode(['success' => true, 'message' => 'Resource updated successfully']);
            exit;
        }

        if ($action === 'modify_request') {
            $requestId = isset($data['id']) ? (int)$data['id'] : 0;
            $resourceId = isset($data['resourceId']) ? (int)$data['resourceId'] : 0;
            $note = trim((string)($data['note'] ?? ''));
            $requestForUserId = isset($data['requestForUserId']) ? (int)$data['requestForUserId'] : $requestUserId;

            if ($requestId <= 0 || $resourceId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Request ID and resource are required']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, requested_by, status FROM approvals WHERE id = :id AND type = 'Resource Request'");
            $stmt->execute([':id' => $requestId]);
            $requestRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$requestRow) {
                echo json_encode(['success' => false, 'message' => 'Request not found']);
                exit;
            }

            if ($requestRow['status'] !== 'pending') {
                echo json_encode(['success' => false, 'message' => 'Only pending requests can be modified']);
                exit;
            }

            $isOwner = (int)$requestRow['requested_by'] === $requestUserId;
            if (!$isAdmin && !$isOwner) {
                echo json_encode(['success' => false, 'message' => 'You can only modify your own requests']);
                exit;
            }

            if (!$isAdmin) {
                $requestForUserId = $requestUserId;
            }

            $resource = resources_fetch_resource($pdo, $resourceId);
            if (!$resource) {
                echo json_encode(['success' => false, 'message' => 'Resource not found']);
                exit;
            }

            $requestForUser = flowstone_fetch_user($pdo, $requestForUserId);
            if (!$requestForUser) {
                echo json_encode(['success' => false, 'message' => 'Request target user not found']);
                exit;
            }

            $payload = resources_encode_request_payload($resourceId, $requestForUserId, $note);
            $stmt = $pdo->prepare('UPDATE approvals SET description = :description WHERE id = :id');
            $stmt->execute([
                ':description' => $payload,
                ':id' => $requestId,
            ]);

            echo json_encode(['success' => true, 'message' => 'Resource request updated successfully']);
            exit;
        }

        if ($action === 'review_request') {
            resources_require_admin($isAdmin);

            $requestId = isset($data['id']) ? (int)$data['id'] : 0;
            $decision = $data['decision'] ?? '';
            if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid review request']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id, requested_by, status, description FROM approvals WHERE id = :id AND type = 'Resource Request'");
            $stmt->execute([':id' => $requestId]);
            $requestRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$requestRow) {
                echo json_encode(['success' => false, 'message' => 'Request not found']);
                exit;
            }

            $currentStatus = (string)$requestRow['status'];
            $canApprove = in_array($currentStatus, ['pending', 'rejected'], true);
            $canReject = $currentStatus === 'pending';

            if (($decision === 'approve' && !$canApprove) || ($decision === 'reject' && !$canReject)) {
                echo json_encode(['success' => false, 'message' => 'Request cannot be reviewed with this action']);
                exit;
            }

            $payload = resources_decode_description($requestRow['description']);
            $resourceId = (int)($payload['resourceId'] ?? 0);
            $requestForUserId = (int)($payload['requestForUserId'] ?? (int)$requestRow['requested_by']);

            if ($decision === 'approve') {
                $resource = resources_fetch_resource($pdo, $resourceId);
                if (!$resource) {
                    echo json_encode(['success' => false, 'message' => 'Resource not found for approval']);
                    exit;
                }

                $stmt = $pdo->prepare("UPDATE resources SET status = 'assigned', assigned_to = :assigned_to WHERE id = :id");
                $stmt->execute([
                    ':assigned_to' => $requestForUserId,
                    ':id' => $resourceId,
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE resources SET status = 'available', assigned_to = NULL WHERE id = :id");
                $stmt->execute([':id' => $resourceId]);
            }

            $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
            $stmt = $pdo->prepare('UPDATE approvals SET status = :status, approved_by = :approved_by, approved_at = NOW() WHERE id = :id');
            $stmt->execute([
                ':status' => $newStatus,
                ':approved_by' => $requestUserId,
                ':id' => $requestId,
            ]);

            echo json_encode(['success' => true, 'message' => 'Request ' . $newStatus . ' successfully']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Invalid update action']);
        exit;
        
    } elseif ($method === 'DELETE') {
        resources_require_admin($isAdmin);

        // Delete resource
        $data = $bodyData;
        $resourceId = $data['id'] ?? 0;
        
        if (!$resourceId) {
            echo json_encode(['success' => false, 'message' => 'Resource ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM resources WHERE id = :id");
        $stmt->execute([':id' => $resourceId]);
        
        echo json_encode(['success' => true, 'message' => 'Resource deleted successfully']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
