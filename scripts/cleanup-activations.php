<?php
/**
 * Скрипт для CRON: очистка активаций, истекших более 14 дней назад.
 *
 * Запуск вручную:
 * php scripts/cleanup-activations.php
 */

require __DIR__ . '/../api/code-lib.php';

$result = run_cleanup_activations();

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
