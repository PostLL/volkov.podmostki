document.addEventListener('DOMContentLoaded', function () {

let playlist = [];
let currentTrackIndex = 0;
let audio = new Audio();
let isPlaying = false;
let saveTimer = null;

audio.preload = "metadata";
audio.playsInline = true;

const STORAGE_KEY = "podcastPlayerState";

/* LOAD PLAYLIST */

fetch('/api/playlist.php?show=volkov-golos')
.then(res => res.json())
.then(data => {
    playlist = data.tracks || [];
    restoreState();
});

/* LOAD TRACK */

function loadTrack(index, restoreTime = 0) {

    if (!playlist.length) return;

    currentTrackIndex = index;
    audio.src = playlist[index].file;
    audio.load();

    document.getElementById('trackTitle').innerText =
        playlist[index].title;

    document.getElementById('progress').value = 0;
    document.getElementById('currentTime').innerText = "0:00";
    document.getElementById('duration').innerText = "0:00";

    audio.onloadedmetadata = function () {

        document.getElementById('duration').innerText =
            formatTime(audio.duration);

        if (restoreTime > 0 && restoreTime < audio.duration) {
            audio.currentTime = restoreTime;
        }
    };

    updateUI();
}

/* SAFE PLAY */

async function safePlay() {
    try {
        await audio.play();
        isPlaying = true;
        updateUI();
        startAutoSave();
    } catch (err) {
        console.log("Play blocked:", err);
    }
}

/* PLAY / PAUSE */

window.playPause = function () {

    if (!audio.src) return;

    if (isPlaying) {
        audio.pause();
        isPlaying = false;
        updateUI();
    } else {
        safePlay();
    }
};

/* EVENTS */

audio.addEventListener('timeupdate', function () {

    if (!audio.duration) return;

    let percent = (audio.currentTime / audio.duration) * 100;
    document.getElementById('progress').value = percent;

    document.getElementById('currentTime').innerText =
        formatTime(audio.currentTime);
});

audio.addEventListener('ended', function () {
    nextTrack();
});

/* SEEK */

document.getElementById('progress')
.addEventListener('input', function () {

    if (!audio.duration) return;

    let newTime = (this.value / 100) * audio.duration;
    audio.currentTime = newTime;
});

/* NAVIGATION */

window.nextTrack = function () {
    if (currentTrackIndex < playlist.length - 1) {
        loadTrack(currentTrackIndex + 1);
        safePlay();
    }
};

window.prevTrack = function () {
    if (currentTrackIndex > 0) {
        loadTrack(currentTrackIndex - 1);
        safePlay();
    }
};

/* SAVE */

function startAutoSave() {

    if (saveTimer) clearInterval(saveTimer);

    saveTimer = setInterval(function () {

        if (!audio.duration) return;

        const state = {
            trackIndex: currentTrackIndex,
            currentTime: audio.currentTime,
            volume: audio.volume
        };

        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));

    }, 3000);
}

/* RESTORE */

function restoreState() {

    const saved = localStorage.getItem(STORAGE_KEY);

    if (!saved) {
        loadTrack(0);
        return;
    }

    const state = JSON.parse(saved);

    loadTrack(state.trackIndex, state.currentTime);
}

/* UI */

function updateUI() {

    const icon = document.getElementById('playIcon');

    if (isPlaying) {
        icon.src = "/assets/icons/pause.svg";
    } else {
        icon.src = "/assets/icons/play.svg";
    }
}

function formatTime(sec) {
    sec = Math.floor(sec || 0);
    let min = Math.floor(sec / 60);
    let s = sec % 60;
    return min + ":" + (s < 10 ? "0" : "") + s;
}

});
