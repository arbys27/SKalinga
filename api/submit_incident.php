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
// Handle multiple photo uploads
$photo_paths = [];

// DEBUG: Full detailed logging
error_log('========== SUBMIT_INCIDENT DEBUG START ==========');
error_log('Full $_FILES array: ' . json_encode($_FILES, JSON_PRETTY_PRINT));
error_log('$_FILES[photo] exists: ' . (isset($_FILES['photo']) ? 'YES' : 'NO'));

if (isset($_FILES['photo'])) {
    error_log('$_FILES[photo][name]: ' . json_encode($_FILES['photo']['name']));
    error_log('$_FILES[photo][name] is array: ' . (is_array($_FILES['photo']['name']) ? 'YES' : 'NO'));
    error_log('$_FILES[photo][error]: ' . json_encode($_FILES['photo']['error']));
    error_log('$_FILES[photo][tmp_name]: ' . json_encode($_FILES['photo']['tmp_name']));
    error_log('$_FILES[photo][size]: ' . json_encode($_FILES['photo']['size']));
}

// Check if photo field exists AND has files
if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
    // Handle both single and multiple file uploads
    $files = $_FILES['photo'];
    $is_multiple = is_array($files['name']);
    error_log('is_multiple: ' . ($is_multiple ? 'YES' : 'NO'));
    
    if ($is_multiple) {
        error_log('Processing ' . count($files['name']) . ' files as array');
    } else {
        error_log('Single file - converting to array format');
    }
    
    // Normalize to array format for consistent handling
    if (!$is_multiple) {
        error_log('Converting single file to array format');
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
    $file_count = count($files['tmp_name']);
    error_log('Total files to process: ' . $file_count);
    
    foreach ($files['tmp_name'] as $index => $tmp_name) {
        $error_code = $files['error'][$index];
        error_log('--- File ' . ($index + 1) . '/' . $file_count . ' ---');
        error_log('Name: ' . $files['name'][$index]);
        error_log('Error code: ' . $error_code . ' (0=OK, 4=NO_FILE)');
        error_log('Tmp name: ' . $tmp_name);
        error_log('Size: ' . $files['size'][$index]);
        
        // Skip if no file or error
        if ($error_code === UPLOAD_ERR_NO_FILE) {
            error_log('Skipping - NO_FILE');
            continue;
        }
        
        if ($error_code !== UPLOAD_ERR_OK) {
            error_log('ERROR: File has error code ' . $error_code);
            echo json_encode(['success' => false, 'error' => 'Error uploading file']);
            exit;
        }
        
        $filename = basename($files['name'][$index]);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        error_log('Extension: ' . $ext);
        
        if (!in_array($ext, $allowed)) {
            error_log('ERROR: Invalid file type');
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF allowed']);
            exit;
        }
        
        if ($files['size'][$index] > 5 * 1024 * 1024) { // 5MB limit per file
            error_log('ERROR: File size exceeds limit');
            echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']);
            exit;
        }
        
        $new_filename = 'incident_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;
        
        error_log('Moving to: ' . $upload_path);
        
        if (!move_uploaded_file($tmp_name, $upload_path)) {
            error_log('ERROR: Failed to move file');
            error_log('Failed to move uploaded file to: ' . $upload_path);
            echo json_encode(['success' => false, 'error' => 'Server error: Cannot save uploaded file']);
            exit;
        }
        
        error_log('SUCCESS: File saved');
        $photo_paths[] = 'uploads/incidents/' . $new_filename;
    }
    error_log('Total photos saved: ' . count($photo_paths));
    error_log('Photo paths array: ' . json_encode($photo_paths));
}
error_log('========== SUBMIT_INCIDENT DEBUG END ==========');

// Store photo paths as JSON array
$photo_path = !empty($photo_paths) ? json_encode($photo_paths) : null;
error_log('DEBUG: About to insert - photo_path value: ' . $photo_path);
error_log('DEBUG: About to insert - photo_paths count: ' . count($photo_paths));

try {
    // Verify database connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not initialized');
    }
    
    // Test the connection
    $pdo->query('SELECT 1');
    
    // Ensure urgency is one of the valid options
    if (!in_array($urgency, $valid_urgencies)) {
        throw new Exception('Invalid urgency level: ' . $urgency);
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO incidents (member_id, category, description, location, urgency, status, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $pdo->errorInfo()[2]);
    }
    
    $execute_result = $stmt->execute([
        $member_id, 
        $category, 
        $description, 
        $location, 
        $urgency, 
        'pending',
        $photo_path
    ]);
    
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
