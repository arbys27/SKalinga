<?php
// api/upload_print_request.php - Upload document and create print request
header('Content-Type: application/json');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. User must be logged in.'
    ]);
    exit;
}

try {
    require_once 'db.php';
    
    // Validate required fields
    $required_fields = ['document_title', 'print_type', 'paper_size', 'copies'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate file upload
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload failed or no file provided");
    }
    
    $file = $_FILES['document'];
    $file_name = basename($file['name']);
    $file_size = $file['size'];
    $tmp_path = $file['tmp_name'];
    
    // Validate file type
    $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        throw new Exception("File type not allowed. Allowed types: " . implode(', ', $allowed_extensions));
    }
    
    // Validate file size (max 10MB)
    $max_size = 10 * 1024 * 1024;
    if ($file_size > $max_size) {
        throw new Exception("File size exceeds 10MB limit");
    }
    
    // Create uploads directory if not exists
    $upload_dir = __DIR__ . '/../uploads/printing';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    // Generate unique filename
    $timestamp = time();
    $random_str = substr(md5(uniqid()), 0, 8);
    $new_filename = $timestamp . '_' . $random_str . '.' . $file_ext;
    $file_path = $upload_dir . '/' . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($tmp_path, $file_path)) {
        throw new Exception("Failed to save uploaded file");
    }
    
    // Get user information from session
    $user_id = $_SESSION['user_id'];
    $firstname = $_SESSION['firstname'] ?? 'Unknown';
    $lastname = $_SESSION['lastname'] ?? '';
    
    // Get member_id from users table
    $stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        unlink($file_path);
        throw new Exception("User not found");
    }
    
    $member_id = $user['member_id'];
    $member_name = trim($firstname . ' ' . $lastname);
    $document_title = trim($_POST['document_title']);
    $print_type = $_POST['print_type'];
    $paper_size = $_POST['paper_size'];
    $copies = intval($_POST['copies']);
    
    // Insert into printing_requests table
    $stmt = $pdo->prepare("
        INSERT INTO printing_requests 
        (member_id, member_name, document_title, file_path, file_name, file_size, print_type, paper_size, copies, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURRENT_TIMESTAMP)
    ");
    
    $stmt->execute([
        $member_id,
        $member_name,
        $document_title,
        'uploads/printing/' . $new_filename,
        $file_name,
        $file_size,
        $print_type,
        $paper_size,
        $copies
    ]);
    
    $request_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Print request submitted successfully',
        'request_id' => $request_id,
        'member_id' => $member_id,
        'member_name' => $member_name,
        'data' => [
            'request_id' => $request_id,
            'member_id' => $member_id,
            'member_name' => $member_name,
            'document_title' => $document_title
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
