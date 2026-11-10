<?php

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../security_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$auth = requireAdminApi();
if ($auth['role'] !== 'admin' || ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    authJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../../includes/mail.php';
try {
    authJsonResponse(['success' => true, 'usage' => mailUsageStatus($pdo)]);
} catch (Throwable $exception) {
    $reference = securityLogException('Admin Mail Usage Error', $exception);
    authJsonResponse([
        'success' => false,
        'message' => 'Unable to load email usage. Reference: ' . $reference,
        'reference' => $reference
    ], 500);
}
