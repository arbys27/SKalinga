<?php
// TEST SCRIPT - Debug API connectivity and database
// Access at: localhost/SKalinga/test_api.php

header('Content-Type: application/json');

$results = [];

// Test 1: Database Connection
$results['database'] = [];
try {
    require_once 'api/db_connect.php';
    $results['database']['status'] = 'connected';
    $results['database']['message'] = 'Supabase connection successful';
} catch (Exception $e) {
    $results['database']['status'] = 'error';
    $results['database']['message'] = $e->getMessage();
}

// Test 2: Session Creation
$results['session'] = [];
session_start();
$_SESSION['test'] = 'working';
$results['session']['status'] = $_SESSION['test'] === 'working' ? 'working' : 'error';

// Test 3: SMS API Connection
$results['sms_api'] = [];
$sms_test = curl_init();
curl_setopt($sms_test, CURLOPT_URL, 'https://sms-api-ph-gceo.onrender.com/send/sms');
curl_setopt($sms_test, CURLOPT_RETURNTRANSFER, true);
curl_setopt($sms_test, CURLOPT_POST, true);
curl_setopt($sms_test, CURLOPT_TIMEOUT, 10);
// Disable SSL/TLS verification for this endpoint
curl_setopt($sms_test, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($sms_test, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($sms_test, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
curl_setopt($sms_test, CURLOPT_HTTPHEADER, [
    'x-api-key: sk-e481790680e0f0783c3cc8af',
    'Content-Type: application/json'
]);
curl_setopt($sms_test, CURLOPT_POSTFIELDS, json_encode([
    'recipient' => '+639999999999',
    'message' => 'Test message'
]));

$response = curl_exec($sms_test);
$http_code = curl_getinfo($sms_test, CURLINFO_HTTP_CODE);
$error = curl_error($sms_test);
curl_close($sms_test);

$results['sms_api']['http_code'] = $http_code;
$results['sms_api']['status'] = ($http_code === 200 || $http_code === 400) ? 'reachable' : 'unreachable';
$results['sms_api']['response'] = $response;
if ($error) {
    $results['sms_api']['curl_error'] = $error;
}

// Test 4: Check if API files exist
$results['files'] = [];
$files_to_check = [
    'api/send_registration_otp.php',
    'api/verify_registration_otp.php',
    'api/register.php',
    'api/db_connect.php'
];

foreach ($files_to_check as $file) {
    $results['files'][$file] = file_exists($file) ? 'exists' : 'missing';
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
