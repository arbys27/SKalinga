<?php
/**
 * Update Youth Member
 * Updates an existing youth member's profile
 * Tracks which admin last updated the member
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
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$birthday = isset($_POST['birthday']) ? $_POST['birthday'] : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
$phone = isset($_POST['contact']) ? trim($_POST['contact']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$barangay = isset($_POST['barangay']) ? trim($_POST['barangay']) : 'San Antonio';

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
    $userQuery = "SELECT u.id, u.email FROM users WHERE member_id = ?";
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
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update profile
    $profileQuery = "UPDATE youth_profiles 
                    SET firstname = ?, lastname = ?, birthday = ?, age = ?, gender = ?, 
                        phone = ?, address = ?, barangay = ?, updated_at = NOW()
                    WHERE user_id = ?";
    $profileStmt = $conn->prepare($profileQuery);
    $profileStmt->bind_param("sssissssi", $firstname, $lastname, $birthday, $age, $gender, $phone, $address, $barangay, $user_id);
    $profileStmt->execute();
    $profileStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Log update
    error_log("[Youth Update] Admin '$admin_username' updated youth member: $firstname $lastname ($member_id)");
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Youth member updated successfully',
        'data' => [
            'member_id' => $member_id,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'updated_by' => $admin_username
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("[Update Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating youth member'
    ]);
}

$conn->close();
?>
