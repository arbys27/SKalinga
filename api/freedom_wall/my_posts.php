<?php

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = requireAuth(['youth']);
freedomWallRequireMethod('GET');

try {
    $stmt = $pdo->prepare(
        'SELECT id, category, title, body, is_anonymous, status, rejection_reason,
                created_at
         FROM freedom_wall_posts
         WHERE user_id = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$auth['id']]);
    freedomWallResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $exception) {
    freedomWallDatabaseError($exception);
}
