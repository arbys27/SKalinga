<?php
// get_user_profile.php - Fetch current logged-in user info
header('Content-Type: application/json');

session_start();

$requestedRole = $_GET['role'] ?? 'any';

try {
    // For youth pages requesting youth profile, skip admin session
    if ($requestedRole !== 'youth' && isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        $userName = $_SESSION['admin_username'] ?? 'Administrator';
        $userRole = 'Super Admin';
        
        // Capitalize first letter of username
        $userName = ucfirst(strtolower($userName));
        
        // Get avatar initials from username
        $names = explode(' ', $userName);
        $initials = '';
        foreach ($names as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }
        $initials = substr($initials, 0, 2);
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
                'initials' => $initials,
                'member_id' => 'ADM-' . $userId,
                'issued_date' => date('F Y')
            ]
        ]);
        exit;
    }
    
    // Check if regular user (youth) is logged in (for youth pages)
    if (isset($_SESSION['user_id'])) {
        require_once 'db_connect.php';
        
        $userId = $_SESSION['user_id'];
        $firstName = $_SESSION['firstname'] ?? 'User';
        $lastName = $_SESSION['lastname'] ?? '';
        
        // Capitalize names properly
        $firstName = ucfirst(strtolower($firstName));
        $lastName = ucfirst(strtolower($lastName));
        
        $userName = $firstName . ($lastName ? ' ' . $lastName : '');
        $userRole = 'Youth Member';
        
        // Get avatar initials from name
        $names = explode(' ', trim($userName));
        $initials = '';
        foreach ($names as $name) {
            if (!empty($name)) {
                $initials .= strtoupper(substr($name, 0, 1));
            }
        }
        $initials = substr($initials, 0, 2);
        
        // Fetch member_id and created_at from users table
        $stmt = $conn->prepare("SELECT member_id, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $memberId = 'SK-XXXX-XXXX';
        $issuedDate = date('F Y');
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $memberId = $row['member_id'] ?? $memberId;
            $issuedDate = date('F Y', strtotime($row['created_at']));
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
                'initials' => $initials,
                'member_id' => $memberId,
                'issued_date' => $issuedDate
            ]
        ]);
        exit;
    }
    
    // Not logged in
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
