<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class MailQuotaException extends RuntimeException
{
}

final class MailDeliveryException extends RuntimeException
{
    public int $httpStatus;
    public string $providerResponse;

    public function __construct(string $message, int $httpStatus, string $providerResponse)
    {
        $this->httpStatus = $httpStatus;
        $this->providerResponse = $providerResponse;
        parent::__construct($message);
    }
}

function emailJsMonthlyLimit(): int
{
    $configured = (int)(skalingaConfig()['EMAILJS_MONTHLY_LIMIT'] ?? 200);
    return max(1, $configured > 0 ? $configured : 200);
}

function reserveEmailOtpAttempt(PDO $pdo): array
{
    $limit = emailJsMonthlyLimit();
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT pg_advisory_xact_lock(741209663218)');
        $pdo->exec(
            "INSERT INTO public.mail_usage (month, provider, sent_count, last_sent_at)
             VALUES (to_char(CURRENT_DATE, 'YYYY-MM'), 'emailjs', 0, NULL)
             ON CONFLICT (month, provider) DO NOTHING"
        );
        $stmt = $pdo->prepare(
            "SELECT sent_count, last_sent_at
             FROM public.mail_usage
             WHERE month = to_char(CURRENT_DATE, 'YYYY-MM') AND provider = 'emailjs'
             FOR UPDATE"
        );
        $stmt->execute();
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        $sentCount = (int)($usage['sent_count'] ?? 0);
        if ($sentCount >= $limit) {
            $pdo->commit();
            error_log('[EmailJS] Monthly OTP quota reached; attempts=' . $sentCount . '; limit=' . $limit);
            throw new MailQuotaException('Email codes are unavailable until next month.');
        }

        if (!empty($usage['last_sent_at'])) {
            $lastSent = strtotime((string)$usage['last_sent_at']);
            if ($lastSent !== false && microtime(true) - $lastSent < 1.0) {
                $pdo->commit();
                throw new RuntimeException('EmailJS send rate limited to one request per second.');
            }
        }

        $update = $pdo->prepare(
            "UPDATE public.mail_usage
             SET sent_count = sent_count + 1, last_sent_at = CURRENT_TIMESTAMP
             WHERE month = to_char(CURRENT_DATE, 'YYYY-MM') AND provider = 'emailjs'
             RETURNING sent_count"
        );
        $update->execute();
        $count = (int)$update->fetchColumn();
        $pdo->commit();
        error_log('[EmailJS] OTP send attempt reserved; attempts=' . $count . '; limit=' . $limit);
        return ['count' => $count, 'limit' => $limit];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function mailUsageStatus(PDO $pdo): array
{
    $limit = emailJsMonthlyLimit();
    $stmt = $pdo->prepare(
        "SELECT sent_count FROM public.mail_usage
         WHERE month = to_char(CURRENT_DATE, 'YYYY-MM') AND provider = 'emailjs'"
    );
    $stmt->execute();
    $count = (int)($stmt->fetchColumn() ?: 0);
    return [
        'count' => $count,
        'limit' => $limit,
        'percent' => (int)floor(($count / $limit) * 100),
        'nearLimit' => $count >= (int)ceil($limit * 0.8),
        'exhausted' => $count >= $limit,
    ];
}

function sendLoginOtpEmail(
    string $toEmail,
    string $userName,
    string $code,
    int $expiresMinutes,
    ?PDO $pdo = null
): array {
    $config = skalingaConfig();
    $driver = strtolower(trim((string)($config['MAIL_DRIVER'] ?? 'emailjs')));
    if (strpbrk($toEmail, "\r\n") !== false || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid recipient email address is required.');
    }
    if (!preg_match('/^\d{6}$/', $code) || $expiresMinutes < 1 || $expiresMinutes > 60) {
        throw new InvalidArgumentException('Invalid login verification email parameters.');
    }

    if ($driver === 'log') {
        if (($config['APP_ENV'] ?? '') === 'production') {
            throw new RuntimeException('The log mail driver is forbidden in production.');
        }
        error_log('[Mail:log] Login OTP delivery is disabled; no verification code was logged or sent.');
        throw new RuntimeException('The local log mail driver cannot deliver login verification codes.');
    }
    if ($driver !== 'emailjs') {
        throw new RuntimeException('MAIL_DRIVER must be emailjs or log.');
    }
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is required to enforce the EmailJS monthly limit.');
    }

    reserveEmailOtpAttempt($pdo);
    $serviceId = trim((string)($config['EMAILJS_SERVICE_ID'] ?? ''));
    $templateId = trim((string)($config['EMAILJS_TEMPLATE_ID'] ?? ''));
    $publicKey = trim((string)($config['EMAILJS_PUBLIC_KEY'] ?? ''));
    $privateKey = trim((string)($config['EMAILJS_PRIVATE_KEY'] ?? ''));
    if ($serviceId === '' || $templateId === '' || $publicKey === '' || $privateKey === '') {
        throw new RuntimeException('EmailJS service, template, public, and private keys must be configured.');
    }
    if (strlen($userName) > 160) {
        $userName = substr($userName, 0, 160);
    }

    $payload = json_encode([
        'service_id' => $serviceId,
        'template_id' => $templateId,
        'user_id' => $publicKey,
        'accessToken' => $privateKey,
        'template_params' => [
            'to_email' => $toEmail,
            'user_name' => $userName,
            'otp_code' => $code,
            'expires_minutes' => $expiresMinutes,
        ],
    ], JSON_THROW_ON_ERROR);

    return mailHttpsPost(
        'https://api.emailjs.com/api/v1.0/email/send',
        ['Content-Type: application/json', 'Accept: application/json'],
        $payload,
        'EmailJS',
        [$privateKey, $publicKey, $serviceId, $templateId, $code]
    );
}

function mailHttpsPost(string $url, array $headers, string $payload, string $provider, array $redact = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to send email.');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Unable to initialize the ' . $provider . ' email request.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $responseBody = is_string($response) ? trim($response) : '';
    $safeResponse = mailRedactProviderText($responseBody, $redact);

    if (!is_string($response) || $status !== 200) {
        error_log('[' . $provider . '] HTTP status=' . $status
            . '; cURL error=' . mailRedactProviderText($curlError, $redact)
            . '; response=' . ($safeResponse !== '' ? $safeResponse : '[empty response]'));
        throw new MailDeliveryException(
            $provider . ' email delivery failed with HTTP ' . $status . '.',
            $status,
            $safeResponse
        );
    }

    error_log('[' . $provider . '] HTTP status=200; response=' . ($safeResponse !== '' ? $safeResponse : '[empty response]'));
    return ['provider' => $provider, 'status' => $status, 'response' => $safeResponse];
}

function mailRedactProviderText(string $text, array $sensitiveValues = []): string
{
    foreach ($sensitiveValues as $value) {
        if (is_string($value) && $value !== '') {
            $text = str_replace($value, '[REDACTED]', $text);
        }
    }
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return substr($text, 0, 2000);
}
