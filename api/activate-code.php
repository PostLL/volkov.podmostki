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
$deviceFingerprint = $_POST['device_fingerprint'] ?? ($payload['device_fingerprint'] ?? '');
$deviceId = normalize_device_id($deviceId);
$deviceFingerprint = normalize_device_fingerprint($deviceFingerprint);

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

$status = get_code_status_payload($code, $shows, $deviceId, $deviceFingerprint);
$accessSeconds = get_access_seconds();

if (in_array($status['status'], ['invalid', 'expired', 'device_mismatch'], true)) {
    http_response_code(422);
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

$existingActivatedAt = isset($activations[$normalizedCode]['activated_at'])
    ? (int) $activations[$normalizedCode]['activated_at']
    : 0;

// Если код уже активен и мы просто перепривязываем тот же девайс по fingerprint,
// не продлеваем 10 часов, сохраняем исходное activated_at.
$effectiveActivatedAt = $existingActivatedAt > 0 ? $existingActivatedAt : $now;

$activations[$normalizedCode] = [
    'show' => $show,
    'device_id' => $deviceId,
    'device_fingerprint' => $deviceFingerprint,
    'activated_at' => $effectiveActivatedAt,
    'created_at' => $existingCreatedAt,
    'updated_at' => $now
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
    'activated_at' => $effectiveActivatedAt,
    'expires_at' => $effectiveActivatedAt + $accessSeconds,
    'remaining_seconds' => max(0, ($effectiveActivatedAt + $accessSeconds) - $now)
]);
