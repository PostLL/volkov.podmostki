<?php

function normalize_code(string $code): string
{
    return strtoupper(trim($code));
}

function normalize_device_id(string $deviceId): string
{
    return trim($deviceId);
}

function is_code_format_valid(string $code): bool
{
    return (bool) preg_match('/^[A-Z][A-Z0-9]{5}$/', $code);
}

function map_code_to_show(string $code, array $shows): ?string
{
    $prefix = substr($code, 0, 1);

    foreach ($shows as $slug => $meta) {
        $prefixes = $meta['code_prefixes'] ?? [];
        if (in_array($prefix, $prefixes, true)) {
            return $slug;
        }
    }

    return null;
}

function get_activation_file_path(): string
{
    return __DIR__ . '/../storage/code-activations.json';
}

function read_activation_data(): array
{
    $path = get_activation_file_path();
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function write_activation_data(array $data): bool
{
    $path = get_activation_file_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function get_code_status_payload(string $rawCode, array $shows, string $rawDeviceId = ''): array
{
    $code = normalize_code($rawCode);
    $deviceId = normalize_device_id($rawDeviceId);

    if (!is_code_format_valid($code)) {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Неверный формат кода.',
            'code' => $code
        ];
    }

    $show = map_code_to_show($code, $shows);
    if ($show === null) {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Код не найден.',
            'code' => $code
        ];
    }

    $activations = read_activation_data();
    $entry = $activations[$code] ?? null;

    if (!$entry || empty($entry['activated_at'])) {
        return [
            'ok' => true,
            'status' => 'not_activated',
            'message' => 'Код не активирован.',
            'show' => $show,
            'code' => $code
        ];
    }

    $boundDeviceId = (string) ($entry['device_id'] ?? '');

    // Для старых записей без device_id: требуем одноразовую повторную активацию,
    // чтобы привязать код к первому устройству и закрыть многодевайсный доступ.
    if ($boundDeviceId === '') {
        return [
            'ok' => true,
            'status' => 'not_activated',
            'message' => 'Код требует привязки к устройству. Нажмите «Активировать».',
            'show' => $show,
            'code' => $code
        ];
    }

    if ($deviceId === '') {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Идентификатор устройства не передан.',
            'show' => $show,
            'code' => $code
        ];
    }

    if ($boundDeviceId !== $deviceId) {
        return [
            'ok' => false,
            'status' => 'device_mismatch',
            'message' => 'Этот билет уже используется на другом устройстве.',
            'show' => $show,
            'code' => $code
        ];
    }

    $activatedAt = (int) $entry['activated_at'];
    $expiresAt = $activatedAt + 10 * 60 * 60;

    if (time() >= $expiresAt) {
        return [
            'ok' => false,
            'status' => 'expired',
            'message' => 'Срок доступа по коду истёк.',
            'show' => $show,
            'code' => $code,
            'expires_at' => $expiresAt
        ];
    }

    return [
        'ok' => true,
        'status' => 'active',
        'message' => 'Код активен.',
        'show' => $show,
        'code' => $code,
        'activated_at' => $activatedAt,
        'expires_at' => $expiresAt,
        'remaining_seconds' => $expiresAt - time()
    ];
}