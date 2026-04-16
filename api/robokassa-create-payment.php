<?php
header('Content-Type: application/json; charset=utf-8');

$shows = require __DIR__ . '/shows.php';
require __DIR__ . '/robokassa-config.php';

$adminKey = getenv('PODMOSTKI_ADMIN_KEY') ?: 'CHANGE_ME_NOW';
$providedKey = $_GET['key'] ?? ($_POST['key'] ?? '');
if ($adminKey === 'CHANGE_ME_NOW' || $providedKey !== $adminKey) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

$show = (string) ($_GET['show'] ?? ($_POST['show'] ?? ''));
$qty = (int) ($_GET['qty'] ?? ($_POST['qty'] ?? 1));
$amount = (string) ($_GET['amount'] ?? ($_POST['amount'] ?? '1.00'));
$email = trim((string) ($_GET['email'] ?? ($_POST['email'] ?? '')));
$isTest = ((string) ($_GET['is_test'] ?? ($_POST['is_test'] ?? '1'))) === '1';

if (!isset($shows[$show])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Нужен valid show']);
    exit;
}

$qty = max(1, min($qty, 20));
if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Неверный amount']);
    exit;
}

$merchantLogin = robokassa_get_merchant_login();
$password1 = robokassa_get_password1($isTest);

if (robokassa_is_placeholder($merchantLogin) || robokassa_is_placeholder($password1)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Заполните ROBOKASSA_MERCHANT_LOGIN и ROBOKASSA_PASSWORD1/ROBOKASSA_TEST_PASSWORD1']);
    exit;
}

$invoiceId = (string) time() . random_int(100, 999);
$description = 'Волков.Подмостки: ' . ($shows[$show]['title'] ?? $show) . ' x' . $qty;

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$crc = md5($merchantLogin . ':' . $amount . ':' . $invoiceId . ':' . $password1 . ':Shp_show=' . $show . ':Shp_qty=' . $qty);

$query = [
    'MerchantLogin' => $merchantLogin,
    'OutSum' => $amount,
    'InvId' => $invoiceId,
    'Description' => $description,
    'SignatureValue' => $crc,
    'Shp_show' => $show,
    'Shp_qty' => (string) $qty,
    'Culture' => 'ru',
    'Encoding' => 'utf-8',
    'SuccessURL' => $baseUrl . '/success.php',
    'FailURL' => $baseUrl . '/fail.php'
];

if ($isTest) {
    $query['IsTest'] = '1';
}

if ($email !== '') {
    $query['Email'] = $email;
}

$paymentUrl = robokassa_get_payment_url($isTest) . '?' . http_build_query($query);

echo json_encode([
    'ok' => true,
    'is_test' => $isTest,
    'payment_url' => $paymentUrl,
    'invoice_id' => $invoiceId,
    'show' => $show,
    'qty' => $qty,
    'amount' => $amount
]);
