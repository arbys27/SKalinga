<?php
// setup_database_complete.php - Complete setup with sample data
header('Content-Type: application/json');

require_once 'db_connect.php';

try {
    // Step 1: Create events table
    $createTableSQL = "CREATE TABLE IF NOT EXISTS `events` (
        `id` VARCHAR(20) PRIMARY KEY COMMENT 'Event ID (EVT-YYYY-NNNN)',
        `title` VARCHAR(255) NOT NULL COMMENT 'Event title',
        `date` DATE NOT NULL COMMENT 'Event date',
        `time` TIME NOT NULL COMMENT 'Event start time',
        `location` VARCHAR(255) NOT NULL COMMENT 'Event location',
        `type` VARCHAR(100) NOT NULL COMMENT 'Event type (Training, Sports, Cultural, Community, Other)',
        `description` LONGTEXT COMMENT 'Event description',
        `regLink` VARCHAR(500) COMMENT 'Registration/Google Form link',
        `status` ENUM('Upcoming', 'Ongoing', 'Past') DEFAULT 'Upcoming' COMMENT 'Event status',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Event creation timestamp',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
        
        INDEX `idx_date` (`date`),
        INDEX `idx_time` (`time`),
        INDEX `idx_status` (`status`),
        INDEX `idx_type` (`type`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='SK Events management table'";
    
    if (!$conn->query($createTableSQL)) {
        throw new Exception("Error creating events table: " . $conn->error);
    }
    
    $messages = ['✅ Events table created/verified successfully'];
    
    // Step 2: Check if table is empty
    $checkEmpty = $conn->query("SELECT COUNT(*) as count FROM `events`");
    $isEmpty = $checkEmpty->fetch_assoc()['count'] === 0;
    
    // Step 3: Add sample data if table is empty
    if ($isEmpty) {
        $sampleEvents = [
            [
                'id' => 'EVT-2026-0001',
                'title' => 'Youth Leadership Summit 2026',
                'date' => '2026-02-15',
                'time' => '09:00:00',
                'location' => 'Barangay Hall, San Antonio',
                'type' => 'Training',
                'description' => 'A full-day leadership training with guest speakers, workshops, and team-building activities for youth development and personal growth. Learn from industry experts and network with peers.',
                'regLink' => 'https://forms.google.com/example1',
                'status' => 'Upcoming'
            ],
            [
                'id' => 'EVT-2026-0002',
                'title' => 'Sports Fest 2026',
                'date' => '2026-02-20',
                'time' => '14:00:00',
                'location' => 'Sports Complex',
                'type' => 'Sports',
                'description' => 'Basketball, volleyball, and fun runs tournaments. Open for team and individual participation. All skill levels welcome! Register your team now. Prizes and certificates for winners.',
                'regLink' => 'https://forms.google.com/example2',
                'status' => 'Upcoming'
            ],
            [
                'id' => 'EVT-2026-0003',
                'title' => 'Barangay Environmental Cleanup',
                'date' => '2026-03-05',
                'time' => '10:00:00',
                'location' => 'City Park',
                'type' => 'Community',
                'description' => 'Join volunteers to clean and beautify our public spaces. Gloves and tools provided. All community members are welcome to participate!',
                'regLink' => '',
                'status' => 'Upcoming'
            ],
            [
                'id' => 'EVT-2026-0004',
                'title' => 'Cultural Festival: Our Heritage',
                'date' => '2026-03-15',
                'time' => '08:00:00',
                'location' => 'Town Center',
                'type' => 'Cultural',
                'description' => 'Celebrate our culture through music, dance, food, and crafts. Family-friendly activities all day long. Come experience the rich traditions of our community!',
                'regLink' => 'https://forms.google.com/example3',
                'status' => 'Upcoming'
            ],
            [
                'id' => 'EVT-2026-0005',
                'title' => 'Youth Career Seminar',
                'date' => '2026-02-25',
                'time' => '13:00:00',
                'location' => 'Community Center',
                'type' => 'Seminar',
                'description' => 'Learn about career opportunities in different fields. Meet professionals and get advice on education pathways. Perfect for students planning their future!',
                'regLink' => 'https://forms.google.com/example4',
                'status' => 'Upcoming'
            ]
        ];
        
        foreach ($sampleEvents as $event) {
            $stmt = $conn->prepare("INSERT INTO `events` 
                (`id`, `title`, `date`, `time`, `location`, `type`, `description`, `regLink`, `status`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            $stmt->bind_param("sssssssss", 
                $event['id'],
                $event['title'],
                $event['date'],
                $event['time'],
                $event['location'],
                $event['type'],
                $event['description'],
                $event['regLink'],
                $event['status']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error inserting sample event: " . $stmt->error);
            }
            $stmt->close();
        }
        
        $messages[] = '✅ Sample events added successfully (5 events)';
    } else {
        $countResult = $conn->query("SELECT COUNT(*) as count FROM `events`");
        $count = $countResult->fetch_assoc()['count'];
        $messages[] = "✅ Events table already has $count event(s) - no sample data added";
    }
    
    // Step 4: Verify database connection and basic structure
    $verifyTable = $conn->query("DESCRIBE `events`");
    if (!$verifyTable) {
        throw new Exception("Error verifying table structure: " . $conn->error);
    }
    
    $columnCount = $verifyTable->num_rows;
    $messages[] = "✅ Table structure verified ($columnCount columns)";
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'database' => 'skalinga_youth',
        'table' => 'events',
        'status' => 'Ready to use'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
