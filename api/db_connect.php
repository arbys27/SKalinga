<?php
// Database configuration for XAMPP MySQL
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ""; // Default XAMPP password (empty)
$dbname = "skalinga_youth";

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
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

// Also keep mysqli for backward compatibility with existing code
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("MySQLi connection failed: " . $conn->connect_error);
    // Don't die here - let PDO handle error responses
}

// Set charset to utf8
if ($conn && !$conn->connect_error) {
    $conn->set_charset("utf8");
}
?>