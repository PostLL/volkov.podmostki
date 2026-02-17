<?php

$show = $_GET['show'] ?? 'volkov-golos';

$basePath = $_SERVER['DOCUMENT_ROOT'] . "/storage/audio/$show";
$baseUrl  = "/storage/audio/$show";

if (!is_dir($basePath)) {
    http_response_code(404);
    echo json_encode(["error" => "Show not found"]);
    exit;
}

$files = scandir($basePath);

$tracks = [];
$id = 1;

foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'mp3') {
        $tracks[] = [
            "id" => $id++,
            "title" => pathinfo($file, PATHINFO_FILENAME),
            "file" => "$baseUrl/$file"
        ];
    }
}

usort($tracks, function($a, $b) {
    return strcmp($a['file'], $b['file']);
});

echo json_encode([
    "title" => "Голос Волкова",
    "cover" => "/storage/covers/volkov-golos.jpg",
    "tracks" => $tracks
]);
