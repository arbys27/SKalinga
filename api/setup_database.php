<?php
// Database setup script for SKalinga Youth Registration
// Run this once to create the database and tables

$servername = "localhost";
$username = "root";
$password = "";

// Create connection without database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS skalinga_youth CHARACTER SET utf8 COLLATE utf8_general_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database 'skalinga_youth' created successfully or already exists.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db("skalinga_youth");

// Create youth_registrations table
$sql = "CREATE TABLE IF NOT EXISTS youth_registrations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) UNIQUE NOT NULL,
    firstname VARCHAR(50) NOT NULL,
    lastname VARCHAR(50) NOT NULL,
    birthday DATE NOT NULL,
    age INT(3) NOT NULL,
    gender ENUM('Male', 'Female', 'Other', 'Prefer not to say') NOT NULL,
    contact VARCHAR(15) NOT NULL,
    address TEXT NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    barangay VARCHAR(100) DEFAULT 'San Antonio'
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'youth_registrations' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create print_requests table for future use
$sql = "CREATE TABLE IF NOT EXISTS print_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    member_id VARCHAR(20) NOT NULL,
    request_type ENUM('Print', 'Xerox') NOT NULL,
    pages INT(11) NOT NULL,
    print_type ENUM('Black & White', 'Colored') NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    documents TEXT,
    status ENUM('Pending', 'Finished') DEFAULT 'Pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES youth_registrations(member_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'print_requests' created successfully or already exists.<br>";
} else {
    echo "Error creating print_requests table: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>Database setup completed successfully!";
?>