<?php
// Password reset request - Send OTP via SMS (with email fallback)
error_log("=== request_password_reset.php START ===");
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

// Accept form-urlencoded data (matching frontend)
$phone = trim($_POST['phone'] ?? '');
error_log("Phone received: '$phone'");

// Validation - 11-digit Philippine mobile number
if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    error_log("Phone validation failed: '$phone'");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 11-digit mobile number is required']);
    exit;
}

try {
    // Check if user exists and get their email for fallback
    $stmt = $pdo->prepare("
        SELECT u.id, u.email FROM users u 
        INNER JOIN youth_profiles yp ON u.id = yp.user_id 
        WHERE yp.phone = ?
    ");
    $stmt->execute([$phone]);
    $result = $stmt->fetch();
    
    if (!$result) {
        error_log("User not found for phone: $phone");
        // Don't reveal if number exists (security best practice)
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists, you will receive an OTP via SMS or Email'
        ]);
        exit;
    }
    
    $user_id = $result['id'];
    $user_email = $result['email'];
    error_log("User found - ID: $user_id, Email: $user_email");
    
    // Generate 6-digit OTP
    $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    error_log("OTP generated: $otp_code");
    
    // Clear any existing non-used OTP for this user
    $delete_stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND is_used = false");
    $delete_stmt->execute([$user_id]);
    error_log("Cleared old OTPs for user: $user_id");
    
    // Insert new OTP record (expires in 15 minutes)
    $expires_at = date('Y-m-d H:i:s', time() + 900); // 900 seconds = 15 minutes
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, otp_code, expires_at, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    if (!$insert_stmt->execute([$user_id, $otp_code, $expires_at])) {
        throw new Exception("Failed to create password reset request");
    }
    error_log("OTP stored in database for user: $user_id");
    
    // Format phone for SMS API (convert to +63 format)
    $formatted_phone = '+63' . substr($phone, 1);
    error_log("Formatted phone: $formatted_phone");
    
    // Send OTP via SMS using SMS API Philippines (with automatic email fallback)
    $sms_message = "Your SKalinga password reset code is: $otp_code. Valid for 15 minutes.";
    
    error_log("Attempting to send SMS to: $formatted_phone");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://smsapiph.onrender.com/api/v1/send/sms');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // Disable SSL/TLS verification for this endpoint
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: sk-2b10j0whlzeqvf64h6t2fes2oksm2qzm',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'recipient' => $formatted_phone,
        'message' => $sms_message
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("SMS API Response Code: $http_code");
    error_log("SMS API Response: $response");
    if ($curl_error) {
        error_log("cURL Error: $curl_error");
    }
    
    $sms_response = json_decode($response, true);
    
    if ($http_code === 200 && isset($sms_response['success']) && $sms_response['success']) {
        error_log("SMS sent successfully");
        echo json_encode([
            'success' => true,
            'message' => 'OTP has been sent to your mobile number. Check SMS or email if SMS fails.',
            'user_id' => $user_id
        ]);
    } else {
        error_log("SMS send failed. HTTP Code: $http_code. Response: $response");
        // SMS API will automatically fallback to email, so we still return success
        echo json_encode([
            'success' => true,
            'message' => 'OTP is being sent to your mobile number or email',
            'user_id' => $user_id,
            '_debug' => 'SMS gateway fallback: if SMS fails, it will be sent via email'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Password reset error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
