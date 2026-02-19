<?php
// Ultra-simple debug - just log what arrives
error_log('==================== DEBUG_UPLOAD ====================');
error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'NOT SET'));
error_log('Content-Length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'NOT SET'));
error_log('Files received: ' . json_encode($_FILES, JSON_PRETTY_PRINT));

// Count files
$photo_count = 0;
if (isset($_FILES['photo'])) {
    if (is_array($_FILES['photo']['name'])) {
        $photo_count = count($_FILES['photo']['name']);
        error_log('MULTIPLE FILES: ' . $photo_count . ' files');
        foreach ($_FILES['photo']['name'] as $i => $name) {
            error_log('  File ' . ($i+1) . ': ' . $name . ' (error: ' . $_FILES['photo']['error'][$i] . ')');
        }
    } else {
        $photo_count = 1;
        error_log('SINGLE FILE: ' . $_FILES['photo']['name'] . ' (error: ' . ($_FILES['photo']['error'] ?? 'N/A') . ')');
    }
}
error_log('=====================================================');

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Debug: ' . $photo_count . ' files received',
    'photo_count' => $photo_count,
    'photo_name_is_array' => is_array($_FILES['photo']['name'] ?? null)
]);
?>
