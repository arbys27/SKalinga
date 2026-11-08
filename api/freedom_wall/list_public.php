<?php

require_once dirname(__DIR__) . '/db_connect.php';
require_once __DIR__ . '/common.php';

freedomWallRequireMethod('GET');

try {
    $stmt = $pdo->query(
        "SELECT p.id, p.category, p.title, p.body, p.is_anonymous,
                p.approved_at,
                CASE WHEN p.is_anonymous THEN 'Anonymous Youth'
                     ELSE COALESCE(NULLIF(trim(yp.firstname || ' ' || left(yp.lastname, 1) || '.'), '.'), 'Youth member')
                END AS display_name
         FROM freedom_wall_posts p
         LEFT JOIN youth_profiles yp ON yp.user_id = p.user_id
         WHERE p.status = 'approved' AND p.is_hidden = false
         ORDER BY p.approved_at DESC NULLS LAST, p.created_at DESC
         LIMIT 100"
    );
    freedomWallResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $exception) {
    freedomWallDatabaseError($exception);
}
