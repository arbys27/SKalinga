<?php
// delete_event.php - Delete event from database
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get event ID
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Invalid JSON input");
    }
    
    $eventId = $input['id'] ?? '';
    
    if (!$eventId) {
        throw new Exception("Event ID is required");
    }
    
    // Check if event exists and get image path for cleanup
    $check = $conn->query("SELECT image_path FROM events WHERE event_id = '$eventId'");
    if (!$check || $check->num_rows === 0) {
        throw new Exception("Event not found");
    }
    
    $row = $check->fetch_assoc();
    $imagePath = $row['image_path'];
    
    // Delete event from database
    $query = "DELETE FROM events WHERE event_id = '$eventId'";
    
    if (!$conn->query($query)) {
        throw new Exception("Failed to delete event: " . $conn->error);
    }
    
    // Delete image file if exists
    if ($imagePath) {
        $filePath = '../' . $imagePath;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Event deleted successfully',
        'id' => $eventId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
