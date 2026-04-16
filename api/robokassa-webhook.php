<?php
/**
 * Минимальный webhook-обработчик Робокассы.
 *
 * Как использовать:
 * 1) В Робокассе укажите Result URL -> https://site/api/robokassa-webhook.php
 * 2) Настройте секрет ROBOKASSA_PASSWORD2 (в env или в этом файле).
 * 3) Передавайте в платеж форму дополнительные поля:
 *    - Shp_show (slug спектакля)
 *    - Shp_qty  (кол-во кодов)
 *
 * На успешный вызов мы генерируем коды и кладём их в storage/issued-codes.json.
 */
header('Content-Type: text/plain; charset=utf-8');

$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/code-lib.php';
require __DIR__ . '/robokassa-config.php';

$isTest = ((string) ($_REQUEST['IsTest'] ?? $_REQUEST['is_test'] ?? '')) === '1';
$password2 = robokassa_get_password2($isTest);

$outSum = $_REQUEST['OutSum'] ?? $_REQUEST['out_sum'] ?? '';
$invId = $_REQUEST['InvId'] ?? $_REQUEST['InvoiceID'] ?? $_REQUEST['inv_id'] ?? '';
$signature = strtolower($_REQUEST['SignatureValue'] ?? $_REQUEST['signaturevalue'] ?? '');
$show = $_REQUEST['Shp_show'] ?? $_REQUEST['shp_show'] ?? 'volkov-golos';
$qty = (int) ($_REQUEST['Shp_qty'] ?? $_REQUEST['shp_qty'] ?? 1);

if (robokassa_is_placeholder($password2)) {
    http_response_code(500);
    echo 'CONFIG_ERROR';
    exit;
}

if ($outSum === '' || $invId === '' || $signature === '') {
    http_response_code(400);
    echo 'BAD_REQUEST';
    exit;
}

if (!isset($shows[$show])) {
    http_response_code(422);
    echo 'UNKNOWN_SHOW';
    exit;
}

$qty = max(1, min($qty, 20));

$myCrc = strtolower(md5($outSum . ':' . $invId . ':' . $password2 . ':Shp_qty=' . $qty . ':Shp_show=' . $show));

// Иногда IsTest в webhook отсутствует, хотя платёж тестовый.
// В таком случае дополнительно проверяем подпись тестовым Password2.
if ($myCrc !== $signature) {
    $testPassword2 = robokassa_get_password2(true);
    if (!robokassa_is_placeholder($testPassword2)) {
        $testCrc = strtolower(md5($outSum . ':' . $invId . ':' . $testPassword2 . ':Shp_qty=' . $qty . ':Shp_show=' . $show));
        if ($testCrc === $signature) {
            $isTest = true;
        } else {
            http_response_code(403);
            echo 'BAD_SIGNATURE';
            exit;
        }
    } else {
        http_response_code(403);
        echo 'BAD_SIGNATURE';
        exit;
    }
}

// Лёгкий лог webhook-запросов для оператора (без секретов).
$logPath = (string) (getenv('ROBOKASSA_WEBHOOK_LOG_PATH') ?: (__DIR__ . '/../storage/robokassa-webhook.log'));
$logDir = dirname($logPath);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logLine = json_encode([
    'ts' => time(),
    'inv_id' => (string) $invId,
    'out_sum' => (string) $outSum,
    'show' => (string) $show,
    'qty' => (int) $qty,
    'is_test' => (bool) $isTest,
    'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (is_string($logLine)) {
    $written = @file_put_contents($logPath, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        $fallbackLog = rtrim(sys_get_temp_dir(), '/\\') . '/podmostki-robokassa-webhook.log';
        @file_put_contents($fallbackLog, $logLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

$issued = read_issued_codes();
$prefix = get_show_primary_prefix($show, $shows);
if ($prefix === null) {
    http_response_code(500);
    echo 'SHOW_PREFIX_ERROR';
    exit;
}

$created = [];
$now = time();
for ($i = 0; $i < $qty; $i += 1) {
    $code = generate_ticket_code($prefix, $issued);
    $issued[$code] = [
        'show' => $show,
        'created_at' => $now,
        'source' => $isTest ? 'robokassa-test' : 'robokassa',
        'invoice' => (string) $invId,
        'amount' => (string) $outSum,
        'is_test' => $isTest
    ];
    $created[] = $code;
}

if (!write_issued_codes($issued)) {
    http_response_code(500);
    echo 'STORAGE_ERROR';
    exit;
}

// Можно дописать отправку email/SMS с кодами.
// Робокасса ждёт ответ OK+InvoiceId.
echo 'OK' . $invId;
