<?php

/**
 * Настройки бизнес-логики кодов.
 */
function get_access_seconds(): int
{
    // Срок доступа к спектаклю после активации (по умолчанию 10 часов).
    return 10 * 60 * 60;
}

function get_cleanup_retention_days(): int
{
    // Через сколько дней после истечения доступа удаляем активации.
    return 14;
}

/**
 * Разрешённые спецсимволы в коде.
 */
function get_allowed_special_chars(): array
{
    return ['-', '_', '!'];
}

/**
 * Нормализация кода билета к единому виду.
 */
function normalize_code(string $code): string
{
    return strtoupper(trim($code));
}

/**
 * Нормализация одноразового идентификатора сессии устройства.
 */
function normalize_device_id(string $deviceId): string
{
    return trim($deviceId);
}

/**
 * Нормализация «мягкого» отпечатка устройства.
 * Используется как fallback для инкогнито/очистки localStorage.
 */
function normalize_device_fingerprint(string $fingerprint): string
{
    return trim($fingerprint);
}

/**
 * Проверка формата кода:
 * - 1-й символ: префикс спектакля (латинская заглавная буква)
 * - остальные 5 символов содержат: 2 заглавные латинские буквы, 2 цифры, 1 спецсимвол.
 */
function is_code_format_valid(string $code): bool
{
    if (strlen($code) !== 6) {
        return false;
    }

    if (!preg_match('/^[A-Z]/', $code)) {
        return false;
    }

    $suffix = substr($code, 1);
    return is_ticket_suffix_valid($suffix);
}

/**
 * Проверка правил суффикса: 2 буквы + 2 цифры + 1 спецсимвол.
 */
function is_ticket_suffix_valid(string $suffix): bool
{
    if (strlen($suffix) !== 5) {
        return false;
    }

    $letters = 0;
    $digits = 0;
    $specials = 0;
    $specialSet = get_allowed_special_chars();

    for ($i = 0; $i < strlen($suffix); $i += 1) {
        $ch = $suffix[$i];
        if (preg_match('/[A-Z]/', $ch)) {
            $letters += 1;
            continue;
        }

        if (preg_match('/[0-9]/', $ch)) {
            $digits += 1;
            continue;
        }

        if (in_array($ch, $specialSet, true)) {
            $specials += 1;
            continue;
        }

        return false;
    }

    return $letters === 2 && $digits === 2 && $specials === 1;
}

/**
 * Определяет спектакль по префиксу кода через shows.php.
 */
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

/**
 * Возвращает первый префикс для спектакля (используется генератором кодов).
 */
function get_show_primary_prefix(string $showSlug, array $shows): ?string
{
    if (!isset($shows[$showSlug])) {
        return null;
    }

    $prefixes = $shows[$showSlug]['code_prefixes'] ?? [];
    if (!is_array($prefixes) || empty($prefixes)) {
        return null;
    }

    return strtoupper((string) $prefixes[0]);
}

/**
 * Генерация кода билета.
 * Формат: PREFIX + (2 буквы + 2 цифры + 1 спецсимвол в случайном порядке).
 */
function generate_ticket_code(string $prefix, array $issued): string
{
    $lettersPool = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digitsPool = '0123456789';
    $specials = get_allowed_special_chars();

    do {
        $parts = [];

        // 2 латинские заглавные буквы
        for ($i = 0; $i < 2; $i += 1) {
            $parts[] = $lettersPool[random_int(0, strlen($lettersPool) - 1)];
        }

        // 2 цифры
        for ($i = 0; $i < 2; $i += 1) {
            $parts[] = $digitsPool[random_int(0, strlen($digitsPool) - 1)];
        }

        // 1 спецсимвол
        $parts[] = $specials[random_int(0, count($specials) - 1)];

        // Перемешиваем порядок суффикса
        shuffle($parts);

        $code = strtoupper($prefix) . implode('', $parts);
    } while (isset($issued[$code]));

    return $code;
}

/**
 * Путь к хранилищу активаций.
 */
function get_activation_file_path(): string
{
    return __DIR__ . '/../storage/code-activations.json';
}

/**
 * Путь к хранилищу выпущенных кодов.
 */
