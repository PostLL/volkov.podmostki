<?php
require __DIR__ . '/api/env.php';
require __DIR__ . '/api/db.php';

$adminKey = getenv('PODMOSTKI_ADMIN_KEY') ?: 'CHANGE_ME_NOW';
$key = $_GET['key'] ?? ($_POST['key'] ?? '');

if ($adminKey === 'CHANGE_ME_NOW' || $key !== $adminKey) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $authors = trim((string) ($_POST['authors'] ?? ''));
    $price = (int) ($_POST['price_rub'] ?? 0);
    $prefix = strtoupper(trim((string) ($_POST['code_prefix'] ?? '')));

    if (!preg_match('/^[a-z0-9\-]+$/', $slug) || $title === '' || $price <= 0 || !preg_match('/^[A-Z]$/', $prefix)) {
        $message = 'Проверьте slug/title/price/prefix.';
    } else {
        $showsPath = __DIR__ . '/storage/shows.json';
        $shows = require __DIR__ . '/api/shows.php';

        $coverDir = __DIR__ . '/storage/covers';
        $audioDir = __DIR__ . '/storage/audio/' . $slug;
        if (!is_dir($coverDir)) {
            mkdir($coverDir, 0775, true);
        }
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0775, true);
        }

        $coverWebPath = '/storage/covers/' . $slug . '.jpg';
        if (isset($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
            move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__ . '/storage/covers/' . $slug . '.jpg');
        }

        if (isset($_FILES['tracks']) && is_array($_FILES['tracks']['tmp_name'])) {
            foreach ($_FILES['tracks']['tmp_name'] as $idx => $tmp) {
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $name = (string) ($_FILES['tracks']['name'][$idx] ?? '');
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'mp3') {
                    continue;
                }
                move_uploaded_file($tmp, $audioDir . '/' . basename($name));
            }
        }

        $shows[$slug] = [
            'title' => $title,
            'cover' => $coverWebPath,
            'description' => $description,
            'authors' => $authors,
            'price_rub' => $price,
            'enabled' => true,
            'code_prefixes' => [$prefix]
        ];

        if (db_is_enabled()) {
            $ok = db_upsert_show($slug, $shows[$slug]);
            $message = $ok ? ('Спектакль добавлен: ' . $slug) : 'Не удалось сохранить спектакль в БД';
        } else {
            file_put_contents($showsPath, json_encode($shows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $message = 'Спектакль добавлен: ' . $slug;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Добавить спектакль</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="player">
    <header class="app-header app-header--entry">
        <div class="brand brand--entry">
            <img class="brand-logo brand-logo--entry" src="/assets/logo.png" alt="Лого компании">
        </div>
    </header>

    <section class="entry-card">
        <h2>Добавить спектакль</h2>
        <p class="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <form method="post" enctype="multipart/form-data" class="shop-controls">
            <input type="hidden" name="key" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
            <input class="code-input" name="slug" placeholder="slug, например novyi-spektakl" required>
            <input class="code-input" name="title" placeholder="Название спектакля" required>
            <textarea class="code-input" name="description" placeholder="Описание" style="min-height:100px"></textarea>
            <input class="code-input" name="authors" placeholder="Авторы">
            <input class="code-input" type="number" name="price_rub" min="1" placeholder="Цена в ₽" required>
            <input class="code-input" name="code_prefix" maxlength="1" placeholder="Префикс (1 буква), например N" required>
            <label class="shop-label">Обложка спектакля (jpg)</label>
            <input class="code-input" type="file" name="cover" accept="image/jpeg,image/jpg">
            <label class="shop-label">Треки mp3 (можно несколько)</label>
            <input class="code-input" type="file" name="tracks[]" accept="audio/mpeg,.mp3" multiple>
            <button class="entry-action" type="submit">Сохранить спектакль</button>
        </form>
    </section>
</div>
</body>
</html>
