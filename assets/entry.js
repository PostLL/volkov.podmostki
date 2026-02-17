document.addEventListener('DOMContentLoaded', function () {
    const LAST_CODE_KEY = 'podmostki:lastCode';
    const DEVICE_ID_KEY = 'podmostki:deviceId';
    const PLAYER_GATE_KEY = 'podmostki:playerGate';

    const inputEl = document.getElementById('ticketCode');
    const actionBtnEl = document.getElementById('entryActionBtn');
    const messageEl = document.getElementById('entryMessage');

    const deviceId = getOrCreateDeviceId();
    let latestStatus = null;

    const savedCode = localStorage.getItem(LAST_CODE_KEY);
    if (savedCode) {
        inputEl.value = savedCode;
    }

    inputEl.addEventListener('input', function () {
        const clean = inputEl.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        inputEl.value = clean.slice(0, 6);

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

    if (inputEl.value.length === 6) {
        checkStatus(inputEl.value);
    } else {
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Введите 6 символов';
    }

    function getOrCreateDeviceId() {
        const existing = localStorage.getItem(DEVICE_ID_KEY);
        if (existing) return existing;

        const generated = (window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : `device-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

        localStorage.setItem(DEVICE_ID_KEY, generated);
        return generated;
    }

    function setMessage(text) {
        messageEl.textContent = text || '';
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

    function checkStatus(code) {
        setMessage('Проверяем код...');
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Проверка...';

        fetch(`/api/code-status.php?code=${encodeURIComponent(code)}&device_id=${encodeURIComponent(deviceId)}`)
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
                    setMessage('Код найден. Нажмите «Активировать».');
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

    function activateCode(code) {
        actionBtnEl.disabled = true;
        actionBtnEl.textContent = 'Активация...';

        fetch('/api/activate-code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code, device_id: deviceId })
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

    function goToPlayer(showSlug, code) {
        sessionStorage.setItem(PLAYER_GATE_KEY, '1');
        window.location.href = `/player.php?show=${encodeURIComponent(showSlug)}&code=${encodeURIComponent(code)}`;
    }
});