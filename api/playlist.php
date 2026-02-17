<?php

header('Content-Type: application/json; charset=utf-8');

$shows = require __DIR__ . '/shows.php';

$show = $_GET['show'] ?? 'volkov-golos';

if (!isset($shows[$show])) {
    http_response_code(404);
    echo json_encode(['error' => 'Show not found']);
    exit;
}

$showMeta = $shows[$show];
$basePath = $_SERVER['DOCUMENT_ROOT'] . "/storage/audio/$show";
$baseUrl = "/storage/audio/$show";

if (!is_dir($basePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Audio folder not found']);
    exit;
}

$files = scandir($basePath) ?: [];
$audioFiles = [];

foreach ($files as $file) {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'mp3') {
        continue;
    }
    $audioFiles[] = $file;
}

natcasesort($audioFiles);
$audioFiles = array_values($audioFiles);

$tracks = [];
$id = 1;

foreach ($audioFiles as $file) {
    $title = pathinfo($file, PATHINFO_FILENAME);

    $tracks[] = [
        'id' => $id++,
        'title' => $title,
        'file' => "$baseUrl/$file"
    ];
}

echo json_encode([
    'show' => $show,
    'title' => $showMeta['title'],
    'cover' => $showMeta['cover'],
    'description' => $showMeta['description'],
    'authors' => $showMeta['authors'],
    'tracks' => $tracks
]);