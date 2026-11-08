<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
verificationRequireMethod('POST');
$admin = requireAdminApi();
securityRequireCsrf();
$input = verificationInput();
$token = verificationTokenInput($input);
if ($token === null) {
    verificationAudit($pdo, 'send_otp', 'invalid_token_format', null, null, null, (int)$admin['id']);
    verificationResponse(['success' => false, 'message' => 'A valid verification token is required.'], 400);
}
$tokenHash = hash('sha256', $token);

try {
    $pdo->beginTransaction();
    $record = verificationLoadToken($pdo, $tokenHash, true);
    if (!$record || $record['status'] !== 'active' || verificationIsExpired((string)$record['expires_at'])
        || !postgresBoolean($record['otp_required'])
        || (int)($record['scanned_by_admin_id'] ?? 0) !== (int)$admin['id']) {
        verificationAudit(
            $pdo,
            'send_otp',
            'not_eligible',
            $record ? (int)$record['id'] : null,
            $tokenHash,
            $record ? (int)$record['user_id'] : null,
            (int)$admin['id'],
            $record['service_type'] ?? null,
            $record['service_ref'] ?? null
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'OTP is unavailable for this verification.'], 409);
    }

    $profileQuery = $pdo->prepare(
        'SELECT yp.phone
         FROM users u
         JOIN youth_profiles yp ON yp.user_id = u.id
         WHERE u.id = ? AND u.status = \'active\'
         LIMIT 1'
    );
    $profileQuery->execute([(int)$record['user_id']]);
    $phone = $profileQuery->fetchColumn();
    if (!is_string($phone) || trim($phone) === '') {
        verificationAudit(
            $pdo, 'send_otp', 'missing_phone', (int)$record['id'], $tokenHash, (int)$record['user_id'],
            (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
        );
        $pdo->commit();
        verificationResponse(['success' => false, 'message' => 'No registered mobile number is available for OTP.'], 400);
    }

    $code = otpGenerateCode();
    otpReserveSend($pdo, 'service-verification', (string)$record['user_id']);
    $update = $pdo->prepare(
        'UPDATE service_verification_tokens
         SET otp_hash = ?, otp_expires_at = CURRENT_TIMESTAMP + INTERVAL \'5 minutes\',
             otp_attempts = 0, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $update->execute([otpCodeHash($code), (int)$record['id']]);
    verificationAudit(
        $pdo, 'send_otp', 'queued', (int)$record['id'], $tokenHash, (int)$record['user_id'],
        (int)$admin['id'], (string)$record['service_type'], (string)$record['service_ref']
    );
    $pdo->commit();

    try {
        otpSendSms($phone, $code, 'service-verification');
    } catch (Throwable $exception) {
        $invalidate = $pdo->prepare(
            'UPDATE service_verification_tokens
             SET otp_hash = NULL, otp_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND token_hash = ? AND status = \'active\''
        );
        $invalidate->execute([(int)$record['id'], $tokenHash]);
        $pdo->prepare(
            "INSERT INTO service_verification_audit
                (token_id, token_hash, user_id, admin_id, action, result, service_type, service_ref, ip_address)
             VALUES (?, ?, ?, ?, 'send_otp', 'delivery_failed', ?, ?, ?)"
        )->execute([
            (int)$record['id'], $tokenHash, (int)$record['user_id'], (int)$admin['id'],
            (string)$record['service_type'], (string)$record['service_ref'], securityClientIp()
        ]);
        throw $exception;
    }

    verificationResponse([
        'success' => true,
        'message' => 'OTP sent to the registered mobile number.',
        'phone_hint' => otpMaskPhone($phone),
    ]);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (str_starts_with($exception->getMessage(), 'rate limited:')) {
        verificationResponse(['success' => false, 'message' => otpRateLimitMessage($exception->getMessage())], 429);
    }
    error_log('[Service Verification] OTP send failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Service Verification] OTP delivery failed: ' . $exception->getMessage());
    verificationResponse(['success' => false, 'message' => 'Unable to send the OTP. Please try again later.'], 500);
}
