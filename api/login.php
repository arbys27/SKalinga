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

// Get and sanitize form data - support both email and phone/mobile
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';

// Determine which field was provided
$login_field = '';
$login_value = '';

if (!empty($email)) {
    $login_field = 'email';
    $login_value = $email;
} elseif (!empty($phone)) {
    $login_field = 'phone';
    $login_value = $phone;
} elseif (!empty($mobile)) {
    $login_field = 'phone';
    $login_value = $mobile;
}

// Validation
if (empty($login_value)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email or phone number is required']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

// Check if user exists and verify password
try {
    if ($login_field === 'email') {
        $stmt = $pdo->prepare("
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
    } else {
        // Login with phone number
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.member_id, 
                u.password_hash, 
                u.status,
                u.email,
                p.firstname, 
                p.lastname
            FROM users u
            LEFT JOIN youth_profiles p ON u.id = p.user_id
            WHERE p.phone = ?
        ");
    }
    
    $stmt->execute([$login_value]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Check if account is active
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact support.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Update last login timestamp
    $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $update_stmt->execute([$user['id']]);

    // Login successful - start session
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['member_id'] = $user['member_id'];
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'member_id' => $user['member_id'],
        'name' => $user['firstname'] . ' ' . $user['lastname'],
        'redirect' => 'youth-portal.html'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>