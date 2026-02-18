<?php
// api/update_print_status.php - Update printing request status
header('Content-Type: application/json');

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

try {
    require_once 'db.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['request_id']) || !isset($input['status'])) {
        throw new Exception("Missing required fields: request_id, status");
    }
    
    $request_id = intval($input['request_id']);
    $status = $input['status'];
    
    // Validate status value
    $valid_statuses = ['Pending', 'Printing', 'Completed', 'Claimed'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception("Invalid status. Allowed: " . implode(', ', $valid_statuses));
    }
    
    // Check if request exists
    $stmt = $pdo->prepare("SELECT request_id FROM printing_requests WHERE request_id = ?");
    $stmt->execute([$request_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Print request not found");
    }
    
    // Update status
    $claimed_at = null;
    if ($status === 'Claimed') {
        $claimed_at = date('Y-m-d H:i:s');
    }
    
    $stmt = $pdo->prepare("
        UPDATE printing_requests 
        SET status = ?, 
            updated_at = NOW(),
            claimed_at = ?
        WHERE request_id = ?
    ");
    
    $stmt->execute([$status, $claimed_at, $request_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'request_id' => $request_id,
        'status' => $status
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
