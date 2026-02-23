<?php
// Send OTP via SMS for login verification
require_once 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$trust_device = isset($_POST['trust_device']) && $_POST['trust_device'] === '1';

// Validate inputs
if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid 11-digit phone number is required']);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

try {
    // Check if user exists with this phone number
    $stmt = $pdo->prepare("
        SELECT 
            u.id, 
            u.member_id, 
            u.password_hash, 
            u.status,
            u.last_otp_verified_at,
            p.firstname, 
            p.lastname
        FROM users u
        LEFT JOIN youth_profiles p ON u.id = p.user_id
        WHERE p.phone = ?
    ");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password']);
        exit;
    }

    // Check if account is active
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is inactive. Please contact support.']);
        exit;
    }

    session_start();

    // Check if device is trusted (OTP verified in last 30 days)
    $skip_otp = false;
    if ($trust_device && !empty($user['last_otp_verified_at'])) {
        $last_verified = strtotime($user['last_otp_verified_at']);
        $thirty_days_ago = time() - (30 * 24 * 60 * 60);
        
        if ($last_verified > $thirty_days_ago) {
            // Device is trusted, skip OTP
            $skip_otp = true;
            
            // Create authenticated session directly
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['member_id'] = $user['member_id'];
            $_SESSION['phone'] = $phone;
            $_SESSION['authenticated'] = true;
            
            echo json_encode([
                'success' => true,
                'message' => 'Welcome back! Login successful.',
                'skip_otp' => true,
                'redirect' => 'youth-portal.html'
            ]);
            exit;
        }
    }

    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store OTP in session (expires in 10 minutes)
    $_SESSION['login_otp'] = $otp;
    $_SESSION['login_phone'] = $phone;
    $_SESSION['login_user_id'] = $user['id'];
    $_SESSION['login_member_id'] = $user['member_id'];
    $_SESSION['login_firstname'] = $user['firstname'];
    $_SESSION['login_lastname'] = $user['lastname'];
    $_SESSION['login_trust_device'] = $trust_device;
    $_SESSION['otp_expires_at'] = time() + 600; // 10 minutes
    
    // Format phone for SMS API (convert to +63 format)
    $formatted_phone = '+63' . substr($phone, 1);
    
    // Send SMS via SMS API Philippines
    $sms_message = "Your SKalinga login code is: $otp. Valid for 10 minutes.";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://sms-api-ph-gceo.onrender.com/send/sms');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'x-api-key: sk-e481790680e0f0783c3cc8af',
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'recipient' => $formatted_phone,
        'message' => $sms_message
    ]));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $sms_response = json_decode($response, true);
    
    if ($http_code === 200 && isset($sms_response['success']) && $sms_response['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Login code sent to your phone',
            'skip_otp' => false,
            'phone' => $phone
        ]);
    } else {
        // Log SMS failure
        error_log("SMS API Error during login: " . print_r($sms_response, true));
        echo json_encode([
            'success' => true,
            'message' => 'Login code has been generated. Check your phone or email.',
            'skip_otp' => false,
            'phone' => $phone
        ]);
    }
    
} catch (Exception $e) {
    error_log("Login OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error processing login: ' . $e->getMessage()]);
}
?>
