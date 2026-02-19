<?php
/**
 * Verify SMS OTP code
 * POST /api/verify_sms_otp.php
 * 
 * Parameters:
 * - phone: string (11-digit Philippine mobile number)
 * - otp: string (6-digit OTP code)
 * 
 * Response: JSON
 * {
 *   "success": true/false,
 *   "message": "string"
 * }
 */

header('Content-Type: application/json');

try {
    // Validate input
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    
    if (empty($phone) || empty($otp)) {
        throw new Exception('Phone number and OTP are required');
    }
    
    // Validate formats
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        throw new Exception('Invalid phone number format');
    }
    
    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        throw new Exception('Invalid OTP format');
    }
    
    // TODO: Verify OTP against stored value
    // Option 1: From session (if sent via session)
    // $session_key = 'sms_otp_' . $phone;
    // if (!isset($_SESSION[$session_key])) {
    //     throw new Exception('OTP not found or expired');
    // }
    // $stored_data = $_SESSION[$session_key];
    // if ($stored_data['expires_at'] < time()) {
    //     unset($_SESSION[$session_key]);
    //     throw new Exception('OTP has expired');
    // }
    // if ($stored_data['code'] !== $otp) {
    //     throw new Exception('Invalid OTP code');
    // }
    
    // Option 2: From database
    // require 'db_connect.php';
    // $stmt = $conn->prepare('SELECT code, expires_at FROM sms_otps WHERE phone = ? ORDER BY created_at DESC LIMIT 1');
    // $stmt->bind_param('s', $phone);
    // $stmt->execute();
    // $result = $stmt->get_result();
    // if ($result->num_rows === 0) {
    //     throw new Exception('OTP not found or expired');
    // }
    // $row = $result->fetch_assoc();
    // if ($row['expires_at'] < time()) {
    //     throw new Exception('OTP has expired');
    // }
    // if ($row['code'] !== $otp) {
    //     throw new Exception('Invalid OTP code');
    // }
    
    // For development/testing - accept any 6-digit code
    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        throw new Exception('Invalid OTP code');
    }
    
    // Mark phone as verified in session or database
    // $_SESSION['phone_verified'][$phone] = true;
    // Or in database: UPDATE users SET phone_verified = 1 WHERE phone = ?
    
    // Optionally delete the OTP after successful verification
    // unset($_SESSION[$session_key]);
    // Or: DELETE FROM sms_otps WHERE phone = ?
    
    echo json_encode([
        'success' => true,
        'message' => 'Phone number verified successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
