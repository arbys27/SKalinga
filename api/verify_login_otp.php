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
    'verify_login_otp'
);
if (!$verification['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $verification['message']]);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$code = isset($_POST['otp']) && is_string($_POST['otp']) ? trim($_POST['otp']) : '';
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter the six-digit login code.']);
    exit;
}

$storedHash = $_SESSION['login_otp_hash'] ?? null;
$userId = (int)($_SESSION['login_user_id'] ?? 0);
$expiresAt = (int)($_SESSION['login_otp_expires_at'] ?? 0);
$attempts = (int)($_SESSION['login_otp_attempts'] ?? 0);
if (!is_string($storedHash) || $userId < 1 || time() >= $expiresAt || $attempts >= 5
    || !in_array($_SESSION['login_pending_type'] ?? '', ['login', 'reauth'], true)) {
    unset(
        $_SESSION['login_pending_type'],
        $_SESSION['login_otp_hash'],
        $_SESSION['login_user_id'],
        $_SESSION['login_otp_expires_at'],
        $_SESSION['login_otp_attempts']
    );
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Login code is invalid or expired. Request a new one.']);
    exit;
}

if (!hash_equals($storedHash, otpCodeHash($code))) {
    $_SESSION['login_otp_attempts'] = $attempts + 1;
    if ($_SESSION['login_otp_attempts'] >= 5) {
        unset(
            $_SESSION['login_pending_type'],
            $_SESSION['login_otp_hash'],
            $_SESSION['login_user_id'],
            $_SESSION['login_otp_expires_at'],
            $_SESSION['login_otp_attempts']
        );
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired login code.']);
    exit;
}

try {
    $pendingType = (string)$_SESSION['login_pending_type'];
    $userId = (int)$_SESSION['login_user_id'];
    $role = $pendingType === 'login'
        ? (string)($_SESSION['login_role'] ?? '')
        : (string)($_SESSION['otp_reauth_role'] ?? '');
    $phone = (string)($_SESSION['login_phone'] ?? '');
    if ($pendingType === 'reauth') {
        $role = (string)($_SESSION['otp_reauth_role'] ?? '');
    }
    if ($pendingType === 'reauth' && (int)($_SESSION['otp_reauth_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('OTP reauthentication session is invalid.');
    }
    if (!in_array($role, ['youth', 'admin', 'sk_official'], true) || $phone === '') {
        throw new RuntimeException('OTP login session is incomplete.');
    }

    if ($role === 'youth') {
        $accountStmt = $pdo->prepare(
            'SELECT u.member_id, u.status, p.phone
             FROM users u
             INNER JOIN youth_profiles p ON p.user_id = u.id
             WHERE u.id = ?'
        );
    } else {
        $accountStmt = $pdo->prepare('SELECT status, phone FROM admins WHERE id = ?');
    }
    $accountStmt->execute([$userId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || $account['status'] !== 'active'
        || !hash_equals((string)$account['phone'], $phone)) {
        throw new RuntimeException('OTP account is no longer active or its registered number changed.');
    }

    $memberId = $pendingType === 'login'
        ? (string)($_SESSION['login_member_id'] ?? '')
        : (string)($_SESSION['otp_reauth_member_id'] ?? '');
    $identifier = $pendingType === 'login'
        ? (string)($_SESSION['login_identifier'] ?? '')
        : (string)($_SESSION['otp_reauth_identifier'] ?? '');
    $name = $pendingType === 'login'
        ? (string)($_SESSION['login_name'] ?? '')
        : (string)($_SESSION['otp_reauth_name'] ?? '');
    $redirect = $pendingType === 'login'
        ? (string)($_SESSION['login_redirect'] ?? '')
        : (string)($_SESSION['otp_reauth_redirect'] ?? '');
    $adminEmail = $pendingType === 'login'
        ? ($_SESSION['login_admin_email'] ?? null)
        : ($_SESSION['otp_reauth_email'] ?? null);

    if ($role === 'youth') {
        $update = $pdo->prepare(
            'UPDATE users
             SET last_login = CASE WHEN ? = \'login\' THEN CURRENT_TIMESTAMP ELSE last_login END,
                 last_otp_verified_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $update->execute([$pendingType, $userId]);
    } elseif ($pendingType === 'login') {
        $update = $pdo->prepare('UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([$userId]);
    }

    if ($pendingType === 'login') {
        $activity = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $activity->execute([$userId, $role, 'login_success', $identifier, securityClientIp()]);
    }

    session_regenerate_id(true);
    unset(
        $_SESSION['login_pending_type'],
        $_SESSION['login_otp_hash'],
        $_SESSION['login_phone'],
        $_SESSION['login_user_id'],
        $_SESSION['login_role'],
        $_SESSION['login_identifier'],
        $_SESSION['login_name'],
        $_SESSION['login_member_id'],
        $_SESSION['login_admin_username'],
        $_SESSION['login_admin_email'],
        $_SESSION['login_admin_role'],
        $_SESSION['login_redirect'],
        $_SESSION['login_otp_expires_at'],
        $_SESSION['login_otp_attempts'],
        $_SESSION['otp_reauth_required'],
        $_SESSION['otp_reauth_user_id'],
        $_SESSION['otp_reauth_role'],
        $_SESSION['otp_reauth_identifier'],
        $_SESSION['otp_reauth_name'],
        $_SESSION['otp_reauth_member_id'],
        $_SESSION['otp_reauth_email'],
        $_SESSION['otp_reauth_redirect']
    );
    $_SESSION['auth_user_id'] = $userId;
    $_SESSION['auth_role'] = $role;
    $_SESSION['auth_identifier'] = $identifier;
    $_SESSION['auth_name'] = $name;
    $_SESSION['member_id'] = $memberId !== '' ? $memberId : null;
    $_SESSION['user_id'] = $role === 'youth' ? $userId : null;
    $_SESSION['member_id'] = $role === 'youth' ? $memberId : null;
    $_SESSION['phone'] = $phone;
    $_SESSION['authenticated'] = $role === 'youth';
    $_SESSION['admin_authenticated'] = $role !== 'youth';
    $_SESSION['admin_id'] = $role !== 'youth' ? $userId : null;
    $_SESSION['admin_username'] = $role !== 'youth' ? $identifier : null;
    $_SESSION['admin_email'] = $role !== 'youth' ? $adminEmail : null;
    $_SESSION['admin_role'] = $role === 'admin' ? 'superadmin' : ($role === 'sk_official' ? 'staff' : null);
    $_SESSION['otp_verified_at'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'member_id' => $memberId,
        'role' => $role,
        'redirect' => $redirect !== '' ? $redirect : ($role === 'youth' ? 'youth-portal.html' : 'admin-dashboard.html')
    ]);
} catch (Throwable $exception) {
    error_log('[Login OTP] Completion failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to complete login right now.']);
}
