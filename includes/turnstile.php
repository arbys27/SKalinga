<?php

declare(strict_types=1);

require_once __DIR__ . '/request.php';

function turnstileConfig(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }

    $local = [];
    $localPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (!is_array($local)) {
            throw new RuntimeException('Local configuration must return an array.');
        }
    }

    $dotenv = [];
    $dotenvPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($dotenvPath)) {
        $parsed = parse_ini_file($dotenvPath, false, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            throw new RuntimeException('Unable to parse the .env configuration file.');
        }
        $dotenv = $parsed;
    }

    $read = static function (string $name, string $default = '') use ($local, $dotenv): string {
        $environmentValue = getenv($name);
        if ($environmentValue !== false && $environmentValue !== '') {
            return $environmentValue;
        }

        $localValue = $local[$name] ?? ($dotenv[$name] ?? $default);
        return is_scalar($localValue) ? (string)$localValue : $default;
    };

    $appEnvironment = $read('APP_ENV', '');
    $enabledValue = $read('TURNSTILE_ENABLED', $appEnvironment === 'production' ? 'true' : 'false');
    $failOpenValue = $read('TURNSTILE_FAIL_OPEN', 'false');
    $enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOLEAN);
    if ($appEnvironment === 'production' && !$enabled) {
        error_log('[Turnstile] Ignoring TURNSTILE_ENABLED=false in production.');
        $enabled = true;
    }

    $config = [
        'site_key' => $read('TURNSTILE_SITE_KEY'),
        'secret_key' => $read('TURNSTILE_SECRET_KEY'),
        'enabled' => $enabled,
        'fail_open' => filter_var($failOpenValue, FILTER_VALIDATE_BOOLEAN),
    ];

    return $config;
}

function verifyTurnstile(string $token, string $expectedAction): array
{
    $config = turnstileConfig();
    if (!$config['enabled']) {
        return ['success' => true, 'message' => ''];
    }

    if ($token === '' || strlen($token) > 2048) {
        error_log('[Turnstile] Rejected request: missing, empty, or oversized token.');
        return ['success' => false, 'message' => 'Please complete the verification and try again.'];
    }

    if (!function_exists('curl_init') || $config['secret_key'] === '') {
        return turnstileUnavailable('cURL or TURNSTILE_SECRET_KEY is not configured.', $config['fail_open']);
    }

    $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($curl === false) {
        return turnstileUnavailable('Unable to initialize the verification request.', $config['fail_open']);
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $config['secret_key'],
            'response' => $token,
            'remoteip' => clientIp(),
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response = curl_exec($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($response) || $httpStatus < 200 || $httpStatus >= 300) {
        return turnstileUnavailable(
            'Siteverify request failed; HTTP ' . $httpStatus . '; ' . $curlError,
            $config['fail_open']
        );
    }

    $result = json_decode($response, true);
    if (!is_array($result)) {
        return turnstileUnavailable('Siteverify returned invalid JSON.', $config['fail_open']);
    }

    if (($result['success'] ?? false) !== true) {
        $codes = $result['error-codes'] ?? [];
        $codes = is_array($codes) ? array_map('strval', $codes) : [];
        error_log('[Turnstile] Verification rejected: ' . implode(', ', $codes));
        return [
            'success' => false,
            'message' => turnstileErrorMessage($codes),
        ];
    }

    $hostname = strtolower(rtrim((string)($result['hostname'] ?? ''), '.'));
    if (!turnstileAllowedHostname($hostname)) {
        error_log('[Turnstile] Verification rejected: unexpected hostname ' . $hostname);
        return ['success' => false, 'message' => 'Verification failed. Please try again.'];
    }

    if (!isset($result['action']) || !is_string($result['action']) || !hash_equals($expectedAction, $result['action'])) {
        error_log('[Turnstile] Verification rejected: action did not match ' . $expectedAction . '.');
        return ['success' => false, 'message' => 'Verification failed. Please try again.'];
    }

    return ['success' => true, 'message' => ''];
}

function turnstileAllowedHostname(string $hostname): bool
{
    if ($hostname === 'skalinga1.onrender.com') {
        return true;
    }

    $requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? '';
    return in_array($hostname, ['localhost', '127.0.0.1', '::1'], true)
        && in_array($requestHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

function turnstileErrorMessage(array $codes): string
{
    if (in_array('timeout-or-duplicate', $codes, true)) {
        return 'Verification expired. Please try again.';
    }
    if (in_array('invalid-input-response', $codes, true)) {
        return 'Verification could not be confirmed. Please try again.';
    }
    if (in_array('missing-input-response', $codes, true)) {
        return 'Please complete the verification and try again.';
    }

    return 'Verification failed. Please try again.';
}

function turnstileUnavailable(string $reason, bool $failOpen): array
{
    error_log('[Turnstile] Verification service unavailable: ' . $reason);
    if ($failOpen) {
        error_log('[Turnstile] TURNSTILE_FAIL_OPEN is enabled; allowing request.');
        return ['success' => true, 'message' => ''];
    }

    return [
        'success' => false,
        'message' => 'Verification service is unavailable. Please try again.',
    ];
}
