<?php

require_once __DIR__ . '/env.php';

function db_is_enabled(): bool
{
    $driver = strtolower((string) (getenv('DB_DRIVER') ?: ''));
    return in_array($driver, ['mysql', 'mariadb'], true);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) (getenv('DB_HOST') ?: 'localhost');
    $port = (string) (getenv('DB_PORT') ?: '3306');
    $name = (string) (getenv('DB_NAME') ?: '');
    $user = (string) (getenv('DB_USER') ?: '');
    $pass = (string) (getenv('DB_PASSWORD') ?: '');
    $charset = (string) (getenv('DB_CHARSET') ?: 'utf8mb4');

    if ($name === '' || $user === '') {
        throw new RuntimeException('DB_NAME/DB_USER are not configured');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_fetch_all_issued_codes(): array
{
    $stmt = db()->query('SELECT code, show_slug, created_at, source, invoice, amount, is_test FROM issued_codes');
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[(string) $row['code']] = [
            'show' => (string) ($row['show_slug'] ?? ''),
            'created_at' => (int) ($row['created_at'] ?? 0),
            'source' => (string) ($row['source'] ?? ''),
            'invoice' => (string) ($row['invoice'] ?? ''),
            'amount' => (string) ($row['amount'] ?? ''),
            'is_test' => (int) ($row['is_test'] ?? 0) === 1,
        ];
    }

    return $result;
}

function db_replace_all_issued_codes(array $data): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM issued_codes');
        $stmt = $pdo->prepare('INSERT INTO issued_codes (code, show_slug, created_at, source, invoice, amount, is_test) VALUES (:code, :show_slug, :created_at, :source, :invoice, :amount, :is_test)');
        foreach ($data as $code => $entry) {
            $stmt->execute([
                ':code' => (string) $code,
                ':show_slug' => (string) ($entry['show'] ?? ''),
                ':created_at' => (int) ($entry['created_at'] ?? 0),
                ':source' => (string) ($entry['source'] ?? ''),
                ':invoice' => (string) ($entry['invoice'] ?? ''),
                ':amount' => (string) ($entry['amount'] ?? ''),
                ':is_test' => !empty($entry['is_test']) ? 1 : 0,
            ]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function db_fetch_all_activations(): array
{
    $stmt = db()->query('SELECT code, show_slug, device_id, device_fingerprint, activated_at, created_at, updated_at FROM code_activations');
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $result[(string) $row['code']] = [
            'show' => (string) ($row['show_slug'] ?? ''),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'device_fingerprint' => (string) ($row['device_fingerprint'] ?? ''),
            'activated_at' => (int) ($row['activated_at'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }

    return $result;
}

function db_replace_all_activations(array $data): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM code_activations');
        $stmt = $pdo->prepare('INSERT INTO code_activations (code, show_slug, device_id, device_fingerprint, activated_at, created_at, updated_at) VALUES (:code, :show_slug, :device_id, :device_fingerprint, :activated_at, :created_at, :updated_at)');
        foreach ($data as $code => $entry) {
            $stmt->execute([
                ':code' => (string) $code,
                ':show_slug' => (string) ($entry['show'] ?? ''),
                ':device_id' => (string) ($entry['device_id'] ?? ''),
                ':device_fingerprint' => (string) ($entry['device_fingerprint'] ?? ''),
                ':activated_at' => (int) ($entry['activated_at'] ?? 0),
                ':created_at' => (int) ($entry['created_at'] ?? 0),
                ':updated_at' => (int) ($entry['updated_at'] ?? 0),
            ]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function db_fetch_shows(): array
{
    $stmt = db()->query('SELECT slug, title, cover, description, authors, price_rub, enabled, code_prefixes_json FROM shows');
    $rows = $stmt->fetchAll();
    $result = [];

    foreach ($rows as $row) {
        $prefixes = json_decode((string) ($row['code_prefixes_json'] ?? '[]'), true);
        if (!is_array($prefixes)) {
            $prefixes = [];
        }

        $result[(string) $row['slug']] = [
            'title' => (string) ($row['title'] ?? ''),
            'cover' => (string) ($row['cover'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'authors' => (string) ($row['authors'] ?? ''),
            'price_rub' => (int) ($row['price_rub'] ?? 0),
            'enabled' => (int) ($row['enabled'] ?? 1) === 1,
            'code_prefixes' => $prefixes,
        ];
    }

    return $result;
}

function db_upsert_show(string $slug, array $show): bool
{
    $sql = 'INSERT INTO shows (slug, title, cover, description, authors, price_rub, enabled, code_prefixes_json)
            VALUES (:slug, :title, :cover, :description, :authors, :price_rub, :enabled, :code_prefixes_json)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                cover = VALUES(cover),
                description = VALUES(description),
                authors = VALUES(authors),
                price_rub = VALUES(price_rub),
                enabled = VALUES(enabled),
                code_prefixes_json = VALUES(code_prefixes_json)';

    try {
        $stmt = db()->prepare($sql);
        return $stmt->execute([
            ':slug' => $slug,
            ':title' => (string) ($show['title'] ?? ''),
            ':cover' => (string) ($show['cover'] ?? ''),
            ':description' => (string) ($show['description'] ?? ''),
            ':authors' => (string) ($show['authors'] ?? ''),
            ':price_rub' => (int) ($show['price_rub'] ?? 0),
            ':enabled' => !empty($show['enabled']) ? 1 : 0,
            ':code_prefixes_json' => json_encode($show['code_prefixes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

function db_get_settings(): array
{
    $stmt = db()->query('SELECT `key`, value_json FROM app_settings');
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string) ($row['value_json'] ?? 'null'), true);
        $result[(string) $row['key']] = $decoded;
    }
    return $result;
}

function db_set_setting(string $key, $value): bool
{
    $sql = 'INSERT INTO app_settings (`key`, value_json) VALUES (:key, :value_json)
            ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)';
    try {
        $stmt = db()->prepare($sql);
        return $stmt->execute([
            ':key' => $key,
            ':value_json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        return false;
    }
}
