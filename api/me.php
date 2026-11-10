<?php

require_once 'auth.php';

authStartSession();
if (!empty($_SESSION['otp_reauth_required'])) {
    authJsonResponse([
        'authenticated' => false,
        'otp_required' => true,
        'message' => 'OTP verification is required to continue.'
    ], 401);
}

$auth = currentAuth();
if ($auth === null) {
    authJsonResponse(['authenticated' => false, 'message' => 'Not authenticated'], 401);
}

$mailUsage = null;
if (in_array($auth['role'], ['admin', 'sk_official'], true)) {
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/../includes/mail.php';
    $mailUsage = mailUsageStatus($pdo);
}

authJsonResponse([
    'authenticated' => true,
    'user' => $auth,
    'role' => $auth['role'],
    'otp_off' => !empty($_SESSION['login_otp_off']),
    'mail_usage' => $mailUsage
]);
