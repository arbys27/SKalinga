<?php
// api/get_user_print_requests.php - Fetch print requests of logged-in youth
header('Content-Type: application/json');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. User must be logged in.'
    ]);
    exit;
}

try {
    require_once 'db.php';
    
    $user_id = $_SESSION['user_id'];
    
    // Get user's member_id
    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    $member_id = $user['member_id'];
    
    // Fetch all print requests for this user
    $stmt = $pdo->prepare("
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
        WHERE member_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$member_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $requests,
        'total' => count($requests)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
