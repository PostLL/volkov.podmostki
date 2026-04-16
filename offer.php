<?php
require __DIR__ . '/api/env.php';
$fio = getenv('COMPANY_SELF_EMPLOYED_FIO') ?: 'ФИО самозанятого';
$inn = getenv('COMPANY_SELF_EMPLOYED_INN') ?: 'ИНН';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Публичная оферта</title>
    <link rel="icon" type="image/svg+xml" href="/assets/icons/manifest-logo.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header app-header--entry"><div class="brand brand--entry"><img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого"></div></header>
    <section class="notice-card compliance-card">
        <h1 class="result-title">Публичная оферта</h1>
        <p>Продавец: <?= htmlspecialchars($fio, ENT_QUOTES, 'UTF-8') ?>, ИНН <?= htmlspecialchars($inn, ENT_QUOTES, 'UTF-8') ?>.</p>
        <p>Предмет оферты: предоставление цифрового доступа к аудиоспектаклям «Волков.Подмостки» после оплаты.</p>
        <p>Порядок оплаты: через Робокассу. После успешной оплаты покупатель получает билетные коды на странице success.</p>
        <p>Возврат: 
        <p>1. Основания для возврата</p>
        <p>Возврат Товара надлежащего качества возможен исключительно при условии, что Товар не был активирован.</p>
        <p>Активацией считается: использование активационного кода или любое иное действие, приводящее к началу оказания услуги или получению доступа к цифровому контенту.</p>
        <p>2. Порядок оформления возврата</p>
        <p>Для оформления возврата необходимо направить заявку в Службу технической поддержки по адресу: support@volga-tours.ru.</p>
        <p>В теме письма укажите: «Возврат билета № [код билета]».</p>
        <p>3. Обработка заявки</p>
        <p>Специалисты компании проверяют статус активации Товара.</p>
        <p>Если Товар не активирован: Мы подтверждаем возможность возврата и инициируем процедуру перечисления денежных средств на карту плательщика.</p>
        <p>Если Товар активирован: В возврате будет отказано, так как данный Товар относится к категории, не подлежащей возврату после начала использования (в соответствии с условиями Оферты).</p>
        <p>4. Сроки</p>
        <p>Средства будут возвращены в течение 3 банковских дней после подтверждения статуса Товара.</p>
    </section>
</div>
</body>
</html>