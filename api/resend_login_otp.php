<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

securityStartSession();
securityRequireCsrf();
$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'send_otp'
);
if (!$verification['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $verification['message']]);
    exit;
}

if (($_SESSION['login_pending_type'] ?? '') !== 'login') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No login verification is pending. Please sign in again.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$userId = (int)($_SESSION['login_user_id'] ?? 0);
$role = (string)($_SESSION['login_role'] ?? '');
$phone = (string)($_SESSION['login_phone'] ?? '');
if ($userId < 1 || $phone === '' || !in_array($role, ['youth', 'admin', 'sk_official'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login verification expired. Please sign in again.']);
    exit;
}

try {
    otpReserveSend($pdo, 'login', $role . ':' . $userId);
    $code = otpGenerateCode();
    otpSendSms($phone, $code, 'login');
    $_SESSION['login_otp_hash'] = otpCodeHash($code);
    $_SESSION['login_otp_expires_at'] = time() + 300;
    $_SESSION['login_otp_attempts'] = 0;

    echo json_encode([
        'success' => true,
        'message' => 'A new verification code was sent to ' . otpMaskPhone($phone) . '.'
    ]);
} catch (RuntimeException $exception) {
    $limited = strpos($exception->getMessage(), 'rate limited') !== false;
    http_response_code($limited ? 429 : 503);
    error_log('[Login OTP] Resend failed: ' . $exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $limited
            ? otpRateLimitMessage($exception->getMessage())
            : 'Unable to send the verification code right now.'
    ]);
} catch (Throwable $exception) {
    error_log('[Login OTP] Resend failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process the verification request.']);
}
