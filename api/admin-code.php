<?php
/**
 * Простейший административный API для управления кодами/активациями.
 *
 * ВАЖНО: перед использованием задайте ADMIN_API_KEY в этом файле
 * или через переменную окружения PODMOSTKI_ADMIN_KEY.
 */
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/env.php';
$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/code-lib.php';

$ADMIN_API_KEY = getenv('PODMOSTKI_ADMIN_KEY') ?: 'CHANGE_ME_NOW';
$providedKey = $_GET['key'] ?? ($_POST['key'] ?? '');

if ($ADMIN_API_KEY === 'CHANGE_ME_NOW' || $providedKey !== $ADMIN_API_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$code = normalize_code($_GET['code'] ?? ($_POST['code'] ?? ''));
$show = $_GET['show'] ?? ($_POST['show'] ?? '');

$issued = read_issued_codes();
$activations = read_activation_data();

if ($action === 'add_code') {
    if ($code === '' || $show === '' || !isset($shows[$show])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Нужны code и valid show']);
        exit;
    }

    if (!is_code_format_valid($code)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Неверный формат кода']);
        exit;
    }

    if (isset($issued[$code])) {
        echo json_encode(['ok' => false, 'message' => 'Такой код уже существует', 'code' => $code, 'show' => $issued[$code]['show'] ?? null]);
        exit;
    }

    $issued[$code] = [
        'show' => $show,
        'created_at' => time(),
        'source' => 'manual'
    ];

    echo json_encode(['ok' => write_issued_codes($issued), 'message' => 'Код добавлен', 'code' => $code, 'show' => $show]);
    exit;
}

if ($action === 'generate_code') {
    if ($show === '' || !isset($shows[$show])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Нужен valid show']);
        exit;
    }

    $prefix = get_show_primary_prefix($show, $shows);
    if ($prefix === null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Для спектакля не задан префикс']);
        exit;
    }

    $newCode = generate_ticket_code($prefix, $issued);
    $issued[$newCode] = [
        'show' => $show,
        'created_at' => time(),
        'source' => 'generated'
    ];

    echo json_encode([
        'ok' => write_issued_codes($issued),
        'message' => 'Код сгенерирован',
        'code' => $newCode,
        'show' => $show,
        'prefix' => $prefix
    ]);
    exit;
}

if ($action === 'reset_device') {
    if ($code === '' || !isset($activations[$code])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Код не найден в активациях']);
        exit;
    }

    $activations[$code]['device_id'] = '';
    $activations[$code]['device_fingerprint'] = '';
    $activations[$code]['updated_at'] = time();

    echo json_encode(['ok' => write_activation_data($activations), 'message' => 'Привязка к устройству сброшена', 'code' => $code]);
    exit;
}

if ($action === 'reset_activation') {
    if ($code === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Нужен code']);
        exit;
    }

    unset($activations[$code]);
    echo json_encode(['ok' => write_activation_data($activations), 'message' => 'Активация удалена', 'code' => $code]);
    exit;
}

if ($action === 'reset_time') {
    if ($code === '' || !isset($activations[$code])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Код не найден в активациях']);
        exit;
    }

    $activations[$code]['activated_at'] = time();
    $activations[$code]['updated_at'] = time();

    $hours = round(get_access_seconds() / 3600, 2);
    echo json_encode(['ok' => write_activation_data($activations), 'message' => "Время доступа обновлено (ещё {$hours} ч)", 'code' => $code]);
    exit;
}

if ($action === 'delete_code') {
    if ($code === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Нужен code']);
        exit;
    }

    unset($issued[$code], $activations[$code]);
    $ok1 = write_issued_codes($issued);
    $ok2 = write_activation_data($activations);

    echo json_encode(['ok' => ($ok1 && $ok2), 'message' => 'Код удалён', 'code' => $code]);
    exit;
}

if ($action === 'status') {
    if ($code === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Нужен code']);
        exit;
    }

    $issuedEntry = $issued[$code] ?? null;
    $activationEntry = $activations[$code] ?? null;
    $showByPrefix = map_code_to_show($code, $shows);

    if (!is_code_format_valid($code) || $showByPrefix === null) {
        $statusPayload = [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Код некорректный или не привязан к спектаклю.',
            'code' => $code
        ];
    } elseif ($issuedEntry === null) {
        $statusPayload = [
            'ok' => false,
            'status' => 'invalid',
            'message' => 'Код не найден в списке выпущенных.',
            'code' => $code,
            'show' => $showByPrefix
        ];
    } elseif ($activationEntry === null || empty($activationEntry['activated_at'])) {
        $statusPayload = [
            'ok' => true,
            'status' => 'not_activated',
            'message' => 'Код найден, но ещё не активирован.',
            'code' => $code,
            'show' => $issuedEntry['show'] ?? $showByPrefix,
            'issued' => $issuedEntry
        ];
    } else {
        $activatedAt = (int) ($activationEntry['activated_at'] ?? 0);
        $expiresAt = $activatedAt + get_access_seconds();
        $isExpired = time() >= $expiresAt;

        $statusPayload = [
            'ok' => !$isExpired,
            'status' => $isExpired ? 'expired' : 'active',
            'message' => $isExpired ? 'Код активирован, но срок доступа истёк.' : 'Код активирован и действителен.',
            'code' => $code,
            'show' => $issuedEntry['show'] ?? $showByPrefix,
            'activated_at' => $activatedAt,
            'expires_at' => $expiresAt,
            'remaining_seconds' => max(0, $expiresAt - time()),
            'activation' => $activationEntry,
            'issued' => $issuedEntry
        ];
    }

    echo json_encode(['ok' => true, 'status_payload' => $statusPayload]);
    exit;
}

if ($action === 'run_cleanup') {
    $result = run_cleanup_activations();
    echo json_encode(['ok' => $result['ok'], 'message' => 'Очистка выполнена', 'stats' => $result]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Неизвестное action']);
