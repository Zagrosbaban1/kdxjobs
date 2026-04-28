<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
apply_security_headers(true);

$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$lockFile = __DIR__ . '/install.lock';

if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    json_response(['ok' => false, 'error' => 'Installer is not available publicly.'], 403);
}

if (is_file($lockFile)) {
    json_response(['ok' => false, 'error' => 'Installer has already been locked. Remove api/install.lock manually only if you intentionally need to reinstall.'], 403);
}

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($schema === false) {
        json_response(['ok' => false, 'error' => 'Could not read database/schema.sql'], 500);
    }

    $pdo->exec($schema);
    file_put_contents($lockFile, 'Locked at ' . date(DATE_ATOM));
    json_response(['ok' => true, 'message' => 'Database installed and seeded.']);
} catch (Throwable $exception) {
    json_response(['ok' => false, 'error' => $exception->getMessage()], 500);
}
