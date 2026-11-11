<?php
require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/../includes/turnstile.php';
require_once __DIR__ . '/../includes/mail.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$verification = verifyTurnstile(
    is_array($input) && is_string($input['cf-turnstile-response'] ?? null) ? $input['cf-turnstile-response'] : '',
    'forgot_password'
);
if (!$verification['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $verification['message']]);
    exit;
}

require_once 'db_connect.php';

$emailValue = is_array($input) ? ($input['email'] ?? null) : null;
$email = is_string($emailValue) ? trim($emailValue) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']);
    exit;
}

$genericResponse = [
    'success' => true,
    'message' => 'If an active youth account uses this email, an OTP will be sent shortly.'
];
$appConfig = skalingaConfig();
try {
    $userStmt = $pdo->prepare(
        'SELECT id, email FROM users WHERE LOWER(email) = LOWER(?) AND status = ? LIMIT 1'
    );
    $userStmt->execute([$email, 'active']);
    $user = $userStmt->fetch();

    if (!$user) {
        echo json_encode($genericResponse);
        exit;
    }

    otpReserveSend($pdo, 'password-reset', (string)$user['id']);
    $otpCode = otpGenerateCode();
    $otpHash = otpCodeHash($otpCode);

    $pdo->beginTransaction();
    $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND is_used = FALSE');
    $deleteStmt->execute([$user['id']]);
    $insertStmt = $pdo->prepare(
        'INSERT INTO password_resets (user_id, otp_code, otp_attempts, is_used, expires_at, created_at)
         VALUES (?, ?, 0, FALSE, CURRENT_TIMESTAMP + INTERVAL \'5 minutes\', CURRENT_TIMESTAMP)
         RETURNING id'
    );
    $insertStmt->execute([$user['id'], $otpHash]);
    $resetId = (int)$insertStmt->fetchColumn();
    $pdo->commit();

    sendPasswordResetOtpEmail(
        (string)$user['email'],
        'SKalinga youth member',
        $otpCode,
        5,
        $pdo
    );

    echo json_encode($genericResponse);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (strpos($exception->getMessage(), 'rate limited') !== false) {
        echo json_encode($genericResponse);
        exit;
    }
    if (!empty($resetId)) {
        $pdo->prepare('DELETE FROM password_resets WHERE id = ? AND is_used = FALSE')->execute([$resetId]);
    }
    $reference = securityLogException('Password Reset Request Error', $exception, [$otpCode ?? '']);
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to send the password reset code right now. Reference: ' . $reference,
        'reference' => $reference
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!empty($resetId)) {
        $pdo->prepare('DELETE FROM password_resets WHERE id = ? AND is_used = FALSE')->execute([$resetId]);
    }
    $reference = securityLogException('Password Reset Request Error', $exception, [$otpCode ?? '']);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process the password reset request right now. Reference: ' . $reference,
        'reference' => $reference
    ]);
}
