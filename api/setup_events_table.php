<?php
// setup_events_table.php - Initialize events table in database
header('Content-Type: application/json');

require_once 'db_connect.php';

try {
    // SQL to create events table
    $sql = "CREATE TABLE IF NOT EXISTS `events` (
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
    
    if (!$conn->query($sql)) {
        throw new Exception("Error creating events table: " . $conn->error);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Events table initialized successfully'
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
