<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/security_helpers.php';

function freedomWallResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function freedomWallInput(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $input = json_decode($raw, true);
    return is_array($input) ? $input : [];
}

function freedomWallLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function freedomWallRequireMethod(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        freedomWallResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function freedomWallLog(PDO $pdo, array $auth, string $action, int $postId): void
{
    require_once dirname(__DIR__, 2) . '/includes/request.php';
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs (user_id, role, action, identifier, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $auth['id'],
        $auth['role'],
        $action,
        (string)$postId,
        clientIp(),
    ]);
}

function freedomWallDatabaseError(PDOException $exception): void
{
    error_log('[Freedom Wall] Database request failed: ' . $exception->getMessage());
    freedomWallResponse(['success' => false, 'message' => 'Unable to process the Freedom Wall request.'], 500);
}
