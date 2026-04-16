<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/env.php';
$shows = require __DIR__ . '/shows.php';

$robokassaConfigPath = __DIR__ . '/robokassa-config.php';
$hasLocalRobokassaConfig = file_exists($robokassaConfigPath);
$forceProxyToShop = ((string) (getenv('ROBOKASSA_PROXY_TO_SHOP') ?: '0')) === '1';
if ($hasLocalRobokassaConfig) {
    require $robokassaConfigPath;
}

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

$httpGet = static function (string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($body !== false && $err === '') {
                return (string) $body;
            }
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true,
        ]
    ]);

    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : (string) $body;
};

// Режим split-доменов: на app-домене может не быть robokassa-config.php и ключей.
// Тогда генерируем ссылку через shop-домен (его публичный endpoint оплаты).
if (!$hasLocalRobokassaConfig || $forceProxyToShop) {
    $shopBaseUrl = trim((string) (getenv('SHOP_PUBLIC_BASE_URL') ?: ''));
    $shopApiUrl = trim((string) (getenv('SHOP_ROBOKASSA_PUBLIC_PAYMENT_URL') ?: ''));

    if ($shopApiUrl === '' && $shopBaseUrl === '') {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'На app-домене нет robokassa-config.php. Укажите SHOP_PUBLIC_BASE_URL или SHOP_ROBOKASSA_PUBLIC_PAYMENT_URL.'
        ]);
        exit;
    }

    $query = http_build_query([
        'show' => $show,
        'qty' => $qty,
        'email' => $email,
        'is_test' => $isTest ? '1' : '0',
    ]);

    $candidates = [];
    if ($shopApiUrl !== '') {
        $candidates[] = rtrim($shopApiUrl, '/');
    }

    if ($shopBaseUrl !== '') {
        $normalizedBase = rtrim($shopBaseUrl, '/');
        $normalizedBase = preg_replace('~/shop(?:\.php)?$~', '', $normalizedBase);
        $normalizedBase = rtrim((string) $normalizedBase, '/');
        if ($normalizedBase !== '') {
            $candidates[] = $normalizedBase . '/api/robokassa-public-payment.php';
        }
        $candidates[] = rtrim($shopBaseUrl, '/') . '/api/robokassa-public-payment.php';
    }

    $candidates = array_values(array_unique(array_filter($candidates)));

    $data = null;
    $lastRaw = '';
    $usedUrl = '';

    foreach ($candidates as $candidate) {
        $url = $candidate . '?' . $query;
        $response = $httpGet($url);
        if ($response === null) {
            continue;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $data = $decoded;
            $usedUrl = $url;
            break;
        }

        $lastRaw = substr($response, 0, 500);
        $usedUrl = $url;
    }

    if (!is_array($data)) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'message' => 'shop-домен вернул не-JSON ответ при генерации ссылки.',
            'tried' => $candidates,
            'used_url' => $usedUrl,
            'raw' => $lastRaw
        ]);
        exit;
    }

    if (!empty($data['ok']) && !empty($data['payment_url'])) {
        $data['proxied_from'] = 'shop';
        $data['used_url'] = $usedUrl;
        if ($amount !== '1.00') {
            $data['note'] = 'В split-режиме сумма берётся из цены спектакля на shop-домене.';
        }
    }

    echo json_encode($data);
    exit;
}

$merchantLogin = robokassa_get_merchant_login();
$password1 = robokassa_get_password1($isTest);

if (robokassa_is_placeholder($merchantLogin) || robokassa_is_placeholder($password1)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Заполните ROBOKASSA_MERCHANT_LOGIN и ROBOKASSA_PASSWORD1/ROBOKASSA_TEST_PASSWORD1']);
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
    'is_test' => $isTest,
    'payment_url' => $paymentUrl,
    'invoice_id' => $invoiceId,
    'show' => $show,
    'qty' => $qty,
    'amount' => $amount
]);
