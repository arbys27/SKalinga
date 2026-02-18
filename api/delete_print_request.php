<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in (admin session required)
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    require_once 'db.php';
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $request_id = $data['request_id'] ?? null;

    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        exit;
    }

    // Delete the print request
    $stmt = $pdo->prepare("DELETE FROM printing_requests WHERE request_id = ?");
    $stmt->execute([$request_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Print request deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

