<?php
/**
 * Get Youth Members
 * Retrieves all or a specific youth member from database
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

$youth_id = isset($_GET['id']) ? $_GET['id'] : null;

try {
    if ($youth_id) {
        // Get specific youth member
        $query = "SELECT u.id, u.email, u.member_id, u.status, u.created_at, 
                         p.firstname, p.lastname, p.birthday, p.age, p.gender, 
                         p.phone, p.address, p.barangay, p.avatar_path
                  FROM users u
                  LEFT JOIN youth_profiles p ON u.id = p.user_id
                  WHERE u.member_id = ?
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $youth_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Youth member not found']);
            $stmt->close();
            exit;
        }
        
        $youth = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => $youth
        ]);
        
    } else {
        // Get all youth members
        $query = "SELECT u.id, u.email, u.member_id, u.status, u.created_at,
                         p.firstname, p.lastname, p.birthday, p.age, p.gender,
                         p.phone, p.address, p.barangay
                  FROM users u
                  LEFT JOIN youth_profiles p ON u.id = p.user_id
                  ORDER BY u.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $youth = [];
        while ($row = $result->fetch_assoc()) {
            $youth[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $youth,
            'count' => count($youth)
        ]);
    }
    
} catch (Exception $e) {
    error_log("[Get Youth Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching youth data'
    ]);
}

$conn->close();
?>
