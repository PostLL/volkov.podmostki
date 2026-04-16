<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Аудиоспектакль</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/play.svg">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player" id="playerRoot">
    <header class="app-header app-header--player">
        <div class="brand brand--player">
            <img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого компании">
        </div>
    </header>

    <section class="cover-wrap">
        <img id="showCover" src="" alt="Обложка спектакля">
    </section>

    <h3 id="trackTitle">Загрузка...</h3>

    <input type="range" id="progress" value="0" min="0" max="100" step="0.1" aria-label="Прогресс воспроизведения">

    <div class="time">
        <span id="currentTime">0:00</span>
        <span id="duration">0:00</span>
    </div>

    <div class="controls">
        <button id="prevBtn" onclick="prevTrack()" aria-label="Предыдущий трек">
            <img src="/assets/icons/prev.svg" alt="Назад">
        </button>

        <button id="playBtn" onclick="playPause()" aria-label="Играть или пауза">
            <img id="playIcon" src="/assets/icons/play.svg" alt="Играть">
        </button>

        <button id="nextBtn" onclick="nextTrack()" aria-label="Следующий трек">
            <img src="/assets/icons/next.svg" alt="Вперёд">
        </button>
    </div>

    <div class="playlist-divider" aria-hidden="true"></div>

    <section class="playlist-wrap">
        <h4>Плейлист</h4>
        <ul id="playlist" class="playlist"></ul>
    </section>

    <div class="playlist-divider" aria-hidden="true"></div>

    <footer class="show-meta">
        <h1 id="showTitle" class="show-title">Загрузка спектакля...</h1>
        <p id="showDescription"></p>
        <p id="showAuthors"></p>
    </footer>

    <p id="playerMessage" class="message" aria-live="polite"></p>
</div>

<script src="/assets/player.js"></script>
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