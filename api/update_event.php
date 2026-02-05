<?php
// update_event.php - Update event with image upload support
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get event data
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['startTime'] ?? '';
    $endTime = $_POST['endTime'] ?? '';
    $location = $_POST['location'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $regLink = $_POST['regLink'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Event ID is required']);
        exit;
    }

    // Check if event exists and get current image
    $result = $conn->query("SELECT image_path FROM events WHERE event_id = '$id'");
    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Event not found']);
        exit;
    }
    
    $currentEvent = $result->fetch_assoc();
    $imagePath = $currentEvent['image_path'];

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/events/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Delete old image if exists
        if ($imagePath && file_exists($uploadDir . basename($imagePath))) {
            unlink($uploadDir . basename($imagePath));
        }

        $fileName = 'event_' . time() . '_' . basename($_FILES['image']['name']);
        $uploadPath = $uploadDir . $fileName;
        $relativePath = 'assets/images/events/' . $fileName;

        // Validate image type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
        finfo_close($finfo);
        
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image type']);
            exit;
        }

        // Validate file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Image too large (max 5MB)']);
            exit;
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $imagePath = $relativePath;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload image']);
            exit;
        }
    }

    // Build update query
    $updates = [];
    
    if ($title) $updates[] = "title = '$title'";
    if ($date) $updates[] = "date = '$date'";
    if ($startTime) $updates[] = "start_time = '$startTime'";
    if ($endTime) $updates[] = "end_time = '$endTime'";
    if ($location) $updates[] = "location = '$location'";
    if ($type) $updates[] = "event_type = '$type'";
    if ($capacity) $updates[] = "capacity = $capacity";
    if (isset($_POST['description'])) $updates[] = "description = '$description'";
    if (isset($_POST['regLink'])) $updates[] = "registration_link = '$regLink'";
    if ($status) $updates[] = "status = '$status'";
    
    $updates[] = "image_path = " . ($imagePath ? "'$imagePath'" : "NULL");
    $updates[] = "updated_at = NOW()";

    if (empty($updates) || count($updates) <= 2) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }

    // Execute update
    $sql = "UPDATE events SET " . implode(", ", $updates) . " WHERE event_id = '$id'";
    
    if (!$conn->query($sql)) {
        throw new Exception("Update failed: " . $conn->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event updated successfully',
        'id' => $id
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
