<?php
/**
 * Send SMS OTP to a phone number
 * POST /api/send_sms_otp.php
 * 
 * Parameters:
 * - phone: string (11-digit Philippine mobile number)
 * 
 * Response: JSON
 * {
 *   "success": true/false,
 *   "message": "string",
 *   "otp_id": "string" (optional, for tracking)
 * }
 */

header('Content-Type: application/json');

try {
    // Validate phone number
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    if (empty($phone)) {
        throw new Exception('Phone number is required');
    }
    
    // Validate format (11-digit Philippine number)
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        throw new Exception('Invalid phone number format');
    }
    
    // TODO: Implement SMS OTP sending logic
    // You can use services like:
    // - Nexmo/Vonage
    // - Twilio
    // - AWS SNS
    // - Local SMS gateway provider
    
    // Generate OTP code (6 digits)
    $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // TODO: Store OTP in session or database with phone number and expiration time
    // Example: $_SESSION['sms_otp_' . $phone] = ['code' => $otp_code, 'expires_at' => time() + 600];
    // Or in database: INSERT INTO sms_otps (phone, code, expires_at) VALUES (?, ?, ?)
    
    // For development/testing, log the OTP to console
    // In production, actually send via SMS
    error_log("SMS OTP for $phone: $otp_code");
    
    // Simulate SMS sending success
    echo json_encode([
        'success' => true,
        'message' => 'OTP sent successfully to your phone number',
        'otp_id' => bin2hex(random_bytes(8)) // For tracking purposes
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
