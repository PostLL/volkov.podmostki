<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Волков.Подмостки — Вход по коду</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/manifest-logo.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header app-header--entry">
        <div class="brand brand--entry">
            <img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого компании">
        </div>
    </header>

    <section class="entry-card">
        <p class="entry-help">Введите код билета (6 символов)</p>

        <input id="ticketCode" class="code-input" type="text" maxlength="6" placeholder="Например, G1A-2B" autocomplete="off">

        <p id="entryMessage" class="message" aria-live="polite"></p>

        <button id="entryActionBtn" class="entry-action" type="button" disabled>Проверка...</button>
    </section>

    <section class="notice-card">
        <h2>Внимание!</h2>
        <p>Введите код полностью и дождитесь проверки статуса. Если код активен — нажмите «Продолжить», если не активирован — «Активировать».</p>
        <p>Доступ к спектаклю ограничен по времени. Не передавайте код третьим лицам.</p>
        <p><strong>Техподдержка:</strong> support@volga-tours.ru · +7 (900) 000-00-00</p>
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