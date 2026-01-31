<?php
// Include database connection
require_once 'db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get additional user data from database
$stmt = $conn->prepare("SELECT birthday, age, gender, contact, address, barangay FROM youth_registrations WHERE member_id = ?");
$stmt->bind_param("s", $_SESSION['member_id']);
$stmt->execute();
$result = $stmt->get_result();

$user_data = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Return user data from session and database
echo json_encode([
    'success' => true,
    'member_id' => $_SESSION['member_id'],
    'name' => $_SESSION['firstname'] . ' ' . $_SESSION['lastname'],
    'firstname' => $_SESSION['firstname'],
    'lastname' => $_SESSION['lastname'],
    'email' => $_SESSION['email'],
    'birthday' => $user_data['birthday'] ?? '',
    'age' => $user_data['age'] ?? '',
    'gender' => $user_data['gender'] ?? '',
    'contact' => $user_data['contact'] ?? '',
    'address' => $user_data['address'] ?? '',
    'barangay' => $user_data['barangay'] ?? ''
]);
?>