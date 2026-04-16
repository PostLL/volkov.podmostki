<?php
require __DIR__ . '/api/env.php';
$fio = getenv('COMPANY_SELF_EMPLOYED_FIO') ?: 'ФИО самозанятого';
$inn = getenv('COMPANY_SELF_EMPLOYED_INN') ?: 'ИНН';
$email = getenv('COMPANY_CONTACT_EMAIL') ?: 'support@your-domain.ru';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Политика обработки персональных данных</title>
    <link rel="icon" type="image/svg+xml" href="/assets/icons/manifest-logo.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header app-header--entry"><div class="brand brand--entry"><img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого"></div></header>
    <section class="notice-card compliance-card">
        <h1 class="result-title">Политика обработки персональных данных</h1>
        <p><?= htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') ?>, ИНН <?= htmlspecialchars($inn, ENT_QUOTES, 'UTF-8') ?>.</p>
        <p><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>.</p>
        <p>Мы обрабатываем только данные, необходимые для продажи цифрового доступа: параметры заказа и технические данные для защиты от мошенничества.</p>
        <p>Данные не передаются третьим лицам, кроме платежного провайдера (Робокасса) и обязательных по закону случаев.</p>
    </section>
</div>
</body>
</html>
