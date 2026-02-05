<?php
// get_user_profile.php - Fetch current logged-in user info
header('Content-Type: application/json');

session_start();

try {
    // Check if admin is logged in
    if (isset($_SESSION['admin_id'])) {
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
                'initials' => $initials
            ]
        ]);
        exit;
    }
    
    // Check if regular user is logged in
    if (isset($_SESSION['user_id'])) {
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
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'name' => $userName,
                'role' => $userRole,
                'initials' => $initials
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
