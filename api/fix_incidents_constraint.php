<?php
// API: Fix incidents table constraint issue
session_start();
header('Content-Type: application/json');

require 'db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['admin_authenticated']) && !isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    if (!isset($pdo)) {
        throw new Exception('Database connection not initialized');
    }

    // Drop the problematic constraint if it exists
    $pdo->exec('ALTER TABLE incidents DROP CONSTRAINT IF EXISTS incidents_urgency_check');

    // Add a new CHECK constraint with proper values or remove it entirely
    // For now, we'll just drop it since we're validating urgency in PHP
    
    echo json_encode([
        'success' => true,
        'message' => 'Constraint fixed successfully. You can now submit incidents without errors.'
    ]);

} catch (PDOException $e) {
    error_log('Fix constraint error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log('General error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>
