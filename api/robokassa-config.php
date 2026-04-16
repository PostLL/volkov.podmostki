<?php

require_once __DIR__ . '/env.php';

/**
 * Конфиг Робокассы (поддержка тестового и боевого режима).
 */
function robokassa_get_merchant_login()
{
    return getenv('ROBOKASSA_MERCHANT_LOGIN') ?: 'CHANGE_ME_ROBOKASSA_LOGIN';
}

function robokassa_get_password1($isTest)
{
    if ($isTest) {
        return getenv('ROBOKASSA_TEST_PASSWORD1') ?: (getenv('ROBOKASSA_PASSWORD1') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD1');
    }

    return getenv('ROBOKASSA_PASSWORD1') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD1';
}

function robokassa_get_password2($isTest)
{
    if ($isTest) {
        return getenv('ROBOKASSA_TEST_PASSWORD2') ?: (getenv('ROBOKASSA_PASSWORD2') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD2');
    }

    return getenv('ROBOKASSA_PASSWORD2') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD2';
}

function robokassa_get_payment_url($isTest)
{
    // По официальной документации используем единый URL для test/live.
    return 'https://auth.robokassa.ru/Merchant/Index.aspx';
}

function robokassa_normalize_base_url($url)
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return rtrim($url, '/');
}

function robokassa_detect_current_base_url()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function robokassa_get_shop_base_url()
{
    $env = robokassa_normalize_base_url((string) (getenv('SHOP_PUBLIC_BASE_URL') ?: ''));
    if ($env !== '') {
        return $env;
    }

    return robokassa_detect_current_base_url();
}

function robokassa_get_app_base_url()
{
    $env = robokassa_normalize_base_url((string) (getenv('APP_PUBLIC_BASE_URL') ?: ''));
    if ($env !== '') {
        return $env;
    }

    return robokassa_get_shop_base_url();
}

function robokassa_is_placeholder($value)
{
    return strpos($value, 'CHANGE_ME_') === 0;
}

function robokassa_is_login_suspicious($merchantLogin)
{
    // На части аккаунтов Robokassa login действительно может быть в формате домена,
    // поэтому не блокируем такие значения на уровне API.
    return false;
}

function robokassa_generate_invoice_id()
{
    return (string) time() . mt_rand(100, 999);
}

function robokassa_build_payment_url(
    $isTest,
    $merchantLogin,
    $password1,
    $amount,
    $invoiceId,
    $description,
    $show,
    $qty,
    $successUrl,
    $failUrl,
    $email = ''
) {
    // По требованиям Robokassa пользовательские Shp_* параметры должны идти
    // в подписи в алфавитном порядке (здесь: Shp_qty, затем Shp_show).
    $crc = md5($merchantLogin . ':' . $amount . ':' . $invoiceId . ':' . $password1 . ':Shp_qty=' . $qty . ':Shp_show=' . $show);

    $query = [
        'MerchantLogin' => $merchantLogin,
        'OutSum' => $amount,
        'InvId' => $invoiceId,
        // Дополнительная совместимость с некоторыми примерами/интеграциями Robokassa.
        'InvoiceID' => $invoiceId,
        'Description' => $description,
        'SignatureValue' => $crc,
        'Shp_show' => $show,
        'Shp_qty' => (string) $qty,
        'Culture' => 'ru',
        'Encoding' => 'utf-8',
        'SuccessURL' => $successUrl,
        'FailURL' => $failUrl,
    ];

    if ($isTest) {
        $query['IsTest'] = '1';
    }

    if ($email !== '') {
        $query['Email'] = $email;
    }

    return robokassa_get_payment_url($isTest) . '?' . http_build_query($query);
}
