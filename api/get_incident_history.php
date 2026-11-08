<?php

require_once __DIR__ . '/auth.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$incidentId = filter_var($_GET['incident_id'] ?? null, FILTER_VALIDATE_INT);
if ($incidentId === false || $incidentId === null || $incidentId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid incident ID is required.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

try {
    $stmt = $pdo->prepare(
        'SELECT id, incident_id, old_status, new_status, changed_by, changed_by_name,
                notes, old_assigned_to, old_handler_name, new_assigned_to,
                new_handler_name, created_at
         FROM incident_status_history
         WHERE incident_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([$incidentId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $exception) {
    error_log('[Incident History] Database error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to retrieve incident history.']);
}