function get_issued_codes_file_path(): string
{
    return __DIR__ . '/../storage/issued-codes.json';
}

/**
 * Универсальное чтение JSON-файла в массив.
 */
function read_json_array_file(string $path): array
{
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

/**
 * Универсальная запись JSON-файла с блокировкой.
 */
function write_json_array_file(string $path, array $data): bool
{
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

/**
 * Прочитать карту активаций кодов.
 */
function read_activation_data(): array
{
    return read_json_array_file(get_activation_file_path());
}

/**
 * Сохранить карту активаций кодов.
 */
function write_activation_data(array $data): bool
{
    return write_json_array_file(get_activation_file_path(), $data);
}

/**
 * Прочитать список выпущенных кодов.
 */
function read_issued_codes(): array
{
    return read_json_array_file(get_issued_codes_file_path());
}

/**
 * Сохранить список выпущенных кодов.
 */
function write_issued_codes(array $data): bool
{
    return write_json_array_file(get_issued_codes_file_path(), $data);
}

/**
 * В продуктиве строго работаем только по списку выпущенных кодов.
 */
function is_issued_codes_enforced(array $issued): bool
{
    return true;
}

/**
 * Очистка старых активаций:
 * удаляем записи, истекшие более N дней назад.
 */
function prune_old_activations(array $activations): array
{
    $now = time();
    $keep = [];

    foreach ($activations as $code => $entry) {
        $activatedAt = (int) ($entry['activated_at'] ?? 0);
        if ($activatedAt <= 0) {
            continue;
        }

        $deleteAfter = $activatedAt + get_access_seconds() + get_cleanup_retention_days() * 24 * 60 * 60;
        if ($now < $deleteAfter) {
            $keep[$code] = $entry;
        }
    }

    return $keep;
}

/**
 * Выполнить и сохранить очистку активаций.
 */
function run_cleanup_activations(): array
{
    $before = read_activation_data();
    $after = prune_old_activations($before);

    $saved = write_activation_data($after);

    return [
        'ok' => $saved,
        'before' => count($before),
        'after' => count($after),
        'deleted' => count($before) - count($after)
    ];
}

/**
 * Центральная проверка статуса кода.
 */
function get_code_status_payload(
    string $rawCode,
    array $shows,
    string $rawDeviceId = '',
    string $rawDeviceFingerprint = ''
): array {
    $code = normalize_code($rawCode);
    $deviceId = normalize_device_id($rawDeviceId);
    $deviceFingerprint = normalize_device_fingerprint($rawDeviceFingerprint);

    if (!is_code_format_valid($code)) {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Неверный формат кода.',
            'code' => $code
        ];
    }

    $showByPrefix = map_code_to_show($code, $shows);
    if ($showByPrefix === null) {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Код не найден.',
            'code' => $code
        ];
    }

    $issued = read_issued_codes();
    $issuedEnforced = is_issued_codes_enforced($issued);

    if ($issuedEnforced && !isset($issued[$code])) {
        return [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Код не найден в списке выпущенных.',
            'code' => $code
        ];
    }

    $show = $showByPrefix;
    if ($issuedEnforced) {
        $show = $issued[$code]['show'] ?? $showByPrefix;
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
    $boundFingerprint = (string) ($entry['device_fingerprint'] ?? '');

    // Для старых активаций без device_id требуем повторную привязку.
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

    // Основная проверка: device_id.
    // Fallback для инкогнито/очистки localStorage: разрешаем тот же браузер/устройство
    // по совпавшему fingerprint, даже если device_id новый.
    $deviceMatch = ($boundDeviceId === $deviceId);
    $fingerprintMatch = ($boundFingerprint !== '' && $deviceFingerprint !== '' && $boundFingerprint === $deviceFingerprint);

    if (!$deviceMatch && !$fingerprintMatch) {
        return [
            'ok' => false,
            'status' => 'device_mismatch',
            'message' => 'Этот билет уже используется на другом устройстве.',
            'show' => $show,
            'code' => $code
        ];
    }

    $activatedAt = (int) $entry['activated_at'];
    $expiresAt = $activatedAt + get_access_seconds();

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
        'remaining_seconds' => $expiresAt - time(),
        'rebind_required' => !$deviceMatch && $fingerprintMatch
    ];
}
