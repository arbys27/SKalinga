<?php
// API: Update Incident Status (Admin)
header('Content-Type: application/json');

require 'db_connect.php';
require 'check_admin_session.php';

$data = json_decode(file_get_contents('php://input'), true);

$incident_id = $data['incident_id'] ?? null;
$status = trim($data['status'] ?? '');
$admin_notes = trim($data['admin_notes'] ?? '');

// Validate input
if (!$incident_id || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$valid_statuses = ['pending', 'in_review', 'under_action', 'resolved', 'closed'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        UPDATE incidents
        SET status = ?, admin_notes = ?, updated_date = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    
    $stmt->execute([$status, $admin_notes, $incident_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident status updated successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to update incident']);
}
?>
