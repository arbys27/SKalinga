<?php
// get_borrow_records.php - Fetch all borrow records with resource info
header('Content-Type: application/json');

require_once 'db.php';

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Admin login required.'
    ]);
    exit;
}

try {
    // Fetch borrow records with resource details using JOIN
    $stmt = $pdo->query("
        SELECT 
            br.borrow_id,
            br.item_id,
            br.member_id,
            br.borrower_name,
            br.quantity,
            br.borrow_date,
            br.due_date,
            br.return_date,
            br.status,
            r.name as item_name,
            r.description
        FROM borrow_records br
        JOIN resources r ON br.item_id = r.item_id
        ORDER BY br.borrow_date DESC
    ");

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $records,
        'total' => count($records)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
