<?php
// return_resource.php - Mark a resource as returned and update available count
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
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }

    // Validate required field
    if (empty($data['borrow_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Borrow ID is required.'
        ]);
        exit;
    }

    $borrowId = intval($data['borrow_id']);

    // Check if borrow record exists
    $stmt = $pdo->prepare("SELECT item_id, status, quantity FROM borrow_records WHERE borrow_id = ?");
    $stmt->execute([$borrowId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Borrow record not found.'
        ]);
        exit;
    }

    if ($record['status'] === 'Returned') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Item already marked as returned.'
        ]);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update borrow record status to Returned
        $stmt = $pdo->prepare("
            UPDATE borrow_records
            SET status = 'Returned', return_date = NOW()
            WHERE borrow_id = ?
        ");

        $stmt->execute([$borrowId]);

        // Update available count in resources (restore by quantity borrowed)
        $returnQuantity = intval($record['quantity'] ?? 1);
        $stmt = $pdo->prepare("
            UPDATE resources
            SET available = available + ?
            WHERE item_id = ?
        ");

        $stmt->execute([$returnQuantity, $record['item_id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Item marked as returned successfully',
            'borrow_id' => $borrowId,
            'status' => 'Returned'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

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
