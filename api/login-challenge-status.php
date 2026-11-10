<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
$token = is_string($_COOKIE['login_challenge'] ?? null) ? $_COOKIE['login_challenge'] : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    echo json_encode(['success' => true, 'pending' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT c.expires_at, c.last_sent_at, c.consumed_at, a.email
         FROM login_challenges c
         LEFT JOIN admins a ON c.user_type IN (\'admin\', \'sk_official\') AND a.id = c.user_id
         LEFT JOIN users u ON c.user_type = \'youth\' AND u.id = c.user_id
         WHERE c.token_hash = ?'
    );
    $stmt->execute([hash('sha256', $token)]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$challenge || $challenge['consumed_at'] !== null
        || strtotime((string)$challenge['expires_at']) <= time()) {
        clearLoginChallengeCookie();
        echo json_encode(['success' => true, 'pending' => false]);
        exit;
    }

    $email = (string)($challenge['email'] ?? '');
    if ($email === '') {
        $stmt = $pdo->prepare(
            'SELECT u.email FROM login_challenges c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.token_hash = ? AND c.user_type = \'youth\''
        );
        $stmt->execute([hash('sha256', $token)]);
        $email = (string)$stmt->fetchColumn();
    }
    $wait = max(0, 60 - (time() - strtotime((string)$challenge['last_sent_at'])));
    echo json_encode([
        'success' => true,
        'pending' => true,
        'maskedEmail' => otpMaskEmail($email),
        'resendAfter' => $wait
    ]);
} catch (Throwable $exception) {
    error_log('[Login Challenge Status Error] ' . get_class($exception) . ': ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'pending' => false, 'message' => 'Unable to load sign-in verification.']);
}
