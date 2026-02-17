<?php
// borrow_resource.php - Create a new borrow record
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

    // Validate required fields
    $requiredFields = ['item_id', 'member_id', 'borrower_name', 'due_date'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required.'
            ]);
            exit;
        }
    }

    $itemId = trim($data['item_id']);
    $memberId = trim($data['member_id']);
    $borrowerName = trim($data['borrower_name']);
    $dueDate = $data['due_date'];
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;

    // Validate quantity
    if ($quantity < 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Quantity must be at least 1.'
        ]);
        exit;
    }

    // Validate date format
    if (!strtotime($dueDate)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid due date format.'
        ]);
        exit;
    }

    // Check if resource exists and has available items
    $stmt = $pdo->prepare("SELECT quantity, available FROM resources WHERE item_id = ?");
    $stmt->execute([$itemId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Resource not found.'
        ]);
        exit;
    }

    if ($resource['available'] < $quantity) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Only ' . $resource['available'] . ' unit(s) available. Cannot borrow ' . $quantity . ' unit(s).'
        ]);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Insert borrow record
        $stmt = $pdo->prepare("
            INSERT INTO borrow_records (item_id, member_id, borrower_name, quantity, due_date, status)
            VALUES (?, ?, ?, ?, ?, 'Borrowed')
        ");

        $stmt->execute([
            $itemId,
            $memberId,
            $borrowerName,
            $quantity,
            $dueDate
        ]);

        $borrowId = $pdo->lastInsertId();

        // Update available count
        $stmt = $pdo->prepare("
            UPDATE resources
            SET available = available - ?
            WHERE item_id = ?
        ");

        $stmt->execute([$quantity, $itemId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Item borrowed successfully',
            'borrow_id' => $borrowId,
            'item_id' => $itemId,
            'member_id' => $memberId,
            'borrower_name' => $borrowerName,
            'quantity' => $quantity,
            'due_date' => $dueDate,
            'status' => 'Borrowed'
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
