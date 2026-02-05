<?php
// Get user profile with session validation
require_once 'db_connect.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get complete user profile by joining users and youth_profiles
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.email,
            u.member_id,
            u.status,
            u.email_verified,
            u.last_login,
            u.created_at,
            p.firstname,
            p.lastname,
            p.birthday,
            p.age,
            p.gender,
            p.phone,
            p.address,
            p.barangay,
            p.avatar_path,
            p.bio
        FROM users u
        LEFT JOIN youth_profiles p ON u.id = p.user_id
        WHERE u.id = ?
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Get event attendance count (if events table exists)
    $events_count = 0;
    $check_events = $conn->query("SHOW TABLES LIKE 'event_registrations'");
    if ($check_events->num_rows > 0) {
        $event_stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM event_registrations 
            WHERE user_id = ? AND status = 'Attended'
        ");
        $event_stmt->bind_param("i", $user_id);
        $event_stmt->execute();
        $event_result = $event_stmt->get_result();
        $event_data = $event_result->fetch_assoc();
        $events_count = $event_data['count'] ?? 0;
        $event_stmt->close();
    }
    
    // Return success response with all user data
    echo json_encode([
        'success' => true,
        'id' => $user['id'],
        'email' => $user['email'],
        'member_id' => $user['member_id'],
        'firstname' => $user['firstname'],
        'lastname' => $user['lastname'],
        'birthday' => $user['birthday'],
        'age' => $user['age'],
        'gender' => $user['gender'],
        'phone' => $user['phone'],
        'address' => $user['address'],
        'barangay' => $user['barangay'],
        'avatar_path' => $user['avatar_path'],
        'bio' => $user['bio'],
        'status' => $user['status'],
        'email_verified' => (bool)$user['email_verified'],
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login'],
        'events_attended' => $events_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error retrieving user data: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>