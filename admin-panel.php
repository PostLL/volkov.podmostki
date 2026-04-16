<?php
$shows = require __DIR__ . '/api/shows.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Админка кодов</title>
    <style>
        body{background:#000;color:#ff7f00;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0}
        .wrap{max-width:760px;margin:0 auto;padding:20px 16px;display:grid;gap:12px}
        .card{border:1px solid #1f1f1f;border-radius:12px;padding:14px;background:#090909}
        .card h2,.card h3{margin:0 0 10px}
        .grid{display:grid;gap:8px}
        .grid input,.grid select,.grid button{min-height:44px;border-radius:8px;border:1px solid #2c2c2c;background:#101010;color:#ff7f00;padding:8px 10px}
        .grid button{border-color:#ff7f00;background:transparent;cursor:pointer}
        .grid button:disabled{opacity:.5;cursor:not-allowed}
        pre{white-space:pre-wrap;word-break:break-word;background:#050505;padding:10px;border-radius:8px;color:#ffc788;min-height:60px}
        .hint{color:#ffd8ab;font-size:14px;margin:0}
    </style>
</head>
<body>
<div class="wrap">
    <header class="card">
        <h2>Админка кодов</h2>
        <p class="hint">Если кнопка долго думает, покажем ошибку через 12 секунд (чтобы не было "вечной загрузки").</p>
        <div class="grid">
            <input id="adminKey" type="password" placeholder="Секретный ключ (PODMOSTKI_ADMIN_KEY)">
        </div>
    </header>

    <section class="card">
        <h3>Добавить код вручную</h3>
        <div class="grid">
            <select id="showSelect" required>
                <option value="">Сначала выберите спектакль (slug)</option>
                <?php foreach ($shows as $slug => $meta): ?>
                    <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($slug . ' — ' . ($meta['title'] ?? $slug), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input id="addCode" placeholder="Код, например GA1-2B">
            <button onclick="runAction('add_code',{code:val('addCode'),show:val('showSelect')})">Добавить код</button>
            <button id="generateBtn" onclick="runAction('generate_code',{show:val('showSelect')})" disabled>Сгенерировать код</button>
        </div>
    </section>

    <section class="card">
        <h3>Операции с существующим кодом</h3>
        <div class="grid">
            <input id="workCode" placeholder="Код, например GA1-2B">
            <button onclick="runAction('status',{code:val('workCode')})">Проверить статус</button>
            <button onclick="runAction('reset_device',{code:val('workCode')})">Сбросить устройство</button>
            <button onclick="runAction('reset_time',{code:val('workCode')})">Дать ещё время</button>
            <button onclick="runAction('reset_activation',{code:val('workCode')})">Сбросить активацию</button>
            <button onclick="runAction('delete_code',{code:val('workCode')})">Удалить код</button>
        </div>
    </section>

    <section class="card">
        <h3>Обслуживание</h3>
        <div class="grid">
            <button onclick="runAction('run_cleanup',{})">Запустить очистку просроченных (14+ дней)</button>
        </div>
    </section>

    <section class="card">
        <h3>Робокасса (тестовый режим)</h3>
        <div class="grid">
            <select id="rkShow">
                <option value="">Выберите спектакль (slug)</option>
                <?php foreach ($shows as $slug => $meta): ?>
                    <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($slug . ' — ' . ($meta['title'] ?? $slug), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input id="rkAmount" type="text" value="1.00" placeholder="Сумма, например 1.00">
            <input id="rkQty" type="number" min="1" max="20" value="1" placeholder="Количество кодов">
            <input id="rkEmail" type="email" placeholder="Email покупателя (необязательно)">
            <button onclick="createRobokassaTestLink()">Сгенерировать тестовую ссылку оплаты</button>
        </div>
    </section>

    <section class="card">
        <h3>Ответ сервера</h3>
        <pre id="out">Пока пусто.</pre>
    </section>
</div>

<script>
function val(id){return document.getElementById(id).value.trim();}

const showSelect = document.getElementById('showSelect');
const generateBtn = document.getElementById('generateBtn');

showSelect.addEventListener('change', function () {
    generateBtn.disabled = !showSelect.value;
});

async function runAction(action, params) {
    const key = val('adminKey');
    if (!key) {
        alert('Введите секретный ключ');
        return;
    }

    if ((action === 'add_code' || action === 'generate_code') && !val('showSelect')) {
        alert('Сначала выберите спектакль (slug)');
        return;
    }

    const out = document.getElementById('out');
    out.textContent = 'Загрузка...';

    const query = new URLSearchParams({ action, key, ...params });
    const url = '/api/admin-code.php?' + query.toString();

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);

    try {
        const res = await fetch(url, { signal: controller.signal, cache: 'no-store' });
        const text = await res.text();
        out.textContent = text;

        if (action === 'generate_code') {
            try {
                const data = JSON.parse(text);
                if (data.code) {
                    document.getElementById('addCode').value = data.code;
                    document.getElementById('workCode').value = data.code;
                }
            } catch (_) {}
        }
    } catch (e) {
        if (e.name === 'AbortError') {
            out.textContent = 'Таймаут запроса (12 сек). Проверьте сервер, ключ и доступ к /api/admin-code.php';
        } else {
            out.textContent = 'Ошибка запроса: ' + e;
        }
    } finally {
        clearTimeout(timeout);
    }
}

async function createRobokassaTestLink() {
    const key = val('adminKey');
    if (!key) {
        alert('Введите секретный ключ');
        return;
    }

    const show = val('rkShow');
    if (!show) {
        alert('Выберите спектакль для оплаты');
        return;
    }

    const out = document.getElementById('out');
    out.textContent = 'Загрузка...';

    const query = new URLSearchParams({
        key,
        show,
        amount: val('rkAmount') || '1.00',
        qty: val('rkQty') || '1',
        email: val('rkEmail'),
        is_test: '1'
    });

    try {
        const res = await fetch('/api/robokassa-create-payment.php?' + query.toString(), { cache: 'no-store' });
        const text = await res.text();
        out.textContent = text;

        const data = JSON.parse(text);
        if (data.payment_url) {
            window.open(data.payment_url, '_blank', 'noopener');
        }
    } catch (e) {
        out.textContent = 'Ошибка запроса: ' + e;
    }
}
</script>
</body>
</html>
