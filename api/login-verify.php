<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'login_verify'
);
if (!$verification['success']) {
    authJsonResponse(['success' => false, 'message' => $verification['message']], 400);
}

$token = is_string($_COOKIE['login_challenge'] ?? null) ? $_COOKIE['login_challenge'] : '';
$code = is_string($_POST['otp'] ?? null) ? trim($_POST['otp']) : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token) || !preg_match('/^\d{6}$/', $code)) {
    authJsonResponse(['success' => false, 'message' => 'Enter the six-digit sign-in code.'], 400);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, user_type, user_id, code_hash, expires_at, attempts, max_attempts, redirect_to, remember_device
         FROM login_challenges
         WHERE token_hash = ? AND consumed_at IS NULL
         FOR UPDATE'
    );
    $stmt->execute([hash('sha256', $token)]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$challenge || strtotime((string)$challenge['expires_at']) <= time()
        || (int)$challenge['attempts'] >= (int)$challenge['max_attempts']) {
        if ($challenge) {
            $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$challenge['id']]);
        }
        $pdo->commit();
        error_log('[Login OTP] Verification failed; challenge expired or attempts exhausted.');
        clearLoginChallengeCookie();
        authJsonResponse(['success' => false, 'message' => 'The sign-in code is invalid or expired. Sign in again.'], 400);
    }

    if (!hash_equals((string)$challenge['code_hash'], otpCodeHash($code))) {
        $attempts = (int)$challenge['attempts'] + 1;
        $pdo->prepare(
            'UPDATE login_challenges
             SET attempts = ?, consumed_at = CASE WHEN ? >= max_attempts THEN CURRENT_TIMESTAMP ELSE consumed_at END
             WHERE id = ?'
        )->execute([$attempts, $attempts, $challenge['id']]);
        $pdo->commit();
        error_log('[Login OTP] Verification failed; attempt=' . $attempts . ' of ' . (int)$challenge['max_attempts'] . '.');
        if ($attempts >= (int)$challenge['max_attempts']) {
            clearLoginChallengeCookie();
        }
        authJsonResponse(['success' => false, 'message' => 'Invalid sign-in code.'], 400);
    }

    $role = (string)$challenge['user_type'];
    $userId = (int)$challenge['user_id'];
    if ($role === 'youth') {
        $accountStmt = $pdo->prepare(
            'SELECT u.id, u.email, u.member_id, u.status, p.firstname, p.lastname
             FROM users u LEFT JOIN youth_profiles p ON p.user_id = u.id WHERE u.id = ?'
        );
    } else {
        $accountStmt = $pdo->prepare(
            'SELECT id, username, email, role, status FROM admins WHERE id = ?'
        );
    }
    $accountStmt->execute([$userId]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || strtolower((string)$account['status']) !== 'active'
        || !filter_var((string)($account['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$challenge['id']]);
        $pdo->commit();
        clearLoginChallengeCookie();
        authJsonResponse(['success' => false, 'message' => 'This account cannot complete sign-in. Contact the SK Office.'], 403);
    }

    $identifier = $role === 'youth'
        ? (string)($account['email'] ?? $account['member_id'] ?? '')
        : (string)$account['username'];
    $name = $role === 'youth'
        ? trim((string)($account['firstname'] ?? '') . ' ' . (string)($account['lastname'] ?? ''))
        : (string)$account['username'];
    $redirect = (string)($challenge['redirect_to'] ?? '');

    $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
        ->execute([$challenge['id']]);
    if ($role === 'youth') {
        $pdo->prepare(
            'UPDATE users SET last_login = CURRENT_TIMESTAMP, last_otp_verified_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$userId]);
    } else {
        $pdo->prepare('UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$userId]);
    }
    $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $role, 'login_success', $identifier, securityClientIp()]);
    $trustedToken = null;
    if ($role === 'youth' && postgresBoolean($challenge['remember_device'] ?? false)) {
        $trustedToken = bin2hex(random_bytes(32));
        $pdo->prepare(
            'INSERT INTO trusted_devices (user_id, token_hash, user_agent_hash, expires_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP + INTERVAL \'14 days\')'
        )->execute([$userId, hash('sha256', $trustedToken), securityUserAgentHash()]);
    }
    $pdo->commit();

    establishLoginSession($account, $role, $identifier, $name, $role !== 'youth');
    if (is_string($trustedToken)) {
        setTrustedDeviceCookie($trustedToken, time() + 14 * 24 * 60 * 60);
    }
    clearLoginChallengeCookie();
    error_log('[Login OTP] Verification succeeded; role=' . $role . '; email=' . otpMaskEmail((string)$account['email']) . '.');
    authJsonResponse([
        'success' => true,
        'redirect' => $redirect !== '' ? $redirect : ($role === 'youth' ? 'youth-portal.html' : 'admin-dashboard.html'),
        'role' => $role
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Login OTP Verify Error', $exception, [$code]);
    authJsonResponse([
        'success' => false,
        'message' => 'Unable to verify the sign-in code right now. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ], 500);
}
