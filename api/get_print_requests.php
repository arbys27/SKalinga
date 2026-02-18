<?php
// api/get_print_requests.php - Fetch all print requests for admin dashboard
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
    
    // Fetch all print requests sorted by created_at DESC
    $stmt = $pdo->query("
        SELECT 
            request_id,
            member_id,
            member_name,
            document_title,
            file_path,
            file_name,
            file_size,
            print_type,
            paper_size,
            copies,
            status,
            created_at,
            updated_at,
            claimed_at,
            notes
        FROM printing_requests
        ORDER BY created_at DESC
    ");
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'total' => count($requests)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
