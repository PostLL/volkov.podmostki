<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/env.php';
require __DIR__ . '/db.php';

if (!db_is_enabled()) {
    echo json_encode([
        'ok' => false,
        'message' => 'DB выключена (DB_DRIVER не mysql/mariadb).',
        'db_driver' => getenv('DB_DRIVER') ?: ''
    ]);
    exit;
}

try {
    $pdo = db();
    $info = [
        'db_host' => getenv('DB_HOST') ?: 'localhost',
        'db_port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: '',
        'db_user' => getenv('DB_USER') ?: '',
    ];

    $tables = ['issued_codes', 'code_activations', 'shows', 'app_settings'];
    $tableStatus = [];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);
        $tableStatus[$table] = $stmt->fetchColumn() !== false;
    }

    echo json_encode([
        'ok' => true,
        'message' => 'DB подключена',
        'connection' => $info,
        'tables' => $tableStatus,
        'fallback_to_file' => ((string) (getenv('DB_FALLBACK_TO_FILE') ?: '0')) === '1'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Ошибка подключения к БД',
        'error' => $e->getMessage(),
        'connection' => [
            'db_host' => getenv('DB_HOST') ?: 'localhost',
            'db_port' => getenv('DB_PORT') ?: '3306',
            'db_name' => getenv('DB_NAME') ?: '',
            'db_user' => getenv('DB_USER') ?: '',
        ]
    ]);
}
