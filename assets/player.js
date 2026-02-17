document.addEventListener('DOMContentLoaded', function () {
    const trackTitleEl = document.getElementById('trackTitle');
    const progressEl = document.getElementById('progress');
    const currentTimeEl = document.getElementById('currentTime');
    const durationEl = document.getElementById('duration');
    const playIconEl = document.getElementById('playIcon');
    const playerMessageEl = document.getElementById('playerMessage');
    const prevBtnEl = document.getElementById('prevBtn');
    const nextBtnEl = document.getElementById('nextBtn');
    const playlistEl = document.getElementById('playlist');
    const showTitleEl = document.getElementById('showTitle');
    const showCoverEl = document.getElementById('showCover');
    const showDescriptionEl = document.getElementById('showDescription');
    const showAuthorsEl = document.getElementById('showAuthors');

    const LAST_CODE_KEY = 'podmostki:lastCode';
    const DEVICE_ID_KEY = 'podmostki:deviceId';
    const PLAYER_GATE_KEY = 'podmostki:playerGate';

    const navEntries = performance.getEntriesByType('navigation');
    const navType = navEntries.length ? navEntries[0].type : '';

    if (navType === 'reload') {
        window.location.href = '/';
        return;
    }

    const gateAllowed = sessionStorage.getItem(PLAYER_GATE_KEY) === '1';
    sessionStorage.removeItem(PLAYER_GATE_KEY);
    if (!gateAllowed) {
        window.location.href = '/';
        return;
    }

    const deviceId = localStorage.getItem(DEVICE_ID_KEY) || '';
    if (!deviceId) {
        window.location.href = '/';
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const codeFromQuery = (urlParams.get('code') || '').toUpperCase();
    const showFromQuery = urlParams.get('show') || '';
    const code = codeFromQuery || (localStorage.getItem(LAST_CODE_KEY) || '').toUpperCase();

    if (!code || code.length !== 6) {
        window.location.href = '/';
        return;
    }

    localStorage.setItem(LAST_CODE_KEY, code);

    let showSlug = showFromQuery;
    let STORAGE_KEY = `podcastPlayerState:${showSlug || 'unknown'}`;

    let playlist = [];
    let currentTrackIndex = 0;
    let saveTimer = null;
    let isPlaying = false;
    let lastPersistedSecond = -1;

    const audio = new Audio();
    audio.preload = 'metadata';
    audio.playsInline = true;

    setMessage('Проверка доступа...');

    fetch(`/api/code-status.php?code=${encodeURIComponent(code)}&device_id=${encodeURIComponent(deviceId)}`)
        .then(function (res) { return res.json(); })
        .then(function (status) {
            if (status.status !== 'active' || !status.show) {
                window.location.href = '/';
                return;
            }

            showSlug = status.show;
            STORAGE_KEY = `podcastPlayerState:${showSlug}`;

            const url = new URL(window.location.href);
            url.searchParams.set('show', showSlug);
            url.searchParams.set('code', code);
            window.history.replaceState({}, '', url.toString());

            loadShow(showSlug);
        })
        .catch(function () {
            setMessage('Ошибка проверки доступа. Попробуйте зайти снова.');
        });

    function loadShow(slug) {
        setMessage('Загрузка плейлиста...');

        fetch(`/api/playlist.php?show=${encodeURIComponent(slug)}`)
            .then(function (res) {
                if (!res.ok) {
                    throw new Error(`Playlist request failed: ${res.status}`);
                }
                return res.json();
            })
            .then(function (data) {
                playlist = Array.isArray(data.tracks) ? data.tracks : [];

                showTitleEl.textContent = data.title || 'Аудиоспектакль';
                showCoverEl.src = data.cover || '';
                showDescriptionEl.textContent = data.description || '';
                showAuthorsEl.textContent = data.authors || '';

                if (!playlist.length) {
                    setMessage('Плейлист пуст.');
                    trackTitleEl.innerText = 'Нет доступных треков';
                    renderPlaylist();
                    updateButtonsState();
                    return;
                }

                restoreState();
                renderPlaylist();
                updateButtonsState();
                setMessage('Нажмите play для воспроизведения.');
            })
            .catch(function () {
                setMessage('Ошибка загрузки плейлиста. Проверьте интернет и попробуйте снова.');
                trackTitleEl.innerText = 'Ошибка загрузки';
            });
    }

    function renderPlaylist() {
        playlistEl.innerHTML = '';

        playlist.forEach(function (track, index) {
            const item = document.createElement('li');
            item.className = 'playlist-item' + (index === currentTrackIndex ? ' is-active' : '');
            item.textContent = `${index + 1}. ${track.title || `Трек ${index + 1}`}`;
            item.addEventListener('click', function () {
                loadTrack(index, 0);
                safePlay();
            });
            playlistEl.appendChild(item);
        });
    }

    function setMessage(message) {
        playerMessageEl.textContent = message || '';
    }

    function loadTrack(index, restoreTime = 0) {
        if (!playlist.length) return;

        if (index < 0) index = 0;
        if (index > playlist.length - 1) index = playlist.length - 1;

        currentTrackIndex = index;
        isPlaying = false;

        const track = playlist[index];

        audio.src = track.file;
        audio.load();

        trackTitleEl.innerText = track.title || `Трек ${index + 1}`;

        progressEl.value = 0;
        currentTimeEl.innerText = '0:00';
        durationEl.innerText = '0:00';
        progressEl.style.setProperty('--progress-percent', '0%');

        audio.onloadedmetadata = function () {
            durationEl.innerText = formatTime(audio.duration);

            if (restoreTime > 0 && restoreTime < audio.duration) {
                audio.currentTime = restoreTime;
                currentTimeEl.innerText = formatTime(audio.currentTime);
                syncProgress();
            }

            updateMediaSessionMetadata();
        };

        updateUI();
        updateButtonsState();
        renderPlaylist();
        persistState();
    }

    async function safePlay() {
        if (!audio.src) return;

        try {
            await audio.play();
            isPlaying = true;
            updateUI();
            startAutoSave();
            setMessage('');
        } catch (err) {
            isPlaying = false;
            updateUI();
            setMessage('Воспроизведение заблокировано браузером. Нажмите play ещё раз.');
        }
    }

    window.playPause = function () {
        if (!audio.src) return;

        if (isPlaying) {
            audio.pause();
            isPlaying = false;
            updateUI();
            persistState();
        } else {
            safePlay();
        }
    };

    window.nextTrack = function () {
        if (currentTrackIndex < playlist.length - 1) {
            loadTrack(currentTrackIndex + 1, 0);
            safePlay();
        }
    };

    window.prevTrack = function () {
        if (currentTrackIndex > 0) {
            loadTrack(currentTrackIndex - 1, 0);
            safePlay();
        }
    };

    function startAutoSave() {
        if (saveTimer) clearInterval(saveTimer);

        saveTimer = setInterval(function () {
            persistState();
        }, 3000);
    }

    function persistState(force = false) {
        if (!audio.src || !playlist.length) return;

        const secondNow = Math.floor(audio.currentTime || 0);
        if (!force && secondNow === lastPersistedSecond) return;

        const state = {
            trackIndex: currentTrackIndex,
            currentTime: audio.currentTime || 0,
            volume: audio.volume || 1,
            updatedAt: Date.now()
        };

        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        lastPersistedSecond = secondNow;
    }

    function restoreState() {
        const saved = localStorage.getItem(STORAGE_KEY);

        if (!saved) {
            loadTrack(0, 0);
            return;
        }

        try {
            const state = JSON.parse(saved);
            const safeIndex = Number.isInteger(state.trackIndex) ? state.trackIndex : 0;
            const safeTime = Number.isFinite(state.currentTime) ? state.currentTime : 0;

            if (Number.isFinite(state.volume)) {
                audio.volume = Math.max(0, Math.min(1, state.volume));
            }

            loadTrack(safeIndex, safeTime);
        } catch (e) {
            loadTrack(0, 0);
        }
    }

    function updateUI() {
        playIconEl.src = isPlaying ? '/assets/icons/pause.svg' : '/assets/icons/play.svg';

        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
        }
    }

    function updateButtonsState() {
        const noTracks = !playlist.length;
        prevBtnEl.disabled = noTracks || currentTrackIndex <= 0;
        nextBtnEl.disabled = noTracks || currentTrackIndex >= playlist.length - 1;
    }

    function syncProgress() {
        if (!audio.duration) return;

        const percent = (audio.currentTime / audio.duration) * 100;
        progressEl.value = percent;
        progressEl.style.setProperty('--progress-percent', `${percent}%`);
    }

    function formatTime(sec) {
        sec = Math.floor(sec || 0);
        const min = Math.floor(sec / 60);
        const s = sec % 60;
        return min + ':' + (s < 10 ? '0' : '') + s;
    }

    audio.addEventListener('timeupdate', function () {
        if (!audio.duration) return;

        syncProgress();
        currentTimeEl.innerText = formatTime(audio.currentTime);
    });

    audio.addEventListener('pause', function () {
        isPlaying = false;
        updateUI();
        persistState();
    });

    audio.addEventListener('ended', function () {
        persistState(true);

        if (currentTrackIndex < playlist.length - 1) {
            window.nextTrack();
        } else {
            isPlaying = false;
            updateUI();
        }
    });

    audio.addEventListener('error', function () {
        setMessage('Ошибка загрузки аудио. Проверьте соединение или попробуйте другой трек.');
    });

    progressEl.addEventListener('input', function () {
        if (!audio.duration) return;

        const newTime = (Number(this.value) / 100) * audio.duration;
        audio.currentTime = newTime;
        currentTimeEl.innerText = formatTime(audio.currentTime);
        syncProgress();
        persistState(true);
    });

    window.addEventListener('pagehide', function () {
        persistState(true);
    });

    window.addEventListener('beforeunload', function () {
        persistState(true);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            persistState(true);
        }
    });

    function updateMediaSessionMetadata() {
        if (!('mediaSession' in navigator) || !playlist.length) return;

        const track = playlist[currentTrackIndex];
        navigator.mediaSession.metadata = new MediaMetadata({
            title: track.title || `Трек ${currentTrackIndex + 1}`,
            artist: 'Аудиоспектакль',
            album: showSlug
        });
    }

    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', function () {
            safePlay();
        });

        navigator.mediaSession.setActionHandler('pause', function () {
            audio.pause();
            isPlaying = false;
            updateUI();
            persistState(true);
        });

        navigator.mediaSession.setActionHandler('previoustrack', function () {
            window.prevTrack();
        });

        navigator.mediaSession.setActionHandler('nexttrack', function () {
            window.nextTrack();
        });

        navigator.mediaSession.setActionHandler('seekto', function (details) {
            if (!audio.duration) return;
            if (!Number.isFinite(details.seekTime)) return;

            audio.currentTime = Math.max(0, Math.min(audio.duration, details.seekTime));
            syncProgress();
            persistState(true);
        });
    }
});