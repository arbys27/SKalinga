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

if (empty($_SESSION['otp_reauth_required'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No OTP re-verification is pending.']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$userId = (int)($_SESSION['otp_reauth_user_id'] ?? 0);
$role = (string)($_SESSION['otp_reauth_role'] ?? '');
if ($userId < 1 || !in_array($role, ['youth', 'admin', 'sk_official'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'The login session has expired. Please sign in again.']);
    exit;
}

try {
    if ($role === 'youth') {
        $stmt = $pdo->prepare(
            'SELECT u.status, u.member_id, u.email, p.phone, p.firstname, p.lastname
             FROM users u
             INNER JOIN youth_profiles p ON p.user_id = u.id
             WHERE u.id = ?'
        );
    } else {
        $stmt = $pdo->prepare('SELECT status, phone, username, email FROM admins WHERE id = ?');
    }
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || $account['status'] !== 'active' || trim((string)($account['phone'] ?? '')) === '') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account cannot be re-verified. Contact the SK Office.']);
        exit;
    }

    $phone = (string)$account['phone'];
    otpReserveSend($pdo, 'login_reauth', $role . ':' . $userId);
    $code = otpGenerateCode();
    otpSendSms($phone, $code, 'login_reauth');

    $_SESSION['login_pending_type'] = 'reauth';
    $_SESSION['login_otp_hash'] = otpCodeHash($code);
    $_SESSION['login_otp_expires_at'] = time() + 300;
    $_SESSION['login_otp_attempts'] = 0;
    $_SESSION['login_user_id'] = $userId;
    $_SESSION['login_role'] = $role;
    $_SESSION['login_phone'] = $phone;
    $_SESSION['otp_reauth_redirect'] = reauthRedirect($role, $_POST['redirect'] ?? '');
    if ($role === 'youth') {
        $_SESSION['otp_reauth_member_id'] = (string)$account['member_id'];
        $_SESSION['otp_reauth_identifier'] = (string)(($account['email'] ?? null) ?: $phone);
        $_SESSION['otp_reauth_name'] = trim($account['firstname'] . ' ' . $account['lastname']);
        $_SESSION['otp_reauth_email'] = null;
    } else {
        $_SESSION['otp_reauth_identifier'] = (string)$account['username'];
        $_SESSION['otp_reauth_name'] = (string)$account['username'];
        $_SESSION['otp_reauth_email'] = $account['email'] ?? null;
    }

    echo json_encode([
        'success' => true,
        'message' => 'A verification code was sent to ' . otpMaskPhone($phone) . '.',
        'phone' => otpMaskPhone($phone)
    ]);
} catch (RuntimeException $exception) {
    $limited = strpos($exception->getMessage(), 'rate limited') !== false;
    http_response_code($limited ? 429 : 503);
    error_log('[OTP Reverification] Delivery failed: ' . $exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $limited
            ? otpRateLimitMessage($exception->getMessage())
            : 'Unable to send the verification code right now.'
    ]);
} catch (Throwable $exception) {
    error_log('[OTP Reverification] Request failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process the verification request.']);
}

function reauthRedirect(string $role, mixed $requested): string
{
    $page = basename(parse_url(is_string($requested) ? $requested : '', PHP_URL_PATH) ?: '');
    $youthPages = ['youth-portal.html', 'events.html', 'borrow.html', 'print.html', 'health.html', 'report.html', 'profile.html', 'public-disclosure.html', 'freedom-wall.html'];
    $adminPages = ['admin-dashboard.html', 'admin-masterlist.html', 'youth-registry.html', 'dashboard-events.html', 'dashboard-resources.html', 'dashboard-printing.html', 'dashboard-incidents.html', 'admin-health-announcements.html', 'dashboard-public_disclosure.html', 'dashboard-settings.html', 'admin-service-verification.html', 'dashboard-freedom-wall.html'];
    return $role === 'youth'
        ? (in_array($page, $youthPages, true) ? $page : 'youth-portal.html')
        : (in_array($page, $adminPages, true) ? $page : 'admin-dashboard.html');
}
