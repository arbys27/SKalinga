<?php

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../security_helpers.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/mail.php';
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$auth = requireAdminApi();
if ($auth['role'] !== 'admin' || ($_SESSION['admin_role'] ?? '') !== 'superadmin') {
    authJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
}
securityRequireCsrf();

$admin = $pdo->prepare('SELECT email, username FROM admins WHERE id = ? AND status = \'active\'');
$admin->execute([$auth['id']]);
$account = $admin->fetch(PDO::FETCH_ASSOC);
$email = trim((string)($account['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpbrk($email, "\r\n") !== false) {
    authJsonResponse(['success' => false, 'message' => 'Your administrator account needs a valid email address.'], 400);
}

$code = otpGenerateCode();
try {
    $result = sendLoginOtpEmail(
        $email,
        (string)($account['username'] ?? 'SKalinga administrator'),
        $code,
        10,
        $pdo
    );
    error_log('[EmailJS Test] Delivery succeeded for superadmin id=' . $auth['id'] . '.');
    authJsonResponse([
        'success' => true,
        'message' => 'Test email sent to ' . otpMaskEmail($email) . '.',
        'provider' => $result['provider'],
        'status' => $result['status'],
        'response' => $result['response'],
        'usage' => mailUsageStatus($pdo)
    ]);
} catch (Throwable $exception) {
    $reference = securityLogException('EmailJS Test Error', $exception, [
        $code,
        $email,
        (string)(skalingaConfig()['EMAILJS_PRIVATE_KEY'] ?? ''),
        (string)(skalingaConfig()['EMAILJS_PUBLIC_KEY'] ?? '')
    ]);
    $message = $exception instanceof MailQuotaException
        ? 'Email codes are unavailable until next month. Please contact the SK Office.'
        : 'Test email failed. Reference: ' . $reference;
    $response = $exception instanceof MailDeliveryException
        ? ['provider' => 'EmailJS', 'status' => $exception->httpStatus, 'response' => $exception->providerResponse]
        : null;
    authJsonResponse([
        'success' => false,
        'message' => $message,
        'reference' => $reference,
        'provider_result' => $response,
        'usage' => mailUsageStatus($pdo)
    ], $exception instanceof MailQuotaException ? 503 : 502);
}
