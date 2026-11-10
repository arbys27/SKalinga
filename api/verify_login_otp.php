<?php

header('Content-Type: application/json; charset=utf-8');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'SMS login verification is retired. Sign in again to receive an email code.'
]);
