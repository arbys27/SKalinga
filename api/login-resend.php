<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$token = is_string($_COOKIE['login_challenge'] ?? null) ? $_COOKIE['login_challenge'] : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    authJsonResponse(['success' => false, 'message' => 'Sign-in verification expired. Please sign in again.'], 401);
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, user_type, user_id, expires_at, send_count, last_sent_at
         FROM login_challenges
         WHERE token_hash = ? AND consumed_at IS NULL
         FOR UPDATE'
    );
    $stmt->execute([hash('sha256', $token)]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$challenge || strtotime((string)$challenge['expires_at']) <= time()) {
        if ($challenge) {
            $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$challenge['id']]);
        }
        $pdo->commit();
        clearLoginChallengeCookie();
        authJsonResponse(['success' => false, 'message' => 'Sign-in verification expired. Please sign in again.'], 400);
    }
    if ((int)$challenge['send_count'] >= 3) {
        $pdo->commit();
        authJsonResponse(['success' => false, 'message' => 'No more codes can be sent for this sign-in. Start again.'], 429);
    }
    $secondsSinceSend = time() - strtotime((string)$challenge['last_sent_at']);
    if ($secondsSinceSend < 60) {
        $pdo->commit();
        authJsonResponse([
            'success' => false,
            'message' => 'Please wait ' . (60 - max(0, $secondsSinceSend)) . ' seconds before requesting another code.'
        ], 429);
    }

    $role = (string)$challenge['user_type'];
    if ($role === 'youth') {
        $accountStmt = $pdo->prepare(
            'SELECT u.email, u.status, p.firstname, p.lastname
             FROM users u LEFT JOIN youth_profiles p ON p.user_id = u.id WHERE u.id = ?'
        );
    } else {
        $accountStmt = $pdo->prepare('SELECT email, username, status FROM admins WHERE id = ?');
    }
    $accountStmt->execute([(int)$challenge['user_id']]);
    $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || strtolower((string)$account['status']) !== 'active'
        || !filter_var((string)($account['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$challenge['id']]);
        $pdo->commit();
        clearLoginChallengeCookie();
        authJsonResponse(['success' => false, 'message' => 'This account cannot complete sign-in. Contact the SK Office.'], 403);
    }

    $name = $role === 'youth'
        ? trim((string)($account['firstname'] ?? '') . ' ' . (string)($account['lastname'] ?? ''))
        : (string)($account['username'] ?? '');
    $code = otpGenerateCode();
    $pdo->prepare(
        'UPDATE login_challenges
         SET code_hash = ?, send_count = send_count + 1, last_sent_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    )->execute([otpCodeHash($code), $challenge['id']]);
    $pdo->commit();

    try {
        sendLoginOtpEmail((string)$account['email'], $name, $code, 10, $pdo);
    } catch (Throwable $exception) {
        $pdo->prepare('UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$challenge['id']]);
        clearLoginChallengeCookie();
        throw $exception;
    }
    error_log('[Login OTP Resend] Delivery succeeded; role=' . $role . '; email=' . otpMaskEmail((string)$account['email']) . '.');
    authJsonResponse([
        'success' => true,
        'message' => 'A new sign-in code was sent.',
        'maskedEmail' => otpMaskEmail((string)$account['email']),
        'resendAfter' => 60
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Login OTP Resend Error', $exception, [$code ?? '']);
    if ($exception instanceof MailQuotaException) {
        authJsonResponse([
            'success' => false,
            'message' => 'Email codes are unavailable until next month. Please contact the SK Office.',
            'reference' => $reference
        ], 503);
    }
    authJsonResponse([
        'success' => false,
        'message' => 'We couldn\'t send your code. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ], 503);
}
