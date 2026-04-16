document.addEventListener('DOMContentLoaded', function () {
    const messageEl = document.getElementById('shopMessage');
    const buttons = document.querySelectorAll('.shop-buy-btn');
    const qtyButtons = document.querySelectorAll('.shop-qty-btn');

    function setMessage(text) {
        messageEl.textContent = text || '';
    }

    function normalizeQty(raw) {
        const num = parseInt(String(raw || '1'), 10);
        if (Number.isNaN(num)) return 1;
        return Math.max(1, Math.min(10, num));
    }

    qtyButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target') || '';
            const step = parseInt(btn.getAttribute('data-step') || '0', 10);
            if (!targetId || Number.isNaN(step)) return;

            const input = document.getElementById(targetId);
            if (!input) return;

            const current = normalizeQty(input.value);
            input.value = String(normalizeQty(current + step));
        });
    });

    buttons.forEach(function (button) {
        button.addEventListener('click', async function () {
            const show = button.getAttribute('data-show') || '';
            const qtyInput = document.getElementById('qty-' + show);
            const qty = normalizeQty(qtyInput ? qtyInput.value : '1');

            button.disabled = true;
            setMessage('Готовим безопасную ссылку оплаты...');

            try {
                // Для витрины режим берётся из настроек админки (cash_mode),
                // поэтому не форсируем is_test тут.
                const query = new URLSearchParams({ show: show, qty: String(qty) });
                const res = await fetch('/api/robokassa-public-payment.php?' + query.toString(), { cache: 'no-store' });
                const data = await res.json();

                if (!data.ok || !data.payment_url) {
                    setMessage(data.message || 'Не удалось создать ссылку оплаты.');
                    button.disabled = false;
                    return;
                }

                setMessage('Переходим к оплате...');
                window.location.href = data.payment_url;
            } catch (e) {
                setMessage('Ошибка сети. Попробуйте ещё раз.');
                button.disabled = false;
            }
        });
    });
});
