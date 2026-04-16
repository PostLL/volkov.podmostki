document.addEventListener('DOMContentLoaded', function () {
    // Ключи local/session storage для клиентского состояния.
    const LAST_CODE_KEY = 'podmostki:lastCode';
    const DEVICE_ID_KEY = 'podmostki:deviceId';
    const DEVICE_FINGERPRINT_KEY = 'podmostki:deviceFingerprint';
    const PLAYER_GATE_KEY = 'podmostki:playerGate';

    // Элементы страницы входа.
    const inputEl = document.getElementById('ticketCode');
    const actionBtnEl = document.getElementById('entryActionBtn');
    const messageEl = document.getElementById('entryMessage');

    // Идентификатор устройства (persist) и мягкий fingerprint (для инкогнито fallback).
    const deviceId = getOrCreateDeviceId();
    const deviceFingerprint = getOrCreateDeviceFingerprint();

    // Последний ответ API по текущему коду.
    let latestStatus = null;

    // 1) Автозаполнение из ссылки: /?code=G12345
    // 2) Если ссылки нет — подставляем последний код пользователя.
    const params = new URLSearchParams(window.location.search);
    const codeFromLink = sanitizeTicketCode(params.get('code') || '');
    const savedCode = localStorage.getItem(LAST_CODE_KEY) || '';

    if (codeFromLink.length === 6) {
        inputEl.value = codeFromLink;
        localStorage.setItem(LAST_CODE_KEY, codeFromLink);
    } else if (savedCode) {
        inputEl.value = savedCode;
    }

    // Проверка кода после каждого изменения, как только введено 6 символов.
    inputEl.addEventListener('input', function () {
        inputEl.value = sanitizeTicketCode(inputEl.value);

        localStorage.setItem(LAST_CODE_KEY, inputEl.value);

        if (inputEl.value.length === 6) {
            checkStatus(inputEl.value);
        } else {
            latestStatus = null;
            actionBtnEl.disabled = true;
            actionBtnEl.textContent = 'Введите 6 символов';
            setMessage('');
        }
    });

    // Обработка кнопки: Активировать / Продолжить в зависимости от статуса.
    actionBtnEl.addEventListener('click', function () {
        if (!latestStatus) return;

        if (latestStatus.status === 'not_activated') {
            activateCode(latestStatus.code);
            return;
        }

        if (latestStatus.status === 'active') {
            goToPlayer(latestStatus.show, latestStatus.code);
        }
    });

    // Если код уже есть (из ссылки или localStorage) — сразу проверяем.
    if (inputEl.value.length === 6) {
        checkStatus(inputEl.value);
    } else {
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Введите 6 символов';
    }

    /**
     * Стабильный device_id для обычного режима браузера.
     */
    function getOrCreateDeviceId() {
        const existing = localStorage.getItem(DEVICE_ID_KEY);
        if (existing) return existing;

        const generated = (window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : `device-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

        localStorage.setItem(DEVICE_ID_KEY, generated);
        return generated;
    }

    /**
     * Мягкий отпечаток браузера/устройства (без хранения персональных данных).
     * Помогает распознать «то же устройство» при новом device_id в инкогнито.
     */
    function getOrCreateDeviceFingerprint() {
        const existing = localStorage.getItem(DEVICE_FINGERPRINT_KEY);
        if (existing) return existing;

        const parts = [
            // Без userAgent: чтобы один и тот же телефон в другом браузере
            // чаще распознавался как то же устройство.
            navigator.platform || '',
            navigator.language || '',
            String(navigator.maxTouchPoints || 0),
            String(navigator.hardwareConcurrency || 0),
            String(window.screen ? window.screen.width : ''),
            String(window.screen ? window.screen.height : ''),
            String(new Date().getTimezoneOffset())
        ];

        const raw = parts.join('|');
        let hash = 0;
        for (let i = 0; i < raw.length; i += 1) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }

        const fp = `fp-${Math.abs(hash)}`;
        localStorage.setItem(DEVICE_FINGERPRINT_KEY, fp);
        return fp;
    }

    function setMessage(text) {
        messageEl.textContent = text || '';
    }

    function sanitizeTicketCode(raw) {
        return String(raw || '')
            .toUpperCase()
            .replace(/[^A-Z0-9!_\-]/g, '')
            .slice(0, 6);
    }

    function setButtonByStatus(status) {
        if (status === 'not_activated') {
            actionBtnEl.disabled = false;
            actionBtnEl.textContent = 'Активировать';
            return;
        }

        if (status === 'active') {
            actionBtnEl.disabled = false;
            actionBtnEl.textContent = 'Продолжить';
            return;
        }

        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Недоступно';
    }

    // Запрос статуса кода.
    function checkStatus(code) {
        setMessage('Проверяем код...');
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Проверка...';

        const query = new URLSearchParams({
            code: code,
            device_id: deviceId,
            device_fingerprint: deviceFingerprint
        });

        fetch(`/api/code-status.php?${query.toString()}`)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                latestStatus = data;

                if (data.status === 'invalid') {
                    setMessage('Код введён неверно.');
                    setButtonByStatus(data.status);
                    return;
                }

                if (data.status === 'expired') {
                    setMessage('Срок доступа по этому коду истёк.');
                    setButtonByStatus(data.status);
                    return;
                }

                if (data.status === 'device_mismatch') {
                    setMessage('Этот билет уже используется на другом устройстве.');
                    setButtonByStatus(data.status);
                    return;
                }

                if (data.status === 'not_activated') {
                    setMessage(data.message || 'Код найден. Нажмите «Активировать».');
                    setButtonByStatus(data.status);
                    return;
                }

                if (data.status === 'active') {
                    setMessage('Код активен. Нажмите «Продолжить».');
                    setButtonByStatus(data.status);
                    return;
                }

                setMessage('Не удалось определить статус кода.');
                setButtonByStatus('invalid');
            })
            .catch(function () {
                latestStatus = null;
                setMessage('Ошибка проверки кода. Попробуйте снова.');
                actionBtnEl.disabled = true;
                actionBtnEl.textContent = 'Проверка недоступна';
            });
    }

    // Активация кода на устройстве.
    function activateCode(code) {
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Активация...';

        fetch('/api/activate-code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                code: code,
                device_id: deviceId,
                device_fingerprint: deviceFingerprint
            })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                latestStatus = data;

                if (data.status === 'active') {
                    setMessage('Код активирован. Переходим в плеер...');
                    setButtonByStatus('active');
                    goToPlayer(data.show, data.code);
                    return;
                }

                if (data.status === 'expired') {
                    setMessage('Срок доступа по этому коду истёк.');
                    setButtonByStatus('expired');
                    return;
                }

                if (data.status === 'device_mismatch') {
                    setMessage('Этот билет уже используется на другом устройстве.');
                    setButtonByStatus('device_mismatch');
                    return;
                }

                if (data.status === 'storage_error') {
                    setMessage(data.message || 'Ошибка сохранения активации на сервере.');
                    setButtonByStatus('invalid');
                    return;
                }

                setMessage(data.message || 'Не удалось активировать код.');
                setButtonByStatus('invalid');
            })
            .catch(function () {
                setMessage('Ошибка активации. Попробуйте ещё раз.');
                setButtonByStatus('invalid');
            });
    }

    // Переход в плеер разрешается только через gate-флаг.
    function goToPlayer(showSlug, code) {
        sessionStorage.setItem(PLAYER_GATE_KEY, '1');
        window.location.href = `/player.php?show=${encodeURIComponent(showSlug)}&code=${encodeURIComponent(code)}`;
    }
});
