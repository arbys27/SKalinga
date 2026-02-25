<?php
// Simple SMS API Test - No Database Required
header('Content-Type: application/json');

error_log("=== SMS TEST ENDPOINT ===");

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');

if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid 11-digit phone number is required'
    ]);
    exit;
}

// Generate test OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$sms_message = "Your SKalinga test code is: $otp. Valid for 10 minutes.";

// Format phone for SMS API (convert to +63 format)
$formatted_phone = '+63' . substr($phone, 1);

error_log("Test SMS to: $formatted_phone, OTP: $otp");
error_log("Message: $sms_message");

// Send SMS via SMS API Philippines
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://sms-api-ph-gceo.onrender.com/send/sms');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
// Disable SSL/TLS verification for this endpoint
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'x-api-key: sk-e481790680e0f0783c3cc8af',
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
        'message' => 'Test OTP sent successfully!',
        'phone' => $phone,
        'formatted_phone' => $formatted_phone,
        'test_otp' => $otp
    ]);
} else {
    error_log("SMS API failed: " . print_r($sms_response, true));
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send SMS',
        'phone' => $phone,
        'formatted_phone' => $formatted_phone,
        'api_response' => [
            'http_code' => $http_code,
            'response' => $sms_response,
            'curl_error' => $curl_error
        ],
        'test_otp' => $otp  // Show test OTP for debugging
    ]);
}
?>
