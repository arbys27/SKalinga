<?php
// setup_complete.php - Complete database setup with proper schema
header('Content-Type: application/json');
ini_set('display_errors', 0);

require 'db_connect.php';

try {
    // 1. Drop existing table (if it exists)
    $conn->query("DROP TABLE IF EXISTS events");
    
    // 2. Create events table with correct schema
    $sql = "CREATE TABLE `events` (
        `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Auto-incremented ID',
        `event_id` VARCHAR(20) UNIQUE NOT NULL COMMENT 'Event ID (EVT-YYYY-NNNN)',
        `title` VARCHAR(255) NOT NULL COMMENT 'Event title',
        `description` LONGTEXT COMMENT 'Event description',
        `event_type` VARCHAR(100) NOT NULL COMMENT 'Event type (Training, Sports, Cultural, Community, Other)',
        `date` DATE NOT NULL COMMENT 'Event date',
        `start_time` TIME NOT NULL COMMENT 'Event start time',
        `end_time` TIME COMMENT 'Event end time',
        `location` VARCHAR(255) NOT NULL COMMENT 'Event location',
        `capacity` INT DEFAULT 0 COMMENT 'Maximum number of attendees',
        `registered_count` INT DEFAULT 0 COMMENT 'Current number of registrations',
        `registration_link` VARCHAR(500) COMMENT 'Registration/Google Form link',
        `image_path` VARCHAR(500) COMMENT 'Path to event image file',
        `status` ENUM('Upcoming', 'Ongoing', 'Past') DEFAULT 'Upcoming' COMMENT 'Event status',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Event creation timestamp',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
        
        INDEX `idx_event_id` (`event_id`),
        INDEX `idx_date` (`date`),
        INDEX `idx_start_time` (`start_time`),
        INDEX `idx_status` (`status`),
        INDEX `idx_event_type` (`event_type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SK Events management table with image support'";
    
    if (!$conn->query($sql)) {
        throw new Exception("Failed to create table: " . $conn->error);
    }
    
    // 3. Insert sample events
    $sampleEvents = [
        [
            'title' => 'Youth Leadership Training',
            'date' => '2026-02-15',
            'startTime' => '14:00:00',
            'endTime' => '16:00:00',
            'location' => 'Barangay Hall, San Antonio',
            'type' => 'Training',
            'description' => 'Comprehensive leadership development program for young community members',
            'capacity' => 50,
            'regLink' => 'https://forms.gle/sample1'
        ],
        [
            'title' => 'Community Basketball Tournament',
            'date' => '2026-02-20',
            'startTime' => '09:00:00',
            'endTime' => '17:00:00',
            'location' => 'Sports Complex, San Antonio',
            'type' => 'Sports',
            'description' => 'Inter-barangay basketball tournament for youth',
            'capacity' => 100,
            'regLink' => 'https://forms.gle/sample2'
        ],
        [
            'title' => 'Environmental Advocacy Seminar',
            'date' => '2026-03-10',
            'startTime' => '10:00:00',
            'endTime' => '12:00:00',
            'location' => 'Community Center',
            'type' => 'Training',
            'description' => 'Learn about environmental conservation and sustainability practices',
            'capacity' => 75,
            'regLink' => 'https://forms.gle/sample3'
        ],
        [
            'title' => 'Youth Skills Workshop',
            'date' => '2026-03-15',
            'startTime' => '13:00:00',
            'endTime' => '15:00:00',
            'location' => 'Barangay Hall',
            'type' => 'Community',
            'description' => 'Develop practical skills for employment and entrepreneurship',
            'capacity' => 60,
            'regLink' => 'https://forms.gle/sample4'
        ],
        [
            'title' => 'Cultural Festival Celebration',
            'date' => '2026-04-01',
            'startTime' => '08:00:00',
            'endTime' => '18:00:00',
            'location' => 'Town Plaza',
            'type' => 'Cultural',
            'description' => 'Celebrate local culture and traditions with games, performances, and food',
            'capacity' => 200,
            'regLink' => 'https://forms.gle/sample5'
        ]
    ];
    
    $year = date('Y');
    $eventCount = 1;
    $createdEvents = [];
    
    foreach ($sampleEvents as $event) {
        $eventId = "EVT-" . $year . "-" . str_pad($eventCount++, 4, '0', STR_PAD_LEFT);
        
        $title = $conn->real_escape_string($event['title']);
        $date = $event['date'];
        $startTime = $event['startTime'];
        $endTime = $event['endTime'];
        $location = $conn->real_escape_string($event['location']);
        $type = $event['type'];
        $description = $conn->real_escape_string($event['description']);
        $capacity = $event['capacity'];
        $regLink = $conn->real_escape_string($event['regLink']);
        
        $insertSql = "INSERT INTO events (event_id, title, description, event_type, date, start_time, end_time, location, capacity, registration_link, status)
                      VALUES ('$eventId', '$title', '$description', '$type', '$date', '$startTime', '$endTime', '$location', $capacity, '$regLink', 'Upcoming')";
        
        if (!$conn->query($insertSql)) {
            throw new Exception("Failed to insert event: " . $conn->error);
        }
        
        $createdEvents[] = [
            'id' => $eventId,
            'title' => $event['title'],
            'date' => $event['date']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'tableCreated' => true,
        'sampleEventsAdded' => count($createdEvents),
        'events' => $createdEvents
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>

