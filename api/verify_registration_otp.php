<?php
// Verify OTP for registration
error_log("=== verify_registration_otp.php START ===");
error_log("POST data: " . print_r($_POST, true));

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
error_log("Session started. Session data: " . print_r($_SESSION, true));

$otp_input = trim($_POST['otp'] ?? '');
error_log("OTP input received: '$otp_input'");

// Validate OTP input
if (empty($otp_input) || !preg_match('/^[0-9]{6}$/', $otp_input)) {
    error_log("OTP validation failed: '$otp_input'");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 6-digit OTP is required']);
    exit;
}

// Check if OTP session exists
if (!isset($_SESSION['registration_otp']) || !isset($_SESSION['registration_phone'])) {
    error_log("No pending OTP in session");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No pending verification. Please request a new OTP.']);
    exit;
}

error_log("Session OTP: " . $_SESSION['registration_otp']);
error_log("Session Phone: " . $_SESSION['registration_phone']);

// Check if OTP has expired
if (time() > ($_SESSION['otp_expires_at'] ?? 0)) {
    error_log("OTP expired. Time now: " . time() . ", Expiry: " . ($_SESSION['otp_expires_at'] ?? 'not set'));
    unset($_SESSION['registration_otp']);
    unset($_SESSION['registration_phone']);
    unset($_SESSION['otp_expires_at']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
    exit;
}

// Verify OTP
if ($otp_input !== $_SESSION['registration_otp']) {
    error_log("OTP mismatch. Input: '$otp_input', Session: '" . $_SESSION['registration_otp'] . "'");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    exit;
}

// OTP verified successfully
error_log("OTP verified successfully!");
$_SESSION['phone_verified'] = true;
$_SESSION['verified_phone'] = $_SESSION['registration_phone'];

// Clear OTP from session
unset($_SESSION['registration_otp']);
unset($_SESSION['registration_phone']);
unset($_SESSION['otp_expires_at']);

error_log("=== verify_registration_otp.php END ===");

echo json_encode([
    'success' => true,
    'message' => 'Phone number verified successfully!',
    'phone' => $_SESSION['verified_phone']
]);
?>
