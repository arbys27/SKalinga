<?php
// Verify login OTP and complete authentication
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();

$otp_input = trim($_POST['otp'] ?? '');

// Validate OTP input
if (empty($otp_input) || !preg_match('/^[0-9]{6}$/', $otp_input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 6-digit code is required']);
    exit;
}

// Check if login OTP session exists
if (!isset($_SESSION['login_otp']) || !isset($_SESSION['login_user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending login. Please try again.']);
    exit;
}

// Check if OTP has expired
if (time() > ($_SESSION['otp_expires_at'] ?? 0)) {
    unset($_SESSION['login_otp']);
    unset($_SESSION['login_user_id']);
    unset($_SESSION['otp_expires_at']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Login code has expired. Please try again.']);
    exit;
}

// Verify OTP
if ($otp_input !== $_SESSION['login_otp']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid code. Please try again.']);
    exit;
}

// OTP verified successfully - complete login
$user_id = $_SESSION['login_user_id'];
$member_id = $_SESSION['login_member_id'];
$phone = $_SESSION['login_phone'];
$trust_device = $_SESSION['login_trust_device'] ?? false;

// Update last login timestamp and optionally mark device as trusted
try {
    if ($trust_device) {
        // Update both last_login and last_otp_verified_at for trusted device
        $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP, last_otp_verified_at = CURRENT_TIMESTAMP WHERE id = ?");
    } else {
        // Just update last_login
        $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    }
    $stmt->execute([$user_id]);
    
    // Clear temporary login session data
    unset($_SESSION['login_otp']);
    unset($_SESSION['login_phone']);
    unset($_SESSION['login_user_id']);
    unset($_SESSION['login_member_id']);
    unset($_SESSION['login_firstname']);
    unset($_SESSION['login_lastname']);
    unset($_SESSION['login_trust_device']);
    unset($_SESSION['otp_expires_at']);
    
    // Set authenticated session data
    $_SESSION['user_id'] = $user_id;
    $_SESSION['member_id'] = $member_id;
    $_SESSION['phone'] = $phone;
    $_SESSION['authenticated'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'member_id' => $member_id,
        'redirect' => 'youth-portal.html'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error completing login: ' . $e->getMessage()]);
}
?>
