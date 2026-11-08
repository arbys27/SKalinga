<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function sendOTP(string $mobile, string $message): void
{
    $config = skalingaConfig();
    $apiKey = $config['UNISMS_API_KEY'] ?? '';
    $senderId = $config['UNISMS_SENDER_ID'] ?? '';

    if (!is_string($apiKey) || trim($apiKey) === ''
        || !is_string($senderId) || trim($senderId) === '') {
        throw new RuntimeException('UniSMS API credentials and sender ID are not configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to send OTP messages.');
    }
    if (!preg_match('/^(?:09\d{9}|\+639\d{9})$/', $mobile)) {
        throw new InvalidArgumentException('The mobile number is not in a supported Philippine format.');
    }
    if ($message === '' || strlen($message) > 670) {
        throw new InvalidArgumentException('The SMS message must contain 1 to 670 characters.');
    }

    $recipient = str_starts_with($mobile, '09') ? '+63' . substr($mobile, 1) : $mobile;
    $curl = curl_init('https://unismsapi.com/api/sms');
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the UniSMS connection.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'content' => $message,
            'sender_id' => trim($senderId),
            'recipient' => $recipient,
        ], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim($apiKey),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($response) || $status < 200 || $status >= 300) {
        error_log('[UniSMS] Delivery request failed; HTTP ' . $status . '; ' . $curlError);
        throw new RuntimeException('Unable to send the verification code right now.');
    }

    $decoded = json_decode($response, true);
    $messageResult = is_array($decoded) ? ($decoded['message'] ?? null) : null;
    if (!is_array($messageResult)
        || !isset($messageResult['reference_id'])
        || !is_string($messageResult['reference_id'])
        || $messageResult['reference_id'] === '') {
        error_log('[UniSMS] Provider returned an unexpected successful response; HTTP ' . $status);
        throw new RuntimeException('Unable to send the verification code right now.');
    }
}
