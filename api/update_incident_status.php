<?php

require_once __DIR__ . '/auth.php';
$auth = requireAdminApi();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/security_helpers.php';
securityRequireCsrf();

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body.']);
    exit;
}

$incidentId = filter_var($data['incident_id'] ?? null, FILTER_VALIDATE_INT);
$requestedStatus = is_string($data['status'] ?? null) ? trim($data['status']) : '';
$notesValue = $data['notes'] ?? ($data['admin_notes'] ?? '');
if (!is_string($notesValue)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notes must be text.']);
    exit;
}
$notes = trim($notesValue);
$statusMap = [
    'submitted' => 'pending',
    'pending' => 'pending',
    'under_review' => 'in_review',
    'in_review' => 'in_review',
    'referred' => 'under_action',
    'under_action' => 'under_action',
    'resolved' => 'resolved',
    'closed' => 'closed',
];
$status = $statusMap[strtolower($requestedStatus)] ?? null;

if ($incidentId === false || $incidentId === null || $incidentId < 1 || $status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid incident ID and status are required.']);
    exit;
}
if (strlen($notes) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Notes must be 5,000 characters or fewer.']);
    exit;
}

$adminId = (int)$auth['id'];
$adminName = trim((string)($auth['name'] ?? ''));
if ($adminName === '') {
    $adminName = 'Admin #' . $adminId;
}

try {
    $pdo->beginTransaction();

    $actorQuery = $pdo->prepare(
        "SELECT username FROM admins WHERE id = ? AND status = 'active'"
    );
    $actorQuery->execute([$adminId]);
    $currentAdminName = $actorQuery->fetchColumn();
    if (!is_string($currentAdminName)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'The administrator account is not active.']);
        exit;
    }
    $adminName = $currentAdminName;

    $incidentQuery = $pdo->prepare(
        'SELECT id, status, assigned_to, handler_name
         FROM incidents
         WHERE id = ?
         FOR UPDATE'
    );
    $incidentQuery->execute([$incidentId]);
    $incident = $incidentQuery->fetch(PDO::FETCH_ASSOC);
    if (!$incident) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Incident not found.']);
        exit;
    }

    $oldAssignedTo = $incident['assigned_to'] === null ? null : (int)$incident['assigned_to'];
    $oldHandlerName = $incident['handler_name'];
    $assignedTo = $oldAssignedTo;
    $handlerName = $oldHandlerName;
    $requestedAssignee = $data['assigned_to'] ?? null;
    $assignedWasProvided = array_key_exists('assigned_to', $data)
        && $requestedAssignee !== 'keep';
    $assignToMe = !empty($data['assign_to_me']) || $requestedAssignee === 'me';

    if ($assignToMe) {
        $assigneeQuery = $pdo->prepare(
            "SELECT username FROM admins WHERE id = ? AND status = 'active'"
        );
        $assigneeQuery->execute([$adminId]);
        $assigneeName = $assigneeQuery->fetchColumn();
        if (!is_string($assigneeName)) {
            throw new RuntimeException('The current administrator cannot be assigned to this incident.');
        }
        $assignedTo = $adminId;
        $handlerName = $assignedTo === $oldAssignedTo ? $oldHandlerName : $assigneeName;
    } elseif ($assignedWasProvided) {
        if ($requestedAssignee === null || $requestedAssignee === '' || $requestedAssignee === 'none') {
            $assignedTo = null;
            $handlerName = null;
        } else {
            $requestedAdminId = filter_var($requestedAssignee, FILTER_VALIDATE_INT);
            if ($requestedAdminId === false || $requestedAdminId < 1) {
                throw new InvalidArgumentException('Choose a valid incident handler.');
            }

            $assigneeQuery = $pdo->prepare(
                "SELECT username FROM admins WHERE id = ? AND status = 'active'"
            );
            $assigneeQuery->execute([$requestedAdminId]);
            $assigneeName = $assigneeQuery->fetchColumn();
            if (!is_string($assigneeName)) {
                throw new InvalidArgumentException('The selected incident handler is not active.');
            }
            $assignedTo = (int)$requestedAdminId;
            $handlerName = $assignedTo === $oldAssignedTo ? $oldHandlerName : $assigneeName;
        }
    } elseif ($status === 'in_review' && $oldAssignedTo === null) {
        $assignedTo = $adminId;
        $handlerName = $adminName;
    }

    if ($assignedTo !== $oldAssignedTo) {
        if ($oldHandlerName !== null && $handlerName !== null) {
            $assignmentNote = 'Handler reassigned from ' . $oldHandlerName . ' to ' . $handlerName . '.';
        } elseif ($handlerName !== null) {
            $assignmentNote = 'Handler assigned to ' . $handlerName . '.';
        } elseif ($oldHandlerName !== null) {
            $assignmentNote = 'Handler assignment removed for ' . $oldHandlerName . '.';
        } else {
            $assignmentNote = 'Handler assignment changed.';
        }
        $notes = $notes === '' ? $assignmentNote : $notes . "\n" . $assignmentNote;
    }

    $update = $pdo->prepare(
        "UPDATE incidents
         SET status = ?,
             admin_notes = CASE WHEN ? <> '' THEN ? ELSE admin_notes END,
             assigned_to = ?,
             handler_name = ?,
             resolution_notes = CASE
                 WHEN ? IN ('resolved', 'closed') AND ? <> '' THEN ?
                 ELSE resolution_notes
             END,
             resolved_at = CASE
                 WHEN ? = 'resolved' THEN COALESCE(resolved_at, CURRENT_TIMESTAMP)
                 WHEN ? IN ('pending', 'in_review', 'under_action') THEN NULL
                 ELSE resolved_at
             END,
             closed_at = CASE
                 WHEN ? = 'closed' THEN COALESCE(closed_at, CURRENT_TIMESTAMP)
                 WHEN ? <> 'closed' THEN NULL
                 ELSE closed_at
             END,
             updated_date = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $update->execute([
        $status,
        $notes,
        $notes,
        $assignedTo,
        $handlerName,
        $status,
        $notes,
        $notes,
        $status,
        $status,
        $status,
        $status,
        $incidentId
    ]);

    $history = $pdo->prepare(
        'INSERT INTO incident_status_history
            (incident_id, old_status, new_status, changed_by, changed_by_name, notes,
             old_assigned_to, old_handler_name, new_assigned_to, new_handler_name)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $history->execute([
        $incidentId,
        (string)$incident['status'],
        $status,
        $adminId,
        $adminName,
        $notes === '' ? null : $notes,
        $oldAssignedTo,
        $oldHandlerName,
        $assignedTo,
        $handlerName
    ]);

    $activity = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $activity->execute([
        $adminId,
        $auth['role'],
        'incident_status_update',
        (string)$incidentId,
        clientIp(),
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Incident status and accountability history updated.',
        'handler_name' => $handlerName
    ]);
} catch (InvalidArgumentException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Incident Status] Update failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update incident status.']);
}
