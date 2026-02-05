<?php
/**
 * Check Admin Session Function
 * Verifies if an admin is currently authenticated
 * Returns JSON response with authentication status
 */

session_start();
header('Content-Type: application/json');

// Check if admin is authenticated
$authenticated = isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;

if ($authenticated) {
    // Session is valid, return admin info
    http_response_code(200);
    echo json_encode([
        'authenticated' => true,
        'admin' => [
            'id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'email' => $_SESSION['admin_email'],
            'role' => $_SESSION['admin_role']
        ]
    ]);
} else {
    // Session is not valid
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
}
?>
