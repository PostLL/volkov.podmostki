<?php
require __DIR__ . '/api/env.php';
$shows = require __DIR__ . '/api/shows.php';

function env_or_placeholder(string $key, string $placeholder): string
{
    $value = trim((string) (getenv($key) ?: ''));
    return $value !== '' ? $value : $placeholder;
}

$contacts = [
    'email' => env_or_placeholder('COMPANY_CONTACT_EMAIL', 'support@volga-tours.ru'),
    'phone' => env_or_placeholder('COMPANY_CONTACT_PHONE', '+7 (901) 270 45 82'),
    'telegram' => env_or_placeholder('COMPANY_CONTACT_TELEGRAM', 'https://t.me/pavelkoss'),
    'order_rules' => env_or_placeholder('COMPANY_ORDER_RULES', 'После оплаты покупатель получает страницу с билетными кодами и может сразу активировать доступ.'),
    'order' => env_or_placeholder('COMPANY_ORDER', 'После оплаты покупатель получает страницу с билетными кодами и может сразу активировать доступ.'),
    'service_terms' => env_or_placeholder('COMPANY_SERVICE_TERMS', 'Услуга предоставляется онлайн: цифровой доступ к выбранному спектаклю.'),
    'refund_terms' => env_or_placeholder('COMPANY_REFUND_TERMS', 'Возврат/отказ: до активации кода, по запросу в поддержку.'),
    'fio' => env_or_placeholder('COMPANY_SELF_EMPLOYED_FIO', 'Косарев Павел Евгеньевич'),
    'inn' => env_or_placeholder('COMPANY_SELF_EMPLOYED_INN', '760908670945'),
    'privacy_url' => env_or_placeholder('COMPANY_PRIVACY_POLICY_URL', 'https://pavelpostl.store/privacy'),
    'offer_url' => env_or_placeholder('COMPANY_OFFER_URL', 'https://pavelpostl.store/offer')
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Магазин билетов — Волков.Подмостки</title>
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

    <section class="entry-card result-card">
        <h1 class="result-title">Магазин спектаклей</h1>
        <p class="result-subtitle">Выберите спектакль, количество билетов и переходите к оплате через Робокассу.</p>
    </section>

    <section class="tickets-grid" id="shopGrid">
        <?php $visible = 0; foreach ($shows as $slug => $show): ?>
            <?php if (($show['enabled'] ?? true) !== true) { continue; } ?>
            <?php $visible++; ?>
            <article class="ticket-card shop-card" data-show="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
                <p class="ticket-label"><?= htmlspecialchars($show['title'] ?? $slug, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="ticket-cut" aria-hidden="true"></div>
                <p class="shop-description"><?= htmlspecialchars($show['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="shop-meta">Формат: цифровой билет с кодом доступа · Авторы: <?= htmlspecialchars($show['authors'] ?? 'Команда проекта', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="shop-price"><?= (int) ($show['price_rub'] ?? 0) ?> ₽ за 1 билет</p>
                <div class="shop-controls">
                    <label class="shop-label" for="qty-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">Количество</label>
                    <div class="shop-qty-stepper">
                        <button class="shop-qty-btn" type="button" data-step="-1" data-target="qty-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" aria-label="Уменьшить количество">−</button>
                        <input class="code-input shop-qty" id="qty-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" type="text" inputmode="numeric" value="1" readonly aria-readonly="true">
                        <button class="shop-qty-btn" type="button" data-step="1" data-target="qty-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" aria-label="Увеличить количество">+</button>
                    </div>
                    <button class="entry-action ticket-btn ticket-btn--use shop-buy-btn" type="button" data-show="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">Купить</button>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($visible === 0): ?>
            <article class="ticket-card ticket-card--info">
                <p class="ticket-empty">Сейчас нет открытых продаж. Загляните позже</p>
            </article>
        <?php endif; ?>
    </section>

    <section class="notice-card compliance-card">
        <h2>Информация для покупателя</h2>
        <ul class="compliance-list">
            <li><strong>Описание услуги:</strong> <?= htmlspecialchars($contacts['order'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><strong>Как оформить заказ:</strong> <?= htmlspecialchars($contacts['order_rules'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><strong>Срок исполнения / предоставление услуги:</strong> <?= htmlspecialchars($contacts['service_terms'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><strong>Оплата:</strong> онлайн через Робокассу (банковская карта/СБП и др. способы, доступные в кассе).</li>
            <li><strong>Условия возврата / отказа:</strong> <?= htmlspecialchars($contacts['refund_terms'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><strong>Контакты:</strong> <?= htmlspecialchars($contacts['email'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($contacts['phone'], ENT_QUOTES, 'UTF-8') ?> · <a href="<?= htmlspecialchars($contacts['telegram'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Telegram</a></li>
            <li><strong>Реквизиты самозанятого:</strong> <?= htmlspecialchars($contacts['fio'], ENT_QUOTES, 'UTF-8') ?>, ИНН <?= htmlspecialchars($contacts['inn'], ENT_QUOTES, 'UTF-8') ?></li>
            <li><strong>Политика ПДн:</strong> <a href="<?= htmlspecialchars($contacts['privacy_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($contacts['privacy_url'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <li><strong>Публичная оферта:</strong> <a href="<?= htmlspecialchars($contacts['offer_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($contacts['offer_url'], ENT_QUOTES, 'UTF-8') ?></a></li>
        </ul>
    </section>

    <p id="shopMessage" class="message" aria-live="polite"></p>
</div>

<script src="/assets/shop.js?v=20260225c"></script>
</body>
</html>
