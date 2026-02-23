<?php
// db.php - PDO Database Connection to Supabase PostgreSQL
// Reads from environment variables (set in Railway)
header('Content-Type: application/json');

try {
    // Get database credentials from environment variables or use defaults for local development
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_port = getenv('DB_PORT') ?: '5432';
    $db_name = getenv('DB_NAME') ?: 'skalinga_youth';
    $db_user = getenv('DB_USER') ?: 'postgres';
    $db_password = getenv('DB_PASSWORD') ?: '';
    
    // Determine SSL mode - use require for Supabase, allow for local
    $ssl_mode = (strpos($db_host, 'supabase') !== false) ? 'require' : 'allow';
    
    // Build connection string
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=$ssl_mode";
    
    $pdo = new PDO($dsn, $db_user, $db_password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}
?>
