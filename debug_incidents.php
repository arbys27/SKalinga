<?php
session_start();
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_id'] = 1;

header('Content-Type: application/json');

require_once 'api/db_connect.php';

echo json_encode([
    'PDO Status' => 'Checking...',
    'Connection Test' => 'Starting...'
]);

try {
    // Test 1: Basic connection
    $result = $pdo->query('SELECT 1 as test');
    $test = $result->fetch();
    
    // Test 2: Check if incidents table exists (PostgreSQL way)
    $checkTable = $pdo->prepare("
        SELECT EXISTS(
            SELECT 1 FROM information_schema.tables 
            WHERE table_name = 'incidents'
        ) as exists
    ");
    $checkTable->execute();
    $tableCheck = $checkTable->fetch(PDO::FETCH_ASSOC);
    
    // Test 3: Get count
    $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM incidents");
    $countResult = $countStmt->fetch();
    
    // Test 4: Get all incidents
    $stmt = $pdo->prepare("
        SELECT i.id, i.member_id, i.category, i.description, i.location, i.urgency, 
               i.status, i.photo_path, i.submitted_date, i.admin_notes
        FROM incidents i
        ORDER BY i.submitted_date DESC
    ");
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'connection' => 'OK',
        'table_exists' => $tableCheck['exists'],
        'incident_count' => $countResult['cnt'],
        'incidents' => $incidents
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
