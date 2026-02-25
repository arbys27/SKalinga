<?php
header('Content-Type: application/json');

$debug = [
    'php_version' => phpversion(),
    'environment_variables' => [
        'DB_HOST' => getenv('DB_HOST'),
        'DB_PORT' => getenv('DB_PORT'),
        'DB_USER' => getenv('DB_USER'),
        'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***' : 'NOT SET',
        'DB_NAME' => getenv('DB_NAME'),
    ],
    'fallback_values' => [
        'host' => 'aws-1-ap-northeast-1.pooler.supabase.com',
        'port' => 6543,
        'user' => 'postgres.dljukwzdbkxkbngiqzmm',
        'database' => 'postgres'
    ]
];

// Try connection with fallback values
$db_host = getenv('DB_HOST') ?: 'aws-1-ap-northeast-1.pooler.supabase.com';
$db_port = getenv('DB_PORT') ?: '6543';
$db_user = getenv('DB_USER') ?: 'postgres.dljukwzdbkxkbngiqzmm';
$db_password = getenv('DB_PASSWORD') ?: 'jeilaclaydizon';
$db_name = getenv('DB_NAME') ?: 'postgres';

$ssl_mode = (strpos($db_host, 'supabase') !== false) ? 'require' : 'allow';

try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=$ssl_mode",
        $db_user,
        $db_password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $debug['connection'] = 'SUCCESS';
    $debug['actual_values_used'] = [
        'host' => $db_host,
        'port' => $db_port,
        'user' => $db_user,
        'database' => $db_name,
        'ssl_mode' => $ssl_mode
    ];
} catch (PDOException $e) {
    $debug['connection'] = 'FAILED';
    $debug['error'] = $e->getMessage();
    $debug['actual_values_used'] = [
        'host' => $db_host,
        'port' => $db_port,
        'user' => $db_user,
        'database' => $db_name,
        'ssl_mode' => $ssl_mode
    ];
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
