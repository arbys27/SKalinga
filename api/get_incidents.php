<?php
// API: Get All Incidents (Admin)
header('Content-Type: application/json');

require 'db_connect.php';
require 'check_admin_session.php';

// Check if PDO connection exists
if (!isset($pdo)) {
    error_log('PDO connection not available in get_incidents.php');
    echo json_encode(['success' => false, 'error' => 'Database connection not available']);
    exit;
}

try {
    // First check if incidents table exists
    $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'incidents'");
    $tableExists = $checkTableStmt->rowCount() > 0;
    
    if (!$tableExists) {
        error_log('Incidents table does not exist');
        // Return empty array instead of error - table will be created by setup script
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }
    
    // Join through users table (via member_id) to youth_profiles
    $stmt = $pdo->prepare('
        SELECT i.id, i.member_id, i.category, i.description, i.location, i.urgency, 
               i.status, i.photo_path, i.submitted_date, i.admin_notes,
               COALESCE(p.firstname, "Unknown") as firstname,
               COALESCE(p.lastname, "User") as lastname,
               COALESCE(p.phone, "N/A") as phone,
               COALESCE(p.address, "N/A") as address,
               COALESCE(p.avatar_path, "") as avatar_path,
               u.id as user_id
        FROM incidents i
        LEFT JOIN users u ON UPPER(i.member_id) = UPPER(u.member_id)
        LEFT JOIN youth_profiles p ON u.id = p.user_id
        ORDER BY i.submitted_date DESC
    ');
    
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $incidents
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get_incidents.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
