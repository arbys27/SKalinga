<?php

require_once __DIR__ . '/auth.php';
$auth = requireAdminApi();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

try {
    $stmt = $pdo->prepare(
        "SELECT id, username
         FROM admins
         WHERE status = 'active'
         ORDER BY LOWER(username), id"
    );
    $stmt->execute();
    echo json_encode([
        'success' => true,
        'current_admin_id' => (int)$auth['id'],
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (PDOException $exception) {
    error_log('[Incident Handlers] Database error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to retrieve available handlers.']);
}
