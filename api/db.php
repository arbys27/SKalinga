<?php
// db.php - PDO Database Connection to Supabase PostgreSQL
header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "pgsql:host=db.dljukwzdbkxkbngiqzmm.supabase.co;port=5432;dbname=postgres;sslmode=require",
        "postgres",
        "jeilaclaydizon"
    );
    
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
