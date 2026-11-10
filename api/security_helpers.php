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

function securityLogException(string $context, Throwable $exception, array $sensitiveValues = []): string
{
    $reference = bin2hex(random_bytes(4));
    $message = $exception->getMessage();
    if (property_exists($exception, 'providerResponse') && is_string($exception->providerResponse)) {
        $message .= ' Provider response: ' . $exception->providerResponse;
    }
    foreach ($sensitiveValues as $value) {
        if (is_string($value) && $value !== '') {
            $message = str_replace($value, '[REDACTED]', $message);
        }
    }
    error_log('[' . $context . '] ref=' . $reference . ' ' . get_class($exception) . ': '
        . $message . ' at ' . $exception->getFile() . ':' . $exception->getLine());
    return $reference;
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

function loginChallengeUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function setLoginChallengeCookie(string $token, int $expires): void
{
    setcookie('login_challenge', $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearLoginChallengeCookie(): void
{
    setcookie('login_challenge', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function setTrustedDeviceCookie(string $token, int $expires): void
{
    setcookie('trusted_device', $token, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function establishLoginSession(array $account, string $role, string $identifier, string $name, bool $isAdmin): void
{
    authStartSession();
    session_regenerate_id(true);
    foreach ([
        'otp_reauth_required', 'otp_reauth_user_id', 'otp_reauth_role', 'otp_reauth_identifier',
        'otp_reauth_name', 'otp_reauth_member_id', 'otp_reauth_email', 'otp_reauth_redirect',
        'login_otp_off'
    ] as $key) {
        unset($_SESSION[$key]);
    }
    $_SESSION['auth_user_id'] = (int)$account['id'];
    $_SESSION['auth_role'] = $role;
    $_SESSION['auth_identifier'] = $identifier;
    $_SESSION['auth_name'] = $name;
    $_SESSION['member_id'] = $role === 'youth' ? ($account['member_id'] ?? null) : null;
    $_SESSION['user_id'] = $role === 'youth' ? (int)$account['id'] : null;
    $_SESSION['authenticated'] = $role === 'youth';
    $_SESSION['admin_authenticated'] = $isAdmin;
    $_SESSION['admin_id'] = $isAdmin ? (int)$account['id'] : null;
    $_SESSION['admin_username'] = $isAdmin ? (string)$account['username'] : null;
    $_SESSION['admin_email'] = $isAdmin ? ($account['email'] ?? null) : null;
    $_SESSION['admin_role'] = $role === 'admin' ? 'superadmin' : ($role === 'sk_official' ? ($account['role'] ?? 'staff') : null);
    $_SESSION['otp_verified_at'] = time();
}

function otpGenerateCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function otpMaskPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits)) {
        return 'the registered number';
    }
    if (str_starts_with($digits, '63')) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10 || $digits[0] !== '9') {
        return 'the registered number';
    }
    return '+63' . substr($digits, 0, 3) . '****' . substr($digits, -3);
}

function otpMaskEmail(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return 'your registered email';
    }

    $local = $parts[0];
    $visible = function_exists('mb_substr') ? mb_substr($local, 0, 1, 'UTF-8') : substr($local, 0, 1);
    return $visible . str_repeat('*', max(3, strlen($local) - 1)) . '@' . $parts[1];
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
