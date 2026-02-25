<?php
// Database configuration for Supabase PostgreSQL (Transaction pooler)
// Credentials from Supabase project settings

$servername = getenv('DB_HOST') ?: "aws-1-ap-northeast-1.pooler.supabase.com";
$port = getenv('DB_PORT') ?: "6543";
$username = getenv('DB_USER') ?: "postgres.dljukwzdbkxkbngiqzmm";
$password = getenv('DB_PASSWORD') ?: "iRsZUDeb4Gqgrxp2";
$dbname = getenv('DB_NAME') ?: "postgres";

// Check if password is provided (it has a default now, but can be overridden by env var)
if (empty($password)) {
    error_log('Missing database password');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database configuration error']);
    exit;
}

// SSL mode required for Supabase
$ssl_mode = 'require';

// Create PDO connection for PostgreSQL
try {
    $dsn = "pgsql:host=$servername;port=$port;dbname=$dbname;sslmode=$ssl_mode;connect_timeout=10";
    
    error_log("Attempting connection to: $servername:$port as $username");
    
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    
    error_log("Database connection successful!");
    
} catch (PDOException $e) {
    error_log('DATABASE ERROR: ' . $e->getMessage());
    error_log('Host: ' . $servername . ', Port: ' . $port . ', User: ' . $username);
    error_log('DSN: ' . $dsn);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Backward compatibility
$conn = $pdo;
?>
