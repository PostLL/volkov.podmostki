<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Аудиоспектакль</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/assets/styles.css">


</head>
<body>

<div class="player">

    <h3 id="trackTitle">Загрузка...</h3>

    <input type="range" id="progress" value="0" min="0" max="100">

    <div class="time">
        <span id="currentTime">0:00</span>
        <span id="duration">0:00</span>
    </div>

    <div>
        <button onclick="prevTrack()">
            <img src="/assets/icons/prev.svg" alt="Назад">
        </button>

        <button id="playBtn" onclick="playPause()">
            <img id="playIcon" src="/assets/icons/play.svg" alt="Играть">
        </button>

        <button onclick="nextTrack()">
            <img src="/assets/icons/next.svg" alt="Вперёд">
        </button>
    </div>

</div>

<script src="/assets/player.js"></script>
</body>
</html>
