<?php
// Include database connection
require_once 'db_connect.php';

// Start session for phone verification
session_start();

error_log("=== register.php START ===");
error_log("POST data keys: " . implode(", ", array_keys($_POST)));
error_log("SESSION data: " . print_r($_SESSION, true));

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

error_log("Form data - Firstname: $firstname, Email: $email, Phone: $contact");

// Phone verification can come from either POST (for direct API calls) or SESSION (for web registration)
$phone_verified = (int)($_POST['phone-verified'] ?? ($_SESSION['phone_verified'] ? 1 : 0));
$verified_phone = $_SESSION['verified_phone'] ?? null;

error_log("Phone verified: $phone_verified, Verified phone: $verified_phone");

// Validation
$errors = [];

if (empty($firstname)) $errors[] = "First name is required";
if (empty($lastname)) $errors[] = "Last name is required";
if (empty($birthday)) $errors[] = "Birthday is required";
if ($age < 0 || $age > 120) $errors[] = "Age must be between 0 and 120 years old";
if (empty($gender)) $errors[] = "Gender is required";
if (empty($contact) || !preg_match('/^[0-9]{11}$/', $contact)) $errors[] = "Valid 11-digit contact number is required";
if (!$phone_verified) $errors[] = "Phone number must be verified via OTP";
if (empty($address)) $errors[] = "Address is required";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
if ($password !== $confirm_password) $errors[] = "Passwords do not match";

error_log("Validation errors: " . implode(", ", $errors));

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    error_log("=== register.php END (validation failed) ===");
    exit;
}

// Generate unique member ID
function generateMemberId($pdo) {
    do {
        $id = 'SK-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE member_id = ?");
        $stmt->execute([$id]);
        $exists = $stmt->rowCount() > 0;
    } while ($exists);

    return $id;
}

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->rowCount() > 0) {
    error_log("Email already exists: $email");
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    error_log("=== register.php END (email exists) ===");
    exit;
}

// Check if phone already exists in youth_profiles
$stmt = $pdo->prepare("SELECT user_id FROM youth_profiles WHERE phone = ?");
$stmt->execute([$contact]);

if ($stmt->rowCount() > 0) {
    error_log("Phone already exists: $contact");
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Phone number already registered']);
    error_log("=== register.php END (phone exists) ===");
    exit;
}

// Generate member ID
$member_id = generateMemberId($pdo);
error_log("Generated member ID: $member_id");

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

error_log("Barangay extracted: $barangay");

// Start transaction for data consistency
$pdo->beginTransaction();
error_log("Transaction started");

try {
    // Step 1: Insert into users table (authentication)
    error_log("Inserting into users table...");
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, member_id, status, email_verified, phone_verified, created_at)
        VALUES (?, ?, ?, 'active', false, true, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([$email, $password_hash, $member_id]);
    $user_id = $pdo->lastInsertId();
    error_log("User inserted with ID: $user_id");
    
    // Step 2: Insert into youth_profiles table (profile data)
    error_log("Inserting into youth_profiles table...");
    $stmt = $pdo->prepare("
        INSERT INTO youth_profiles 
        (user_id, firstname, lastname, birthday, age, gender, phone, address, barangay, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $user_id, $firstname, $lastname, $birthday, $age, $gender,
        $contact, $address, $barangay
    ]);
    error_log("Profile inserted for user: $user_id");
    
    // Commit transaction
    $pdo->commit();
    error_log("Transaction committed");
    
    // Clear phone verification from session
    unset($_SESSION['phone_verified']);
    unset($_SESSION['verified_phone']);
    unset($_SESSION['registration_otp']);
    unset($_SESSION['registration_phone']);
    unset($_SESSION['otp_expires_at']);
    
    // Registration successful
    error_log("=== register.php END (success) ===");
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful!',
        'member_id' => $member_id,
        'user_id' => $user_id,
        'redirect' => 'index.html'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Exception during registration: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    error_log("=== register.php END (exception) ===");
}
?>