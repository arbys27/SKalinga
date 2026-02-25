<?php
// Database configuration for Supabase PostgreSQL (Transaction pooler)
// Reads from environment variables (set in Railway)
$servername = getenv('DB_HOST') ?: "aws-1-ap-northeast-1.pooler.supabase.com";
$port = getenv('DB_PORT') ?: "6543";
$username = getenv('DB_USER') ?: "postgres.dljukwzdbkxkbngiqzmm";
$password = getenv('DB_PASSWORD') ?: "jeilaclaydizon";
$dbname = getenv('DB_NAME') ?: "postgres";

// Determine SSL mode - use require for Supabase, allow for local
$ssl_mode = (strpos($servername, 'supabase') !== false) ? 'require' : 'allow';

// Create PDO connection for PostgreSQL
try {
    $pdo = new PDO(
        "pgsql:host=$servername;port=$port;dbname=$dbname;sslmode=$ssl_mode",
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
