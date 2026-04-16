<?php

require_once __DIR__ . '/env.php';

/**
 * Конфиг Робокассы (поддержка тестового и боевого режима).
 */
function robokassa_get_merchant_login(): string
{
    return getenv('ROBOKASSA_MERCHANT_LOGIN') ?: 'CHANGE_ME_ROBOKASSA_LOGIN';
}

function robokassa_get_password1(bool $isTest): string
{
    if ($isTest) {
        return getenv('ROBOKASSA_TEST_PASSWORD1') ?: (getenv('ROBOKASSA_PASSWORD1') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD1');
    }

    return getenv('ROBOKASSA_PASSWORD1') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD1';
}

function robokassa_get_password2(bool $isTest): string
{
    if ($isTest) {
        return getenv('ROBOKASSA_TEST_PASSWORD2') ?: (getenv('ROBOKASSA_PASSWORD2') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD2');
    }

    return getenv('ROBOKASSA_PASSWORD2') ?: 'CHANGE_ME_ROBOKASSA_PASSWORD2';
}

function robokassa_get_payment_url(bool $isTest): string
{
    if ($isTest) {
        return 'https://auth.robokassa.ru/Merchant/Index.aspx';
    }

    return 'https://auth.robokassa.ru/Merchant/Index.aspx';
}

function robokassa_is_placeholder(string $value): bool
{
    return str_starts_with($value, 'CHANGE_ME_');
}
