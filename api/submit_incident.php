<?php
// API: Submit Incident Report
session_start();
header('Content-Type: application/json');

// Set error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require 'db_connect.php';

// Check if user is authenticated
if (!isset($_SESSION['member_id'])) {
    error_log('Submit incident failed: User not authenticated. SESSION: ' . json_encode($_SESSION));
    echo json_encode(['success' => false, 'error' => 'User not authenticated. Please login first.']);
    exit;
}

$member_id = $_SESSION['member_id'];

// Get form data
$category = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$location = trim($_POST['location'] ?? '');
$urgency = trim($_POST['urgency'] ?? 'low');

// Validate required fields
if (empty($category) || empty($description) || empty($location)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields (category, description, location)']);
    exit;
}

// Validate category and urgency
$valid_categories = ['safety', 'bullying', 'environment', 'health', 'infrastructure', 'emergency', 'other'];
$valid_urgencies = ['low', 'medium', 'high', 'emergency'];

if (!in_array($category, $valid_categories)) {
    echo json_encode(['success' => false, 'error' => 'Invalid category']);
    exit;
}

if (!in_array($urgency, $valid_urgencies)) {
    echo json_encode(['success' => false, 'error' => 'Invalid urgency level']);
    exit;
}

// Handle multiple photo uploads
$photo_paths = [];
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle both single and multiple file uploads
    $files = $_FILES['photo'];
    $is_multiple = is_array($files['name']);
    
    // Normalize to array format for consistent handling
    if (!$is_multiple) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $upload_dir = '../uploads/incidents/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log('Failed to create upload directory: ' . $upload_dir);
            echo json_encode(['success' => false, 'error' => 'Server error: Cannot create upload directory']);
            exit;
        }
    }
    
    // Process each uploaded file
    foreach ($files['tmp_name'] as $index => $tmp_name) {
        // Skip if no file or error
        if ($files['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($files['error'][$index] !== UPLOAD_ERR_OK) {
            error_log('Upload error code: ' . $files['error'][$index]);
            echo json_encode(['success' => false, 'error' => 'Error uploading file']);
            exit;
        }
        
        $filename = basename($files['name'][$index]);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF allowed']);
            exit;
        }
        
        if ($files['size'][$index] > 5 * 1024 * 1024) { // 5MB limit per file
            echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
            exit;
        }
        
        $new_filename = 'incident_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($tmp_name, $upload_path)) {
            error_log('Failed to move uploaded file to: ' . $upload_path);
            echo json_encode(['success' => false, 'error' => 'Server error: Cannot save uploaded file']);
            exit;
        }
        
        $photo_paths[] = 'uploads/incidents/' . $new_filename;
    }
}

// Store photo paths as JSON array
$photo_path = !empty($photo_paths) ? json_encode($photo_paths) : null;

try {
    // Verify database connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not initialized');
    }
    
    // Test the connection
    $pdo->query('SELECT 1');
    
    $stmt = $pdo->prepare('
        INSERT INTO incidents (member_id, category, description, location, urgency, photo_path, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $pdo->errorInfo()[2]);
    }
    
    $execute_result = $stmt->execute([$member_id, $category, $description, $location, $urgency, $photo_path, 'pending']);
    
    if (!$execute_result) {
        throw new Exception('Failed to execute statement: ' . implode(', ', $stmt->errorInfo()));
    }
    
    $incident_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Incident report submitted successfully',
        'incident_id' => $incident_id
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in submit_incident.php: ' . $e->getMessage());
    error_log('Error code: ' . $e->getCode());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit;
    
} catch (Exception $e) {
    error_log('General error in submit_incident.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>
