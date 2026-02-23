<?php
// debug_connection.php - Test database connection
header('Content-Type: application/json');

// Show environment variables
$debug = [
    'php_version' => phpversion(),
    'getenv_DB_HOST' => getenv('DB_HOST'),
    'getenv_DB_USER' => getenv('DB_USER'),
    'getenv_DB_PASSWORD' => getenv('DB_PASSWORD'),
    'getenv_DB_NAME' => getenv('DB_NAME'),
    'getenv_DB_PORT' => getenv('DB_PORT'),
    '_ENV_DB_HOST' => $_ENV['DB_HOST'] ?? 'NOT SET',
    'putenv_works' => 'testing'
];

// Test connection
try {
    $db_host = getenv('DB_HOST') ?: 'localhost';
    $db_port = getenv('DB_PORT') ?: '5432';
    $db_name = getenv('DB_NAME') ?: 'postgres';
    $db_user = getenv('DB_USER') ?: 'postgres';
    $db_password = getenv('DB_PASSWORD') ?: '';
    
    $ssl_mode = (strpos($db_host, 'supabase') !== false) ? 'require' : 'allow';
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=$ssl_mode";
    
    $debug['connection_attempt'] = "DSN: $dsn";
    
    $pdo = new PDO($dsn, $db_user, $db_password);
    $debug['connection_status'] = 'SUCCESS ✅';
} catch (PDOException $e) {
    $debug['connection_status'] = 'FAILED ❌';
    $debug['error'] = $e->getMessage();
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
