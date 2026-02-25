<?php
header('Content-Type: application/json');

echo json_encode([
    'testing' => 'SMS API Connection'
], JSON_PRETTY_PRINT);

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
    'recipient' => '+639999999999',
    'message' => 'Test SMS from SKalinga'
]));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'http_code' => $http_code,
    'response' => $response,
    'curl_error' => $curl_error,
    'parsed_response' => json_decode($response, true)
], JSON_PRETTY_PRINT);
?>
