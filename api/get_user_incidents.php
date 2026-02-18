<?php
// API: Get User's Own Incidents
session_start();
header('Content-Type: application/json');

require 'db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'];

try {
    $stmt = $pdo->prepare('
        SELECT id, member_id, category, description, location, urgency, status, 
               photo_path, submitted_date, admin_notes
        FROM incidents
        WHERE member_id = ?
        ORDER BY submitted_date DESC
    ');
    
    $stmt->execute([$member_id]);
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $incidents
    ]);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch incidents']);
}
?>
