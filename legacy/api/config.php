<?php
declare(strict_types=1);

$localConfig = [];
$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $loaded = require $localConfigFile;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

function legacy_env_value(string $key): ?string
{
    static $env = null;

    $runtime = getenv($key);
    if ($runtime !== false && $runtime !== '') {
        return (string) $runtime;
    }

    if ($env === null) {
        $env = [];
        $envPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envPath)) {
            foreach ((array) file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                    $value = substr($value, 1, -1);
                }
                $env[$name] = $value;
            }
        }
    }

    return isset($env[$key]) && $env[$key] !== '' ? (string) $env[$key] : null;
}

defined('DB_HOST') || define('DB_HOST', (string) ($localConfig['db_host'] ?? getenv('RECRU_DB_HOST') ?: legacy_env_value('DB_HOST') ?: '127.0.0.1'));
defined('DB_NAME') || define('DB_NAME', (string) ($localConfig['db_name'] ?? getenv('RECRU_DB_NAME') ?: legacy_env_value('DB_DATABASE') ?: 'recru_laravel'));
defined('DB_USER') || define('DB_USER', (string) ($localConfig['db_user'] ?? getenv('RECRU_DB_USER') ?: legacy_env_value('DB_USERNAME') ?: 'root'));
defined('DB_PASS') || define('DB_PASS', (string) ($localConfig['db_pass'] ?? getenv('RECRU_DB_PASS') ?: legacy_env_value('DB_PASSWORD') ?: ''));
defined('APP_NAME') || define('APP_NAME', (string) ($localConfig['app_name'] ?? getenv('RECRU_APP_NAME') ?: legacy_env_value('APP_NAME') ?: 'KDXJobs'));
defined('APP_EMAIL_FROM') || define('APP_EMAIL_FROM', (string) ($localConfig['app_email_from'] ?? getenv('RECRU_APP_EMAIL_FROM') ?: legacy_env_value('MAIL_FROM_ADDRESS') ?: 'no-reply@kdxjobs.local'));
defined('APP_EMAIL_REPLY_TO') || define('APP_EMAIL_REPLY_TO', (string) ($localConfig['app_email_reply_to'] ?? getenv('RECRU_APP_EMAIL_REPLY_TO') ?: legacy_env_value('MAIL_FROM_ADDRESS') ?: 'support@kdxjobs.local'));
defined('OPENAI_API_KEY') || define('OPENAI_API_KEY', (string) ($localConfig['openai_api_key'] ?? legacy_env_value('OPENAI_API_KEY') ?? ''));
defined('OPENAI_CV_MODEL') || define('OPENAI_CV_MODEL', (string) ($localConfig['openai_cv_model'] ?? legacy_env_value('OPENAI_CV_MODEL') ?? 'gpt-5.2'));
defined('OPENAI_BASE_URL') || define('OPENAI_BASE_URL', (string) ($localConfig['openai_base_url'] ?? legacy_env_value('OPENAI_BASE_URL') ?? 'https://api.openai.com/v1'));
defined('PDFTOTEXT_BIN') || define('PDFTOTEXT_BIN', (string) ($localConfig['pdftotext_bin'] ?? legacy_env_value('PDFTOTEXT_BIN') ?? ''));
defined('OCRMYPDF_BIN') || define('OCRMYPDF_BIN', (string) ($localConfig['ocrmypdf_bin'] ?? legacy_env_value('OCRMYPDF_BIN') ?? ''));
defined('TESSERACT_BIN') || define('TESSERACT_BIN', (string) ($localConfig['tesseract_bin'] ?? legacy_env_value('TESSERACT_BIN') ?? ''));
defined('TESSERACT_LANGS') || define('TESSERACT_LANGS', (string) ($localConfig['tesseract_langs'] ?? legacy_env_value('TESSERACT_LANGS') ?? 'eng'));
defined('MAX_UPLOAD_BYTES') || define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
defined('MAX_CV_UPLOAD_BYTES') || define('MAX_CV_UPLOAD_BYTES', 2 * 1024 * 1024);

