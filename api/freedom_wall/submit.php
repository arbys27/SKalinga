<?php

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

$auth = requireAuth(['youth']);
freedomWallRequireMethod('POST');
securityRequireCsrf();

$input = freedomWallInput();
$category = isset($input['category']) && is_string($input['category']) ? trim($input['category']) : '';
$title = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';
$body = isset($input['body']) && is_string($input['body']) ? trim($input['body']) : '';
$anonymousValue = $input['is_anonymous'] ?? false;
$anonymous = $anonymousValue === true || $anonymousValue === 1 || $anonymousValue === '1' || $anonymousValue === 'true';
$categories = ['Feedback', 'Suggestion', 'Concern', 'Other'];

if (!in_array($category, $categories, true)) {
    freedomWallResponse(['success' => false, 'message' => 'Choose a valid category.'], 422);
}
if ($title === '' || freedomWallLength($title) > 120) {
    freedomWallResponse(['success' => false, 'message' => 'Title is required and must be 120 characters or fewer.'], 422);
}
if ($body === '' || freedomWallLength($body) > 2000) {
    freedomWallResponse(['success' => false, 'message' => 'Message is required and must be 2,000 characters or fewer.'], 422);
}

try {
    $rateLimit = $pdo->prepare(
        "SELECT COUNT(*) FROM freedom_wall_posts
         WHERE user_id = ? AND created_at >= CURRENT_TIMESTAMP - INTERVAL '1 hour'"
    );
    $rateLimit->execute([$auth['id']]);
    if ((int)$rateLimit->fetchColumn() >= 5) {
        freedomWallResponse(['success' => false, 'message' => 'You have reached the posting limit. Please try again later.'], 429);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO freedom_wall_posts (user_id, category, title, body, is_anonymous)
         VALUES (?, ?, ?, ?, ?)
         RETURNING id'
    );
    $stmt->execute([$auth['id'], $category, $title, $body, $anonymous]);
    freedomWallResponse([
        'success' => true,
        'message' => 'Your post was submitted for moderation.',
        'id' => (int)$stmt->fetchColumn(),
    ], 201);
} catch (PDOException $exception) {
    freedomWallDatabaseError($exception);
}
