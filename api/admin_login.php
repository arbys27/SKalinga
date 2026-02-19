<?php
/**
 * Admin Login Function
 * Authenticates admin users against the admins table
 * Returns JSON response with success/error status
 */

session_start();
header('Content-Type: application/json');

// Import database connection
require_once 'db_connect.php';

// Disable error reporting from showing in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get POST data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required.'
    ]);
    exit;
}

try {
    // Query admin table
    $query = "SELECT id, username, password_hash, role, status, email 
              FROM admins 
              WHERE username = ? AND status = 'active' 
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password.'
        ]);
        exit;
    }
    
    // Verify password using bcrypt
    if (!password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password.'
        ]);
        exit;
    }
    
    // Update last login timestamp
    $updateQuery = "UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([$admin['id']]);
    
    // Set session variables
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_login_time'] = time();
    
    // Log successful login (optional)
    error_log("[Admin Login] User '{$admin['username']}' logged in at " . date('Y-m-d H:i:s'));
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'admin' => [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("[Admin Login Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
?>
