<?php
require __DIR__ . '/api/env.php';
require __DIR__ . '/api/code-lib.php';

$invId = (string) ($_GET['InvId'] ?? $_GET['inv_id'] ?? '');
$codes = [];

if ($invId !== '') {
    $issued = read_issued_codes();
    foreach ($issued as $code => $entry) {
        if ((string) ($entry['invoice'] ?? '') === $invId) {
            $codes[] = [
                'code' => $code,
                'show' => (string) ($entry['show'] ?? ''),
                'source' => (string) ($entry['source'] ?? '')
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оплата успешна — Волков.Подмостки</title>
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

    <section class="entry-card result-card result-card--success">
        <h1 class="result-title">Оплата прошла успешно 🎭</h1>
        <p class="result-subtitle">Ваши билетные коды готовы. Нажмите «Использовать» или «Поделиться».</p>
    </section>

    <section class="tickets-grid" id="ticketsGrid">
        <?php if ($invId === ''): ?>
            <article class="ticket-card ticket-card--info">
                <p class="ticket-empty">Номер заказа не найден в ссылке. Откройте страницу из письма/чека Робокассы.</p>
            </article>
        <?php elseif (empty($codes)): ?>
            <article class="ticket-card ticket-card--info">
                <p class="ticket-empty">Коды ещё не отображаются. Подождите 5–10 секунд и обновите страницу.</p>
            </article>
        <?php else: ?>
            <?php foreach ($codes as $idx => $item): ?>
                <article class="ticket-card" data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>">
                    <p class="ticket-label">Билет #<?= $idx + 1 ?></p>
                    <p class="ticket-code"><?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="ticket-actions">
                        <a class="entry-action ticket-btn" href="/?code=<?= urlencode($item['code']) ?>">Использовать</a>
                        <button class="entry-action ticket-btn ticket-share-btn" type="button" data-code="<?= htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8') ?>">Поделиться</button>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="notice-card">
        <h2>Важно</h2>
        <p>Сохраните эту страницу или отправьте код себе в мессенджер.</p>
        <p><strong>Техподдержка:</strong> support@podmostki.ru · +7 (900) 000-00-00</p>
    </section>
</div>

<script src="/assets/payment-result.js"></script>
</body>
</html>
