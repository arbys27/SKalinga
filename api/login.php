<?php

require_once 'auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';

authStartSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

require_once __DIR__ . '/../includes/request.php';

$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'login'
);
if (!$verification['success']) {
    authJsonResponse(['success' => false, 'message' => $verification['message']], 400);
}

require_once 'db_connect.php';

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$ipAddress = clientIp();

if ($identifier === '' || $password === '') {
    authJsonResponse(['success' => false, 'message' => 'Identifier and password are required.'], 400);
}

$attemptType = 'login';
$stmt = $pdo->prepare(
    'SELECT COUNT(*), MAX(attempted_at) FROM login_attempts
     WHERE ip_address = ? AND attempt_type = ?
       AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'1 hour\')'
);
$stmt->execute([$ipAddress, $attemptType]);
$attempts = $stmt->fetch(PDO::FETCH_NUM);
$attemptCount = (int)($attempts[0] ?? 0);
$lastAttempt = isset($attempts[1]) ? strtotime((string)$attempts[1]) : false;
$lockoutSeconds = min(1800, 30 * (2 ** max(0, $attemptCount - 1)));
if ($attemptCount >= 5 && $lastAttempt !== false && time() - $lastAttempt < $lockoutSeconds) {
    $waitSeconds = $lockoutSeconds - (time() - $lastAttempt);
    authJsonResponse([
        'success' => false,
        'message' => 'Too many login attempts. Please wait ' . $waitSeconds . ' seconds before trying again.'
    ], 429);
}
$stmt = $pdo->prepare('INSERT INTO login_attempts (ip_address, attempt_type) VALUES (?, ?)');
$stmt->execute([$ipAddress, $attemptType]);

$normalizedIdentifier = function_exists('mb_strtolower')
    ? mb_strtolower(preg_replace('/\s+/u', ' ', $identifier), 'UTF-8')
    : strtolower(preg_replace('/\s+/', ' ', $identifier));
$phoneIdentifier = preg_replace('/\D+/', '', $identifier);

try {
    $adminStmt = $pdo->prepare(
        'SELECT id, username, email, phone, password_hash, role, status
         FROM admins
         WHERE LOWER(username) = ? OR LOWER(email) = ?
         LIMIT 1'
    );
    $adminStmt->execute([$normalizedIdentifier, $normalizedIdentifier]);
    $admin = $adminStmt->fetch() ?: null;

    $youthStmt = $pdo->prepare(
        'SELECT u.id, u.email, u.member_id, u.password_hash, u.status,
                p.phone, p.firstname, p.lastname
         FROM users u
         LEFT JOIN youth_profiles p ON p.user_id = u.id
         WHERE (u.email IS NOT NULL AND LOWER(u.email) = ?)
            OR p.phone = ?
         LIMIT 1'
    );
    $youthStmt->execute([$normalizedIdentifier, $phoneIdentifier]);
    $youth = $youthStmt->fetch() ?: null;

    if ($admin && $youth) {
        error_log('[Auth Login] Identifier collision detected: ' . $identifier);
        authJsonResponse(['success' => false, 'message' => 'This identifier is assigned to more than one account. Contact the SK Office.'], 409);
    }

    $account = $admin ?: $youth;
    if (!$account || ($account['status'] ?? '') !== 'active' || !password_verify($password, $account['password_hash'])) {
        authJsonResponse(['success' => false, 'message' => 'Invalid credentials.'], 401);
    }

    $isAdmin = is_array($admin);
    $role = $isAdmin ? (($account['role'] ?? '') === 'superadmin' ? 'admin' : 'sk_official') : 'youth';
    $name = $isAdmin
        ? $account['username']
        : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
    $accountIdentifier = $isAdmin
        ? (string)$account['username']
        : (string)(($account['email'] ?? null) ?: (($account['phone'] ?? null) ?: $account['member_id']));
    $phone = trim((string)($account['phone'] ?? ''));
    if ($phone === '') {
        authJsonResponse([
            'success' => false,
            'message' => $isAdmin
                ? 'A mobile number must be registered for this admin account before signing in. Contact the system administrator.'
                : 'No mobile number is registered for this account. Contact the SK Office.'
        ], 403);
    }

    $requestedRedirect = basename(parse_url($_POST['redirect'] ?? '', PHP_URL_PATH) ?: '');
    $youthPages = ['youth-portal.html', 'events.html', 'borrow.html', 'print.html', 'health.html', 'report.html', 'profile.html', 'public-disclosure.html'];
    $adminPages = ['admin-dashboard.html', 'admin-masterlist.html', 'youth-registry.html', 'dashboard-events.html', 'dashboard-resources.html', 'dashboard-printing.html', 'dashboard-incidents.html', 'admin-health-announcements.html', 'dashboard-public_disclosure.html', 'dashboard-settings.html', 'admin-service-verification.html'];
    $redirect = $isAdmin
        ? (in_array($requestedRedirect, $adminPages, true) ? $requestedRedirect : 'admin-dashboard.html')
        : (in_array($requestedRedirect, $youthPages, true) ? $requestedRedirect : 'youth-portal.html');

    $code = otpGenerateCode();
    otpReserveSend($pdo, 'login', $isAdmin ? 'admin:' . $account['id'] : 'youth:' . $account['id']);
    otpSendSms($phone, $code, 'login');

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['login_pending_type'] = 'login';
    $_SESSION['login_otp_hash'] = otpCodeHash($code);
    $_SESSION['login_otp_expires_at'] = time() + 300;
    $_SESSION['login_otp_attempts'] = 0;
    $_SESSION['login_user_id'] = (int)$account['id'];
    $_SESSION['login_role'] = $role;
    $_SESSION['login_identifier'] = $accountIdentifier;
    $_SESSION['login_name'] = $name;
    $_SESSION['login_phone'] = $phone;
    $_SESSION['login_member_id'] = $account['member_id'] ?? null;
    $_SESSION['login_admin_username'] = $isAdmin ? $account['username'] : null;
    $_SESSION['login_admin_email'] = $isAdmin ? $account['email'] : null;
    $_SESSION['login_admin_role'] = $isAdmin ? $account['role'] : null;
    $_SESSION['login_redirect'] = $redirect;

    authJsonResponse([
        'success' => true,
        'otp_required' => true,
        'message' => 'A verification code was sent to ' . otpMaskPhone($phone) . '.',
        'phone' => otpMaskPhone($phone),
        'redirect' => 'verify-otp.html'
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Auth Login Error] ' . $exception->getMessage());
    if ($exception instanceof RuntimeException && strpos($exception->getMessage(), 'rate limited') !== false) {
        authJsonResponse([
            'success' => false,
            'message' => otpRateLimitMessage($exception->getMessage())
        ], 429);
    }
    authJsonResponse(['success' => false, 'message' => 'Unable to sign in right now. Please try again later.'], 500);
}
