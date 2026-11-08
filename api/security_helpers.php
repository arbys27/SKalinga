<?php

require_once dirname(__DIR__) . '/includes/request.php';

function securityStartSession(): void
{
    secureSessionStart();
}

function securityCsrfToken(): string
{
    securityStartSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function securityRequireCsrf(): void
{
    securityStartSession();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
        exit;
    }
}

function securityClientIp(): string
{
    return clientIp();
}

function securityUserAgentHash(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function otpHmacSecret(): string
{
    require_once dirname(__DIR__) . '/includes/config.php';
    return skalingaConfig()['SITE_KEY'];
}

function otpCodeHash(string $code): string
{
    return hash_hmac('sha256', $code, otpHmacSecret());
}

function otpGenerateCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otpMaskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits) || strlen($digits) < 5) {
        return 'the registered number';
    }

    return substr($digits, 0, 2) . str_repeat('*', max(0, strlen($digits) - 5)) . substr($digits, -3);
}

function otpFormatPhone(string $phone): string
{
    $phone = trim($phone);
    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
    }

    throw new InvalidArgumentException('The registered mobile number is not in a supported format.');
}

function otpReserveSend(PDO $pdo, string $purpose, string $subject): void
{
    $phoneKey = 'phone:' . substr(hash('sha256', $purpose . ':' . $subject), 0, 38);
    $ipKey = securityClientIp();
    $rateKeys = [
        [$ipKey, 'otp_' . $purpose . '_ip'],
        [$phoneKey, 'otp_' . $purpose . '_phone'],
    ];

    foreach ($rateKeys as [$key, $attemptType]) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*), MAX(attempted_at) FROM login_attempts
             WHERE ip_address = ? AND attempt_type = ?
               AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL \'1 hour\')'
        );
        $stmt->execute([$key, $attemptType]);
        $attempts = $stmt->fetch(PDO::FETCH_NUM);
        $attemptCount = (int)($attempts[0] ?? 0);
        $lastAttempt = isset($attempts[1]) ? strtotime((string)$attempts[1]) : false;
        $lockoutSeconds = min(1800, 30 * (2 ** max(0, $attemptCount - 1)));

        if ($attemptCount >= 5 && $lastAttempt !== false && time() - $lastAttempt < $lockoutSeconds) {
            $waitSeconds = $lockoutSeconds - (time() - $lastAttempt);
            throw new RuntimeException('rate limited: ' . $waitSeconds);
        }
    }

    $insert = $pdo->prepare('INSERT INTO login_attempts (ip_address, attempt_type) VALUES (?, ?)');
    foreach ($rateKeys as [$key, $attemptType]) {
        $insert->execute([$key, $attemptType]);
    }
}

function otpRateLimitMessage(string $exceptionMessage): string
{
    if (preg_match('/^rate limited:\s*(\d+)$/', $exceptionMessage, $matches)) {
        return 'OTP sending is temporarily rate limited. Please wait ' . $matches[1] . ' seconds before trying again.';
    }

    return 'OTP sending is temporarily rate limited. Try again later.';
}

function otpSendSms(string $registeredPhone, string $code, string $purpose): void
{
    require_once dirname(__DIR__) . '/includes/sms.php';
    sendOTP(
        otpFormatPhone($registeredPhone),
        'Your SKalinga verification code is ' . $code . '. It expires in 5 minutes.'
    );
}
