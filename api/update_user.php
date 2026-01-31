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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Validate required fields
$required_fields = ['firstname', 'lastname', 'birthday', 'age', 'gender', 'contact', 'email', 'address'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
        exit;
    }
}

// Additional validation
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (!preg_match('/^[0-9]{11}$/', $data['contact'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Contact number must be 11 digits']);
    exit;
}

if (!in_array($data['gender'], ['Male', 'Female', 'Other'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid gender selection']);
    exit;
}

if ($data['age'] < 13 || $data['age'] > 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Age must be between 13 and 30']);
    exit;
}

// Prepare update query
$stmt = $conn->prepare("UPDATE youth_registrations SET
    firstname = ?,
    lastname = ?,
    birthday = ?,
    age = ?,
    gender = ?,
    contact = ?,
    email = ?,
    address = ?
    WHERE member_id = ?");

$stmt->bind_param("sssisssss",
    $data['firstname'],
    $data['lastname'],
    $data['birthday'],
    $data['age'],
    $data['gender'],
    $data['contact'],
    $data['email'],
    $data['address'],
    $_SESSION['member_id']
);

if ($stmt->execute()) {
    // Update session data
    $_SESSION['firstname'] = $data['firstname'];
    $_SESSION['lastname'] = $data['lastname'];
    $_SESSION['email'] = $data['email'];

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}

$stmt->close();
$conn->close();
?>