<?php
$invId = (string) ($_REQUEST['InvId'] ?? $_REQUEST['InvoiceID'] ?? $_REQUEST['inv_id'] ?? '');
$outSum = (string) ($_REQUEST['OutSum'] ?? $_REQUEST['out_sum'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оплата не завершена — Волков.Подмостки</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/play.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header app-header--entry">
        <div class="brand brand--entry">
            <img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого компании">
        </div>
    </header>

    <section class="entry-card result-card result-card--fail">
        <h1 class="result-title">Оплата не долетела до сцены</h1>
        <p class="result-subtitle">Ничего страшного — это просто антракт. Давайте попробуем ещё раз.</p>
    </section>

    <section class="notice-card">
        <h2>Что можно сделать сейчас</h2>
        <p>1) Вернуться на главную и повторить оплату.</p>
        <p>2) Проверить интернет и лимиты карты.</p>
        <p>3) Если деньги списались — напишите в поддержку, мы поможем быстро.</p>
        <?php if ($invId !== ''): ?>
            <p><strong>Номер заказа:</strong> <?= htmlspecialchars($invId, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($outSum !== ''): ?>
            <p><strong>Сумма:</strong> <?= htmlspecialchars($outSum, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="ticket-actions ticket-actions--compact">
            <a class="entry-action ticket-btn ticket-btn--ghost" href="/">↩ На главную</a>
            <a class="entry-action ticket-btn ticket-btn--ghost" href="mailto:support@podmostki.ru">✉ Поддержка</a>
        </div>
    </section>
</div>
</body>
</html>
