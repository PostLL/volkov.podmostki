<?php
header('Content-Type: application/json; charset=utf-8');

$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/robokassa-config.php';
require_once __DIR__ . '/db.php';

$show = (string) ($_GET['show'] ?? ($_POST['show'] ?? ''));
$qty = (int) ($_GET['qty'] ?? ($_POST['qty'] ?? 1));
$email = trim((string) ($_GET['email'] ?? ($_POST['email'] ?? '')));
$explicitIsTest = $_GET['is_test'] ?? ($_POST['is_test'] ?? null);

$settingsPath = __DIR__ . '/../storage/settings.json';
$settings = [];
if (db_is_enabled()) {
    try {
        $settings = db_get_settings();
    } catch (Throwable $e) {
        $settings = [];
    }
} elseif (file_exists($settingsPath)) {
    $rawSettings = file_get_contents($settingsPath);
    $decodedSettings = json_decode($rawSettings ?: '[]', true);
    if (is_array($decodedSettings)) {
        $settings = $decodedSettings;
    }
}

$cashMode = (string) ($settings['cash_mode'] ?? '');
if ($cashMode === '') {
    $cashMode = ((string) (getenv('ROBOKASSA_DEFAULT_IS_TEST') ?: '1')) === '1' ? 'test' : 'live';
}
$isTest = $cashMode !== 'live';
if ($explicitIsTest !== null) {
    $isTest = ((string) $explicitIsTest) === '1';
}

if (!isset($shows[$show])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Спектакль не найден']);
    exit;
}

if (($shows[$show]['enabled'] ?? true) === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Продажа этого спектакля сейчас отключена']);
    exit;
}

$qty = max(1, min($qty, 10));
$priceRub = (float) ($shows[$show]['price_rub'] ?? 0);
if ($priceRub <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Для спектакля не задана цена']);
    exit;
}

$amount = number_format($priceRub * $qty, 2, '.', '');
$merchantLogin = robokassa_get_merchant_login();
$password1 = robokassa_get_password1($isTest);

if (robokassa_is_placeholder($merchantLogin) || robokassa_is_placeholder($password1)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Не настроены ключи Робокассы']);
    exit;
}

$invoiceId = robokassa_generate_invoice_id();
$description = 'Волков.Подмостки: ' . ($shows[$show]['title'] ?? $show) . ' x' . $qty;

$baseUrl = robokassa_get_shop_base_url();

$paymentUrl = robokassa_build_payment_url(
    $isTest,
    $merchantLogin,
    $password1,
    $amount,
    $invoiceId,
    $description,
    $show,
    $qty,
    $baseUrl . '/success.php',
    $baseUrl . '/fail.php',
    $email
);

echo json_encode([
    'ok' => true,
    'payment_url' => $paymentUrl,
    'show' => $show,
    'qty' => $qty,
    'amount' => $amount,
    'currency' => 'RUB',
    'is_test' => $isTest
]);
