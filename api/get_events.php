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
    $params = [];
    
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (title LIKE ? OR location LIKE ? OR description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $query .= " ORDER BY date ASC, start_time ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $events = [];
    foreach ($results as $row) {
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
