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

authJsonResponse([
    'authenticated' => true,
    'user' => $auth,
    'role' => $auth['role']
]);
