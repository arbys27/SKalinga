<?php

require_once __DIR__ . '/../includes/turnstile.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $config = turnstileConfig();
    echo json_encode([
        'turnstileSiteKey' => $config['site_key'],
        'turnstileEnabled' => $config['enabled'],
    ]);
} catch (Throwable $exception) {
    error_log('[Public Config] Unable to load Turnstile configuration: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Public configuration is unavailable.']);
}
