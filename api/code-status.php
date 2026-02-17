<?php
header('Content-Type: application/json; charset=utf-8');

$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/code-lib.php';

$code = $_GET['code'] ?? '';
$deviceId = $_GET['device_id'] ?? '';

if ($code === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'status' => 'invalid', 'message' => 'Код не передан.']);
    exit;
}

$result = get_code_status_payload($code, $shows, $deviceId);

echo json_encode($result);