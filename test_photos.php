<?php
require 'api/db_connect.php';

$stmt = $pdo->query('SELECT id, member_id, category, photo_path FROM incidents ORDER BY id DESC LIMIT 3');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($results as $row) {
    echo 'ID: ' . $row['id'] . "\n";
    echo 'Photo Path: ' . $row['photo_path'] . "\n";
    
    // Try to decode JSON
    $decoded = json_decode($row['photo_path'], true);
    if (is_array($decoded)) {
        echo 'Array length: ' . count($decoded) . "\n";
        echo 'Contents: ' . json_encode($decoded) . "\n";
    } else {
        echo 'Not an array - raw string\n';
    }
    echo "---\n";
}
?>
