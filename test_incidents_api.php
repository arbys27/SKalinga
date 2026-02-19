<?php
// Simulate admin session
$_SESSION = [];
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'admin';

session_start();

// Directly test the get_incidents.php logic
header('Content-Type: application/json');

require_once 'api/db_connect.php';

try {
    echo json_encode([
        'test' => 'direct_incidents_query',
        'admin_session' => $_SESSION['admin_authenticated'] ?? false
    ]) . "\n";
    
    // Test the actual query
    $query = "
        SELECT i.id, i.member_id, i.category, i.description, i.location, i.urgency, 
               i.status, i.photo_path, i.submitted_date, i.admin_notes,
               COALESCE(p.firstname, 'Unknown') as firstname,
               COALESCE(p.lastname, 'User') as lastname,
               COALESCE(p.phone, 'N/A') as phone,
               COALESCE(p.address, 'N/A') as address,
               COALESCE(p.avatar_path, '') as avatar_path,
               u.id as user_id
        FROM incidents i
        LEFT JOIN users u ON UPPER(i.member_id) = UPPER(u.member_id)
        LEFT JOIN youth_profiles p ON u.id = p.user_id
        ORDER BY i.submitted_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => count($incidents),
        'data' => $incidents
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
