<?php
// add_event.php - Add new event with image upload support
header('Content-Type: application/json');

require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get event data from POST
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $startTime = $_POST['startTime'] ?? '';
    $endTime = $_POST['endTime'] ?? '';
    $location = $_POST['location'] ?? '';
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $capacity = $_POST['capacity'] ?? 0;
    $regLink = $_POST['regLink'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';

    // Validate required fields
    if (!$title || !$date || !$startTime || !$location || !$type) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = '../assets/images/events/';
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
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

    // Generate Event ID (EVT-YYYY-NNNN)
    $year = date('Y');
    $countResult = $conn->query("SELECT COUNT(*) as cnt FROM events WHERE YEAR(created_at) = $year");
    $countRow = $countResult->fetch_assoc();
    $nextNum = str_pad($countRow['cnt'] + 1, 4, '0', STR_PAD_LEFT);
    $eventId = "EVT-" . $year . "-" . $nextNum;

    // Insert into database
    $sql = "INSERT INTO events (event_id, title, description, event_type, date, start_time, end_time, location, capacity, registration_link, image_path, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('sssssssissss', $eventId, $title, $description, $type, $date, $startTime, $endTime, $location, $capacity, $regLink, $imagePath, $status);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Event added successfully',
        'event' => [
            'id' => $eventId,
            'title' => $title,
            'date' => $date,
            'time' => $startTime,
            'endTime' => $endTime,
            'location' => $location,
            'type' => $type,
            'description' => $description,
            'capacity' => $capacity,
            'regLink' => $regLink,
            'imagePath' => $imagePath,
            'status' => $status
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
