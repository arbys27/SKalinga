<?php
// get_events.php - Fetch all events from database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require 'db_connect.php';

try {
    // Get optional filter parameters
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    $query = "SELECT event_id, title, date, start_time, end_time, location, event_type, description, capacity, registered_count, registration_link, image_path, status FROM events WHERE 1=1";
    
    if ($status) {
        $status = $conn->real_escape_string($status);
        $query .= " AND status = '$status'";
    }
    
    if ($search) {
        $search = $conn->real_escape_string($search);
        $query .= " AND (title LIKE '%$search%' OR location LIKE '%$search%' OR description LIKE '%$search%')";
    }
    
    $query .= " ORDER BY date ASC, start_time ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Map database columns to expected format
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['title'],
            'date' => $row['date'],
            'time' => $row['start_time'],
            'endTime' => $row['end_time'],
            'location' => $row['location'],
            'type' => $row['event_type'],
            'description' => $row['description'],
            'capacity' => $row['capacity'],
            'registeredCount' => $row['registered_count'],
            'regLink' => $row['registration_link'],
            'imagePath' => $row['image_path'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $events,
        'count' => count($events)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
