Как добавить новый спектакль с минимумом правок
Добавить папку аудио: storage/audio/<new-show-slug>/...mp3. 

Добавить мета в api/shows.php (title/cover/description/authors). 

Добавить префикс кода в CODE_MAP в assets/player.js (например N: 'novyi-spektakl'). 
const CODE_MAP = {
    G: 'volkov-golos'
};
function mapCodeToShow(code) {
    if (!code) return null;
    const prefix = String(code).trim().charAt(0).toUpperCase();
    return CODE_MAP[prefix] || null;
}