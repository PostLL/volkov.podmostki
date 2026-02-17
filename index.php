<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подмостки — Вход по коду</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/play.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header">
        <div class="brand">
            <img class="brand-logo" src="/assets/logo.png" alt="Лого компании">
            <div>
                <p class="brand-name">Подмостки</p>
                <h1>Введите код билета</h1>
            </div>
        </div>
    </header>

    <section class="entry-card">
        <p class="entry-help">Введите 6 символов кода. Статус проверяется автоматически.</p>
        <div class="code-row">
            <input id="ticketCode" type="text" maxlength="6" placeholder="Например, G12345" autocomplete="off">
            <button id="entryActionBtn" type="button" disabled>Проверка...</button>
        </div>
        <p id="entryMessage" class="message" aria-live="polite"></p>
    </section>
</div>

<script src="/assets/entry.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js').catch(function (err) {
                console.warn('SW register failed:', err);
            });
        });
    }
</script>
</body>
</html>