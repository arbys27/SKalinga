<?php

require_once __DIR__ . '/security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
securityStartSession();

$pendingType = $_SESSION['login_pending_type'] ?? '';
$hasPendingLogin = in_array($pendingType, ['login', 'reauth'], true)
    && is_string($_SESSION['login_otp_hash'] ?? null)
    && time() < (int)($_SESSION['login_otp_expires_at'] ?? 0);

echo json_encode([
    'success' => true,
    'pending' => $hasPendingLogin,
    'reauth' => !empty($_SESSION['otp_reauth_required']) || $pendingType === 'reauth',
    'phone' => $hasPendingLogin && is_string($_SESSION['login_phone'] ?? null)
        ? otpMaskPhone($_SESSION['login_phone'])
        : null
]);
