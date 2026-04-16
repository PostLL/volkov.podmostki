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
$trackCoversPath = $_SERVER['DOCUMENT_ROOT'] . "/storage/covers/$show";
$trackCoversUrl = "/storage/covers/$show";

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
$currentCover = $showMeta['cover'];

foreach ($audioFiles as $file) {
    $title = pathinfo($file, PATHINFO_FILENAME);
    $title = preg_replace('/^\s*\d+([\s._-]+)?/u', '', $title);
    $title = trim((string) $title);

    if ($title === '') {
        $title = pathinfo($file, PATHINFO_FILENAME);
    }

    $trackCoverCandidatePath = $trackCoversPath . '/' . $id . '.jpg';
    if (is_file($trackCoverCandidatePath)) {
        $currentCover = $trackCoversUrl . '/' . $id . '.jpg';
    }

    $tracks[] = [
        'id' => $id++,
        'title' => $title,
        'file' => "$baseUrl/$file",
        'cover' => $currentCover
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