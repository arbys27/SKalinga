<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and sanitize form data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Check if user exists and verify password (JOIN with profile data)
$stmt = $conn->prepare("
    SELECT 
        u.id, 
        u.member_id, 
        u.password_hash, 
        u.status,
        p.firstname, 
        p.lastname
    FROM users u
    LEFT JOIN youth_profiles p ON u.id = p.user_id
    WHERE u.email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if account is active
if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact support.']);
    $conn->close();
    exit;
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    $conn->close();
    exit;
}

// Update last login timestamp
$update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
$update_stmt->bind_param("i", $user['id']);
$update_stmt->execute();
$update_stmt->close();

// Login successful - start session
session_start();
$_SESSION['user_id'] = $user['id'];
$_SESSION['member_id'] = $user['member_id'];
$_SESSION['email'] = $email;
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname'] = $user['lastname'];

echo json_encode([
    'success' => true,
    'message' => 'Login successful!',
    'member_id' => $user['member_id'],
    'name' => $user['firstname'] . ' ' . $user['lastname'],
    'redirect' => 'youth-portal.html'
]);

$conn->close();
?>