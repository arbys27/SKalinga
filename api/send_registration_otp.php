<?php
// Send OTP via SMS for registration verification
error_log("=== send_registration_otp.php START ===");
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

$phone = trim($_POST['phone'] ?? '');
error_log("Phone received: '$phone'");

// Validate phone format
if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    error_log("Phone validation failed: '$phone'");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 11-digit phone number is required']);
    exit;
}

// Check if phone already registered
try {
    $stmt = $pdo->prepare("
        SELECT id FROM youth_profiles 
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$phone]);
    error_log("Phone check - Database query executed");
    
    if ($stmt->rowCount() > 0) {
        error_log("Phone already registered: $phone");
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
        exit;
    }
    
    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    error_log("OTP generated: $otp for phone: $phone");
    
    // Store OTP in session (expires in 10 minutes)
    session_start();
    $_SESSION['registration_otp'] = $otp;
    $_SESSION['registration_phone'] = $phone;
    $_SESSION['otp_expires_at'] = time() + 600; // 10 minutes
    error_log("OTP stored in session");
    
    // Format phone for SMS API (convert to +63 format)
    $formatted_phone = '+63' . substr($phone, 1);
    error_log("Formatted phone: $formatted_phone");
    
    // Send SMS via SMS API Philippines
    $sms_message = "Your SKalinga verification code is: $otp. Valid for 10 minutes.";
    
    error_log("Attempting to send SMS to: $formatted_phone");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://smsapi.ph.onrender.com/api/v1/send/sms');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: sk-2b10esfbwfbxau5qbrp9j8yb7ws1dg81',
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
            'message' => 'OTP sent successfully to your phone',
            'phone' => $phone
        ]);
    } else {
        // Log SMS failure but don't block user - allow manual verification
        error_log("SMS API Error: " . print_r($sms_response, true));
        echo json_encode([
            'success' => true,
            'message' => 'OTP has been generated. Check your phone or email.',
            'phone' => $phone,
            'debug' => 'SMS fallback mode',
            'api_response' => [
                'http_code' => $http_code,
                'response' => $sms_response,
                'curl_error' => $curl_error
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Exception in send_registration_otp: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error generating OTP: ' . $e->getMessage()]);
}
error_log("=== send_registration_otp.php END ===");
?>
