<?php
// Database configuration for Supabase PostgreSQL
$servername = "db.dljukwzdbkxkbngiqzmm.supabase.co";
$port = "5432";
$username = "postgres";
$password = "yJDLVsK8NucNoOzF";
$dbname = "postgres";

// Create PDO connection for PostgreSQL
try {
    $pdo = new PDO(
        "pgsql:host=$servername;port=$port;dbname=$dbname;sslmode=require",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    // For APIs, return JSON error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Backward compatibility: create $conn object for existing code using mysqli-style queries
// Map to PDO for compatibility
$conn = $pdo;
?>
