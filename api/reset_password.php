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
    $user_stmt->execute([$phone]);
    $user_result = $user_stmt->fetchAll();
    
    if (empty($user_result)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid mobile number or OTP']);
        exit;
    }
    
    $user = $user_result[0];
    $user_id = $user['id'];
    
    // Verify OTP
    $otp_stmt = $conn->prepare("
        SELECT id 
        FROM password_resets 
        WHERE user_id = ? 
        AND otp_code = ? 
        AND is_used = 0 
        AND expires_at > NOW()
    ");
    $otp_stmt->execute([$user_id, $otp_code]);
    $otp_result = $otp_stmt->fetchAll();
    
    if (empty($otp_result)) {
        // Check if OTP exists but is expired or used
        $check_stmt = $conn->prepare("
            SELECT id, is_used, expires_at 
            FROM password_resets 
            WHERE user_id = ? AND otp_code = ?
        ");
        $check_stmt->execute([$user_id, $otp_code]);
        $check_result = $check_stmt->fetchAll();
        
        if (!empty($check_result)) {
            $record = $check_result[0];
            if ($record['is_used']) {
                $message = 'OTP has already been used';
            } else {
                $message = 'OTP has expired. Please request a new one';
            }
        } else {
            $message = 'Invalid OTP';
        }
        
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    $otp_record = $otp_result[0];
    $otp_id = $otp_record['id'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Hash new password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user password
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET password_hash = ?, password_updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    
    if (!$update_stmt->execute([$password_hash, $user_id])) {
        throw new Exception("Failed to update password");
    }
    
    // Mark OTP as used
    $mark_used = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
    
    if (!$mark_used->execute([$otp_id])) {
        throw new Exception("Failed to mark OTP as used");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully. Please login with your new password.'
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(500);
    error_log('Password reset error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
