<?php
// Include database connection
require_once 'db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and sanitize form data
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$birthday = $_POST['birthday'] ?? '';
$age = (int)($_POST['age'] ?? 0);
$gender = $_POST['gender'] ?? '';
$contact = trim($_POST['contact'] ?? '');
$address = trim($_POST['address'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm-password'] ?? '';

// Validation
$errors = [];

if (empty($firstname)) $errors[] = "First name is required";
if (empty($lastname)) $errors[] = "Last name is required";
if (empty($birthday)) $errors[] = "Birthday is required";
if ($age < 13 || $age > 35) $errors[] = "Age must be between 13 and 35";
if (empty($gender)) $errors[] = "Gender is required";
if (empty($contact) || !preg_match('/^[0-9]{11}$/', $contact)) $errors[] = "Valid 11-digit contact number is required";
if (empty($address)) $errors[] = "Address is required";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
if ($password !== $confirm_password) $errors[] = "Passwords do not match";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

// Generate unique member ID
function generateMemberId($conn) {
    do {
        $id = 'SK-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT id FROM youth_registrations WHERE member_id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $id;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM youth_registrations WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Generate member ID
$member_id = generateMemberId($conn);

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Extract barangay from address (simple extraction)
$barangay = 'San Antonio'; // Default
if (stripos($address, 'brgy') !== false || stripos($address, 'barangay') !== false) {
    // Try to extract barangay from address
    preg_match('/(?:brgy\.?|barangay)\s*([^,]+)/i', $address, $matches);
    if (!empty($matches[1])) {
        $barangay = trim($matches[1]);
    }
}

// Insert user data
$stmt = $conn->prepare("INSERT INTO youth_registrations
    (member_id, firstname, lastname, birthday, age, gender, contact, address, email, password_hash, barangay)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("ssssissssss",
    $member_id, $firstname, $lastname, $birthday, $age, $gender,
    $contact, $address, $email, $password_hash, $barangay
);

if ($stmt->execute()) {
    // Registration successful
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!',
        'member_id' => $member_id,
        'redirect' => 'youth-portal.html'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>