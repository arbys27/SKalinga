<?php
// Reset password using OTP
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON body since frontend sends JSON
$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');
$otp_code = trim($input['otp_code'] ?? '');
$password = $input['password'] ?? '';

// Validation
$errors = [];
if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) $errors[] = "Valid 11-digit mobile number is required";
if (empty($otp_code) || strlen($otp_code) !== 6) $errors[] = "Valid 6-digit OTP is required";
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

try {
    // Find user by phone - join users and youth_profiles tables
    $user_stmt = $conn->prepare("
        SELECT u.id FROM users u 
        INNER JOIN youth_profiles yp ON u.id = yp.user_id 
        WHERE yp.phone = ?
    ");
    $user_stmt->bind_param("s", $phone);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    
    if ($user_result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid mobile number or OTP']);
        $user_stmt->close();
        exit;
    }
    
    $user = $user_result->fetch_assoc();
    $user_id = $user['id'];
    $user_stmt->close();
    
    // Verify OTP
    $otp_stmt = $conn->prepare("
        SELECT id 
        FROM password_resets 
        WHERE user_id = ? 
        AND otp_code = ? 
        AND is_used = 0 
        AND expires_at > NOW()
    ");
    $otp_stmt->bind_param("is", $user_id, $otp_code);
    $otp_stmt->execute();
    $otp_result = $otp_stmt->get_result();
    
    if ($otp_result->num_rows === 0) {
        // Check if OTP exists but is expired or used
        $check_stmt = $conn->prepare("
            SELECT id, is_used, expires_at 
            FROM password_resets 
            WHERE user_id = ? AND otp_code = ?
        ");
        $check_stmt->bind_param("is", $user_id, $otp_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $record = $check_result->fetch_assoc();
            if ($record['is_used']) {
                $message = 'OTP has already been used';
            } else {
                $message = 'OTP has expired. Please request a new one';
            }
        } else {
            $message = 'Invalid OTP';
        }
        $check_stmt->close();
        
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    $otp_record = $otp_result->fetch_assoc();
    $otp_id = $otp_record['id'];
    $otp_stmt->close();
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Hash new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update user password
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET password_hash = ?, password_updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $update_stmt->bind_param("si", $password_hash, $user_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update password");
    }
    $update_stmt->close();
    
    // Mark OTP as used
    $mark_used = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
    $mark_used->bind_param("i", $otp_id);
    
    if (!$mark_used->execute()) {
        throw new Exception("Failed to mark OTP as used");
    }
    $mark_used->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. Please login with your new password.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