function apply_security_headers(bool $isApi = false): void
{
    header_remove('X-Powered-By');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Resource-Policy: same-origin');

    if ($isApi) {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
        return;
    }

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' https://cdn.ckeditor.com https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "img-src 'self' data:; "
        . "font-src 'self' data:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return $remote !== '' ? preg_replace('/[^a-fA-F0-9:\.\-]/', '_', $remote) : 'unknown';
}

function rate_limit_directory(): string
{
    $baseDir = defined('LEGACY_STORAGE_ROOT') ? (string) LEGACY_STORAGE_ROOT : dirname(__DIR__);
    $dir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate_limits';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $indexFile = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($indexFile)) {
        file_put_contents($indexFile, '');
    }

    return $dir;
}

function enforce_rate_limit(string $action, int $maxAttempts, int $windowSeconds, bool $json = false): void
{
    $safeAction = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $action) ?: 'action';
    $bucket = hash('sha256', $safeAction . '|' . client_ip());
    $file = rate_limit_directory() . DIRECTORY_SEPARATOR . $bucket . '.json';
    $now = time();
    $state = ['start' => $now, 'count' => 0];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
            $state['start'] = (int) $decoded['start'];
            $state['count'] = (int) $decoded['count'];
        }
    }

    if (($now - $state['start']) >= $windowSeconds) {
        $state = ['start' => $now, 'count' => 0];
    }

    $state['count']++;
    file_put_contents($file, json_encode($state, JSON_UNESCAPED_SLASHES));

    if ($state['count'] <= $maxAttempts) {
        return;
    }

    $message = 'Too many requests. Please wait a little and try again.';
    if ($json) {
        json_response(['ok' => false, 'error' => $message], 429);
    }

    throw new RuntimeException($message);
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return $_POST;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function require_fields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            json_response(['ok' => false, 'error' => "Missing field: {$field}"], 422);
        }
    }
}

function ensure_upload_directory_security(string $uploadDir): void
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $htaccess = $uploadDir . DIRECTORY_SEPARATOR . '.htaccess';
    file_put_contents(
        $htaccess,
        "Options -Indexes\r\n"
        . "Require all denied\r\n"
        . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|jsp|asp|aspx|sh)$\">\r\n"
        . "    Require all denied\r\n"
        . "</FilesMatch>\r\n"
        . "AddType text/plain .php .phtml .php3 .php4 .php5 .phar .cgi .pl .py .jsp .asp .aspx .sh\r\n"
    );

    $indexFile = $uploadDir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($indexFile)) {
        file_put_contents($indexFile, '');
    }
}

function upload_file(string $field, array $allowedExtensions, ?int $maxBytes = null): ?string
{
    if (!isset($_FILES[$field])) {
        return null;
    }

    $uploadError = (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file is larger than the server allows.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file is larger than the form allows.',
            UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please choose the PDF again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload temp folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
        ];
        throw new RuntimeException($messages[$uploadError] ?? 'The file upload failed.');
    }

    $maxBytes ??= MAX_UPLOAD_BYTES;
    $size = (int) ($_FILES[$field]['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Uploaded file is too large. CV files must be 2 MB or smaller.');
    }

    $extension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $allowedMimeTypes = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];
    $tmpName = (string) ($_FILES[$field]['tmp_name'] ?? '');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $validMimeTypes = $allowedMimeTypes[$extension] ?? [];
    if ($mimeType === '' || !in_array($mimeType, $validMimeTypes, true)) {
        throw new RuntimeException('Uploaded file content does not match the file type.');
    }

    $uploadDir = defined('UPLOAD_PUBLIC_ROOT') ? (string) UPLOAD_PUBLIC_ROOT : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    ensure_upload_directory_security($uploadDir);

    $filename = uniqid('upload_', true) . '.' . $extension;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $target)) {
        if (!is_file($tmpName) || (!@rename($tmpName, $target) && !@copy($tmpName, $target))) {
            throw new RuntimeException('Could not save uploaded file.');
        }
    }

    return 'uploads/' . $filename;
}
