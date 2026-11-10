<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'SMS codes are only available for youth account activation.'
]);
