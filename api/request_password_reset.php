<?php
// Password reset request - Send OTP
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');

// Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists (security best practice)
        echo json_encode([
            'success' => true,
            'message' => 'If an account exists, you will receive an OTP email'
        ]);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $stmt->close();
    
    // Generate 6-digit OTP
    $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Clear any existing non-used OTP for this user
    $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ? AND is_used = 0");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Insert new OTP record (expires in 15 minutes)
    $expires_at = date('Y-m-d H:i:s', time() + 900); // 900 seconds = 15 minutes
    
    $insert_stmt = $conn->prepare("
        INSERT INTO password_resets (user_id, otp_code, expires_at, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $insert_stmt->bind_param("iss", $user_id, $otp_code, $expires_at);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to create password reset request");
    }
    $insert_stmt->close();
    
    // TODO: Send OTP via email
    // You would implement email sending here
    // For now, log to file for testing
    error_log("OTP for $email: $otp_code (expires at $expires_at)");
    
    echo json_encode([
        'success' => true,
        'message' => 'OTP has been sent to your email',
        'otp' => $otp_code // TODO: Remove in production, send via email instead
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
