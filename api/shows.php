<?php

require_once __DIR__ . '/db.php';

$defaultShows = [
    'volkov-golos' => [
        'title' => 'Волков. Голос',
        'cover' => '/storage/covers/volkov-golos.jpg',
        'description' => 'Иммерсивный аудиоспектакль о памяти, выборе и цене собственного голоса.',
        'authors' => 'Режиссёр: Команда Подмостки · Текст: Авторский отдел · Звук: Sound Lab',
        'price_rub' => 590,
        'enabled' => true,
        'code_prefixes' => ['G']
    ]
];

if (db_is_enabled()) {
    try {
        $fromDb = db_fetch_shows();
        if (!empty($fromDb)) {
            return $fromDb;
        }
    } catch (Throwable $e) {
        // fallback to file/default
    }
}

$storagePath = __DIR__ . '/../storage/shows.json';
if (!file_exists($storagePath)) {
    return $defaultShows;
}

$raw = file_get_contents($storagePath);
if ($raw === false || $raw === '') {
    return $defaultShows;
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    return $defaultShows;
}

foreach ($decoded as $slug => $meta) {
    if (!is_array($meta)) {
        unset($decoded[$slug]);
        continue;
    }

    if (!isset($meta['enabled'])) {
        $decoded[$slug]['enabled'] = true;
    }

    if (!isset($meta['price_rub'])) {
        $decoded[$slug]['price_rub'] = 0;
    }
}

return $decoded;
