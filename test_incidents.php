<?php
require_once 'api/db_connect.php';

// Test incidents table
try {
    echo "Testing incidents table...\n\n";
    
    // Check if table exists
    $stmt = $pdo->query("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_name = 'incidents')");
    $exists = $stmt->fetchColumn();
    
    echo "Table exists: " . ($exists ? 'YES' : 'NO') . "\n";
    
    if ($exists) {
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM incidents");
        $result = $stmt->fetch();
        echo "Total incidents: " . $result['count'] . "\n\n";
        
        // Get sample data
        $stmt = $pdo->query("SELECT id, member_id, category, urgency, status, submitted_date FROM incidents ORDER BY id DESC LIMIT 5");
        $incidents = $stmt->fetchAll();
        
        if ($incidents) {
            echo "Sample incidents:\n";
            foreach ($incidents as $incident) {
                echo "- ID: {$incident['id']}, Member: {$incident['member_id']}, Category: {$incident['category']}, Urgency: {$incident['urgency']}, Status: {$incident['status']}\n";
            }
        } else {
            echo "No incidents found in table\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
