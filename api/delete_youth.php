<?php
/**
 * Delete Youth Member
 * Deletes a youth member from the database
 * Tracks which admin deleted the member
 */

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$member_id = isset($_POST['member_id']) ? trim($_POST['member_id']) : '';

// Admin info
$admin_username = $_SESSION['admin_username'];

// Validate input
if (empty($member_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Member ID is required']);
    exit;
}

try {
    // Get user ID from member_id
    $userQuery = "SELECT id FROM users WHERE member_id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("s", $member_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Youth member not found']);
        $userStmt->close();
        exit;
    }
    
    $user = $userResult->fetch_assoc();
    $user_id = $user['id'];
    $userStmt->close();
    
    // Delete user (cascade will delete profile)
    $deleteQuery = "DELETE FROM users WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteQuery);
    $deleteStmt->bind_param("i", $user_id);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Log deletion
    error_log("[Youth Deletion] Admin '$admin_username' deleted youth member: $member_id");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Youth member deleted successfully',
        'deleted_by' => $admin_username
    ]);
    
} catch (Exception $e) {
    error_log("[Delete Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting youth member'
    ]);
}

$conn->close();
?>
