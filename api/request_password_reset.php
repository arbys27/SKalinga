<?php
// Password reset request - Send OTP
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON body since frontend sends JSON
$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');

// Validation - 11-digit Philippine mobile number
if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 11-digit mobile number is required']);
    exit;
}

try {
    // Check if user exists by phone - join users and youth_profiles tables
    $stmt = $conn->prepare("
        SELECT u.id FROM users u 
        INNER JOIN youth_profiles yp ON u.id = yp.user_id 
        WHERE yp.phone = ?
    ");
    $stmt->execute([$phone]);
    $result = $stmt->fetchAll();
    
    if (empty($result)) {
        // Don't reveal if number exists (security best practice)
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists, you will receive an OTP via SMS'
        ]);
        exit;
    }
    
    $user = $result[0];
    $user_id = $user['id'];
    
    // Generate 6-digit OTP
    $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Clear any existing non-used OTP for this user
    $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND is_used = false");
    $delete_stmt->execute([$user_id]);
    
    // Insert new OTP record (expires in 15 minutes)
    $expires_at = date('Y-m-d H:i:s', time() + 900); // 900 seconds = 15 minutes
    
    $insert_stmt = $conn->prepare("
        INSERT INTO password_resets (user_id, otp_code, expires_at, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    if (!$insert_stmt->execute([$user_id, $otp_code, $expires_at])) {
        throw new Exception("Failed to create password reset request");
    }
    
    // TODO: Send OTP via SMS
    // You would implement SMS sending here using Nexmo, Twilio, AWS SNS, or local SMS gateway
    // For now, log to file for testing
    error_log("OTP for $phone: $otp_code (expires at $expires_at)");
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your mobile number',
        'user_id' => $user_id,
        'otp' => $otp_code // TODO: Remove in production, send via SMS instead
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Password reset error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
