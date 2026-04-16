<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';
$shows = require __DIR__ . '/shows.php';

$adminKey = getenv('PODMOSTKI_ADMIN_KEY') ?: 'CHANGE_ME_NOW';
$providedKey = $_GET['key'] ?? ($_POST['key'] ?? '');
if ($adminKey === 'CHANGE_ME_NOW' || $providedKey !== $adminKey) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$settingsPath = __DIR__ . '/../storage/settings.json';
$showsPath = __DIR__ . '/../storage/shows.json';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');

$settings = [];
if (db_is_enabled()) {
    try {
        $settings = db_get_settings();
    } catch (Throwable $e) {
        $settings = [];
    }
} elseif (file_exists($settingsPath)) {
    $raw = file_get_contents($settingsPath);
    $decoded = json_decode($raw ?: '[]', true);
    if (is_array($decoded)) {
        $settings = $decoded;
    }
}

if ($action === 'get') {
    echo json_encode([
        'ok' => true,
        'cash_mode' => ($settings['cash_mode'] ?? 'test'),
        'shows' => $shows
    ]);
    exit;
}

if ($action === 'set_cash_mode') {
    $mode = ($_GET['mode'] ?? ($_POST['mode'] ?? 'test')) === 'live' ? 'live' : 'test';

    if (db_is_enabled()) {
        $saved = db_set_setting('cash_mode', $mode);
    } else {
        $settings['cash_mode'] = $mode;
        $saved = file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    }

    echo json_encode(['ok' => $saved, 'cash_mode' => $mode]);
    exit;
}

if ($action === 'toggle_show') {
    $slug = (string) ($_GET['show'] ?? ($_POST['show'] ?? ''));
    $enabled = (($_GET['enabled'] ?? ($_POST['enabled'] ?? '1')) === '1');

    if ($slug === '' || !isset($shows[$slug])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Show not found']);
        exit;
    }

    $shows[$slug]['enabled'] = $enabled;

    if (db_is_enabled()) {
        $saved = db_upsert_show($slug, $shows[$slug]);
    } else {
        $saved = file_put_contents($showsPath, json_encode($shows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    }

    echo json_encode(['ok' => $saved, 'show' => $slug, 'enabled' => $enabled]);
    exit;
}

http_response_code(422);
echo json_encode(['ok' => false, 'message' => 'Unknown action']);
