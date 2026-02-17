<?php
header('Content-Type: application/json; charset=utf-8');

$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/code-lib.php';

$payload = [];
$json = json_decode(file_get_contents('php://input'), true);
if (is_array($json)) {
    $payload = $json;
}

$code = $_POST['code'] ?? ($payload['code'] ?? '');
$deviceId = $_POST['device_id'] ?? ($payload['device_id'] ?? '');
$deviceId = normalize_device_id($deviceId);

if ($code === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'status' => 'invalid', 'message' => 'Код не передан.']);
    exit;
}

if ($deviceId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'status' => 'invalid', 'message' => 'Идентификатор устройства не передан.']);
    exit;
}

$status = get_code_status_payload($code, $shows, $deviceId);

if (in_array($status['status'], ['invalid', 'expired', 'device_mismatch'], true)) {
    http_response_code(422);
    echo json_encode($status);
    exit;
}

if ($status['status'] === 'active') {
    echo json_encode($status);
    exit;
}

$normalizedCode = $status['code'];
$show = $status['show'];
$now = time();

$activations = read_activation_data();
$existingCreatedAt = isset($activations[$normalizedCode]['created_at'])
    ? (int) $activations[$normalizedCode]['created_at']
    : $now;

$activations[$normalizedCode] = [
    'show' => $show,
    'device_id' => $deviceId,
    'activated_at' => $now,
    'created_at' => $existingCreatedAt
];

if (!write_activation_data($activations)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 'storage_error',
        'message' => 'Не удалось сохранить активацию. Проверьте права на запись в папку storage.'
    ]);
    exit;
}

$saved = read_activation_data();
$savedDeviceId = $saved[$normalizedCode]['device_id'] ?? null;
if ($savedDeviceId !== $deviceId) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 'storage_error',
        'message' => 'Активация не зафиксирована. Проверьте права на запись в storage.'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'status' => 'active',
    'message' => 'Код успешно активирован.',
    'show' => $show,
    'code' => $normalizedCode,
    'activated_at' => $now,
    'expires_at' => $now + 10 * 60 * 60,
    'remaining_seconds' => 10 * 60 * 60
]);