<?php
/**
 * Check Admin Session
 * Verifies if an admin is currently authenticated
 * Returns JSON response with authentication status and admin info
 */

session_start();
header('Content-Type: application/json');

// Check if admin is authenticated
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Not authenticated'
    ]);
    exit;
}

// If authenticated, return success with admin info
http_response_code(200);
echo json_encode([
    'authenticated' => true,
    'admin' => [
        'id' => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'Admin',
        'role' => $_SESSION['admin_role'] ?? 'staff'
    ]
]);
?>
