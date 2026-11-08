<?php

declare(strict_types=1);

function isHttps(): bool
{
    if (isset($_SERVER['HTTPS'])
        && in_array(strtolower((string)$_SERVER['HTTPS']), ['on', '1', 'https'], true)) {
        return true;
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $isLocalProxy = $remoteAddr === '127.0.0.1' || $remoteAddr === '::1';
    $isRailwayProxy = getenv('RAILWAY_ENVIRONMENT_NAME') !== false
        || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
    $isRenderProxy = strtolower((string)getenv('RENDER')) === 'true'
        || getenv('RENDER_EXTERNAL_HOSTNAME') !== false;
    if (!$isLocalProxy && !$isRailwayProxy && !$isRenderProxy) {
        return false;
    }

    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($isLocalProxy && $forwardedProto === '') {
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] ?? '';
    }
    if (is_string($forwardedProto) && $forwardedProto !== '') {
        $proto = strtolower(trim(explode(',', $forwardedProto)[0]));
        if ($proto === 'https') {
            return true;
        }
        if ($proto === 'http') {
            return false;
        }
    }

    $cfVisitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';
    if ($isLocalProxy && is_string($cfVisitor) && $cfVisitor !== '') {
        $decoded = json_decode($cfVisitor, true);
        if (is_array($decoded) && isset($decoded['scheme'])) {
            return strtolower((string)$decoded['scheme']) === 'https';
        }
    }

    return false;
}

function clientIp(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remoteAddr) || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }

    $isLocalProxy = $remoteAddr === '127.0.0.1' || $remoteAddr === '::1';
    if (!$isLocalProxy) {
        $isRailwayProxy = getenv('RAILWAY_ENVIRONMENT_NAME') !== false
            || getenv('RAILWAY_PUBLIC_DOMAIN') !== false;
        $isRenderProxy = strtolower((string)getenv('RENDER')) === 'true'
            || getenv('RENDER_EXTERNAL_HOSTNAME') !== false;
        if ($isRailwayProxy || $isRenderProxy) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (is_string($forwardedFor)) {
                foreach (array_map('trim', explode(',', $forwardedFor)) as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                        return $candidate;
                    }
                }
            }
        }
        return $remoteAddr;
    }

    $forwardedIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($forwardedIp) && filter_var(trim($forwardedIp), FILTER_VALIDATE_IP) !== false) {
        return trim($forwardedIp);
    }

    return is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : 'unknown';
}

function secureSessionStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        enforceOtpSessionWindow();
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    enforceOtpSessionWindow();
}

function enforceOtpSessionWindow(): void
{
    $hasAuthenticatedIdentity = isset($_SESSION['auth_user_id'], $_SESSION['auth_role'])
        || !empty($_SESSION['authenticated'])
        || !empty($_SESSION['admin_authenticated']);
    if (!$hasAuthenticatedIdentity) {
        return;
    }

    $verifiedAt = filter_var($_SESSION['otp_verified_at'] ?? null, FILTER_VALIDATE_INT);
    if ($verifiedAt !== false && $verifiedAt > 0 && time() - $verifiedAt <= 1800) {
        return;
    }

    if (isset($_SESSION['auth_user_id'], $_SESSION['auth_role'])) {
        $_SESSION['otp_reauth_user_id'] = (int)$_SESSION['auth_user_id'];
        $_SESSION['otp_reauth_role'] = (string)$_SESSION['auth_role'];
        $_SESSION['otp_reauth_identifier'] = (string)($_SESSION['auth_identifier'] ?? '');
        $_SESSION['otp_reauth_name'] = (string)($_SESSION['auth_name'] ?? '');
        $_SESSION['otp_reauth_member_id'] = $_SESSION['member_id'] ?? null;
    } elseif (!empty($_SESSION['admin_authenticated']) && !empty($_SESSION['admin_id'])) {
        $_SESSION['otp_reauth_user_id'] = (int)$_SESSION['admin_id'];
        $_SESSION['otp_reauth_role'] = ($_SESSION['admin_role'] ?? '') === 'superadmin' ? 'admin' : 'sk_official';
        $_SESSION['otp_reauth_identifier'] = (string)($_SESSION['admin_username'] ?? '');
        $_SESSION['otp_reauth_name'] = (string)($_SESSION['admin_username'] ?? '');
        $_SESSION['otp_reauth_member_id'] = null;
    } elseif (!empty($_SESSION['authenticated']) && !empty($_SESSION['user_id'])) {
        $_SESSION['otp_reauth_user_id'] = (int)$_SESSION['user_id'];
        $_SESSION['otp_reauth_role'] = 'youth';
        $_SESSION['otp_reauth_identifier'] = '';
        $_SESSION['otp_reauth_name'] = '';
        $_SESSION['otp_reauth_member_id'] = $_SESSION['member_id'] ?? null;
    }

    foreach ([
        'auth_user_id',
        'auth_role',
        'auth_identifier',
        'auth_name',
        'member_id',
        'user_id',
        'authenticated',
        'admin_authenticated',
        'admin_id',
        'admin_username',
        'admin_email',
        'admin_role',
        'otp_verified_at',
    ] as $key) {
        unset($_SESSION[$key]);
    }
    $_SESSION['otp_reauth_required'] = true;
}
