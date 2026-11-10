<?php

require_once __DIR__ . '/security_helpers.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$token = is_string($_COOKIE['login_challenge'] ?? null) ? $_COOKIE['login_challenge'] : '';
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $pdo->prepare(
            'UPDATE login_challenges SET consumed_at = CURRENT_TIMESTAMP
             WHERE token_hash = ? AND consumed_at IS NULL'
        )->execute([hash('sha256', $token)]);
    } catch (Throwable $exception) {
        error_log('[Login Challenge Cancel Error] ' . get_class($exception) . ': ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to cancel sign-in verification.']);
        exit;
    }
}

clearLoginChallengeCookie();
echo json_encode(['success' => true]);
