<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

securityStartSession();
securityRequireCsrf();
$verification = verifyTurnstile(
    is_string($_POST['cf-turnstile-response'] ?? null) ? $_POST['cf-turnstile-response'] : '',
    'verify_registration_otp'
);
if (!$verification['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $verification['message']]);
    exit;
}

$code = is_string($_POST['otp'] ?? null) ? trim($_POST['otp']) : '';
$challengeId = is_string($_SESSION['registration_sms_challenge_id'] ?? null)
    ? $_SESSION['registration_sms_challenge_id']
    : '';
$masterlistId = (int)($_SESSION['activation_masterlist_id'] ?? 0);
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter the six-digit verification code.']);
    exit;
}
if (!preg_match('/^[a-f0-9-]{36}$/i', $challengeId) || $masterlistId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Request a new verification code for your matched youth record.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT c.id, c.masterlist_id, c.phone, c.code_hash, c.expires_at, c.attempts, c.max_attempts,
                c.consumed_at, k.is_claimed
         FROM registration_sms_challenges c
         INNER JOIN kk_masterlist k ON k.id = c.masterlist_id
         WHERE c.id = ?::uuid AND c.masterlist_id = ?
         FOR UPDATE OF c, k'
    );
    $stmt->execute([$challengeId, $masterlistId]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$challenge || postgresBoolean($challenge['is_claimed'])
        || $challenge['consumed_at'] !== null
        || strtotime((string)$challenge['expires_at']) <= time()
        || (int)$challenge['attempts'] >= (int)$challenge['max_attempts']) {
        $pdo->commit();
        error_log('[Registration SMS] Verification rejected; phone='
            . ($challenge ? otpMaskPhone((string)$challenge['phone']) : '[unknown]')
            . '; reason=expired, consumed, or attempts exhausted.');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Code is invalid or expired. Request a new one.']);
        exit;
    }

    if (!hash_equals((string)$challenge['code_hash'], otpCodeHash($code))) {
        $attempts = (int)$challenge['attempts'] + 1;
        $pdo->prepare(
            'UPDATE registration_sms_challenges
             SET attempts = ?, consumed_at = CASE WHEN ? >= max_attempts THEN CURRENT_TIMESTAMP ELSE consumed_at END
             WHERE id = ?::uuid'
        )->execute([$attempts, $attempts, $challengeId]);
        $pdo->commit();
        error_log('[Registration SMS] Verification failed; phone=' . otpMaskPhone((string)$challenge['phone'])
            . '; attempt=' . $attempts . ' of ' . (int)$challenge['max_attempts'] . '.');
        if ($attempts >= (int)$challenge['max_attempts']) {
            unset($_SESSION['registration_sms_challenge_id']);
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        exit;
    }

    $pdo->prepare(
        'UPDATE registration_sms_challenges
         SET verified_at = CURRENT_TIMESTAMP, consumed_at = CURRENT_TIMESTAMP
         WHERE id = ?::uuid'
    )->execute([$challengeId]);
    $pdo->commit();
    $_SESSION['verified_activation_challenge_id'] = $challengeId;
    $_SESSION['phone_verified'] = true;
    $_SESSION['verified_phone'] = (string)$challenge['phone'];
    $_SESSION['verified_phone_at'] = time();
    unset($_SESSION['registration_sms_challenge_id']);
    error_log('[Registration SMS] Verification succeeded; phone=' . otpMaskPhone((string)$challenge['phone'])
        . '; masterlist=' . $masterlistId . '.');

    echo json_encode([
        'success' => true,
        'message' => 'Phone number verified successfully.',
        'phone' => otpMaskPhone((string)$challenge['phone']),
        'normalizedPhone' => (string)$challenge['phone']
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $reference = securityLogException('Registration SMS Verify Error', $exception, [$code]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to verify the phone code right now. Please try again. Reference: ' . $reference,
        'reference' => $reference
    ]);
}
