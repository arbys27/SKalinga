<?php
// Database configuration for Railway PostgreSQL
// Railway automatically provides DATABASE_URL environment variable

// Option 1: Use DATABASE_URL (recommended for Railway)
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse the PostgreSQL connection URL
    $db_config = parse_url($database_url);
    
    $servername = $db_config['host'];
    $port = $db_config['port'] ?? 5432;
    $username = $db_config['user'];
    $password = $db_config['pass'];
    $dbname = ltrim($db_config['path'], '/');
} else {
    // Fallback to individual environment variables
    $servername = getenv('DB_HOST') ?: "localhost";
    $port = getenv('DB_PORT') ?: "5432";
    $username = getenv('DB_USER') ?: "postgres";
    $password = getenv('DB_PASSWORD') ?: "";
    $dbname = getenv('DB_NAME') ?: "railway";
}

error_log("Database connection attempt: {$username}@{$servername}:{$port}/{$dbname}");

// SSL mode - use prefer for Railway (it handles SSL automatically)
$ssl_mode = 'prefer';

// Create PDO connection for PostgreSQL
try {
    $dsn = "pgsql:host=$servername;port=$port;dbname=$dbname;sslmode=$ssl_mode;connect_timeout=10";
    
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
    error_log('PostgreSQL connection failed: ' . $e->getMessage());
    
    // Fallback to local MySQL for development/testing
    error_log("Attempting fallback to local MySQL...");
    try {
        $mysql_dsn = "mysql:host=localhost;dbname=skalinga;charset=utf8mb4";
        $pdo = new PDO(
            $mysql_dsn,
            "root",
            "",
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10
            ]
        );
        error_log("Local MySQL connection successful (fallback)!");
    } catch (PDOException $e2) {
        error_log('LOCAL MYSQL ERROR: ' . $e2->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed',
            'detail' => 'Neither PostgreSQL (Railway) nor local MySQL could be connected. For testing SMS only, this can be ignored.'
        ]);
        exit;
    }
}

// Backward compatibility
$conn = $pdo;
?>
