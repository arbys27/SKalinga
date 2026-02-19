<?php
// Setup: Create incidents table if it doesn't exist
session_start();
header('Content-Type: application/json');

require 'db_connect.php';

// Just require that someone is logged in (admin OR user)
// Not specific to admin since this is infrastructure setup
if (!isset($_SESSION['admin_authenticated']) && !isset($_SESSION['member_id'])) {
    error_log('Setup attempted by unauthorized user');
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

try {
    // Check if PDO connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Create incidents table (PostgreSQL syntax)
    $sql = "CREATE TABLE IF NOT EXISTS incidents (
        id SERIAL PRIMARY KEY,
        member_id VARCHAR(50) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        location VARCHAR(255) NOT NULL,
        urgency VARCHAR(20) NOT NULL DEFAULT 'low',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        photo_path VARCHAR(255),
        submitted_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        admin_notes TEXT
    )";
    
    $pdo->exec($sql);
    
    // Create indexes
    $indexSql = "
        CREATE INDEX IF NOT EXISTS idx_incidents_member_id ON incidents(member_id);
        CREATE INDEX IF NOT EXISTS idx_incidents_status ON incidents(status);
        CREATE INDEX IF NOT EXISTS idx_incidents_category ON incidents(category);
        CREATE INDEX IF NOT EXISTS idx_incidents_urgency ON incidents(urgency);
        CREATE INDEX IF NOT EXISTS idx_incidents_submitted_date ON incidents(submitted_date);
    ";
    
    // Execute each index separately
    foreach (explode(';', $indexSql) as $indexStatement) {
        $statement = trim($indexStatement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    // Create uploads directories if they don't exist
    $directories = [
        '../uploads/',
        '../uploads/incidents/',
        '../uploads/printing/',
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Incidents table setup completed successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Setup error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Setup failed: ' . $e->getMessage()
    ]);
    exit;
}
?>
