document.addEventListener('DOMContentLoaded', function () {
    const configuredBase = (window.PodmostkiAppBaseUrl || '').replace(/\/+$/, '');
    const shareButtons = document.querySelectorAll('.ticket-share-btn');

    shareButtons.forEach(function (button) {
        button.addEventListener('click', async function () {
            const code = button.getAttribute('data-code') || '';
            if (!code) return;

            const baseUrl = configuredBase || window.location.origin;
            const url = `${baseUrl}/?code=${encodeURIComponent(code)}`;
            const shareText = `Ваш билет в Волков.Подмостки: ${code}`;

            try {
                if (navigator.share) {
                    await navigator.share({
                        title: 'Билет Волков.Подмостки',
                        text: shareText,
                        url
                    });
                    return;
                }

                await navigator.clipboard.writeText(url);
                button.textContent = 'Ссылка скопирована';
                setTimeout(function () {
                    button.textContent = 'Поделиться';
                }, 1800);
            } catch (e) {
                window.prompt('Скопируйте ссылку и отправьте её:', url);
            }
        });
    });
});
