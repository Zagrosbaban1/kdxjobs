<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
require __DIR__ . '/api/config.php';
apply_security_headers();

function app_base_path(): string
{
    static $basePath = null;

    if (is_string($basePath)) {
        return $basePath;
    }

    if (defined('APP_BASE_PATH_OVERRIDE')) {
        $basePath = (string) APP_BASE_PATH_OVERRIDE;
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = trim(str_replace('\\', '/', dirname($scriptName)), '/.');
    if ($dir === '') {
        $basePath = '';
        return $basePath;
    }

    $basePath = '/' . basename(__DIR__);
    return $basePath;
}

function asset_url(string $path): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function enforce_canonical_base_path(): void
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $actualBase = '/' . basename(__DIR__);
    $expectedPrefix = $actualBase . '/';

    if ($actualBase !== '' && $scriptName !== '' && stripos($scriptName, $expectedPrefix) === 0 && strpos($scriptName, $expectedPrefix) !== 0) {
        $target = $actualBase . substr($scriptName, strlen($actualBase));
        if ($query !== '') {
            $target .= '?' . $query;
        }
        header('Location: ' . $target, true, 301);
        exit;
    }
}

enforce_canonical_base_path();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token_value(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token_value()) . '">';
}

function app_setting(string $key, string $default = ''): string
{
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : $default;
    } catch (Throwable) {
        return $default;
    }
}

function set_app_setting(string $key, string $value): void
{
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([
        ':setting_key' => $key,
        ':setting_value' => $value,
    ]);
}

function ai_matching_mode(): string
{
    $sessionMode = (string) ($_SESSION['ai_matching_mode'] ?? '');
    if (in_array($sessionMode, ['strict', 'balanced', 'flexible'], true)) {
        return $sessionMode;
    }

    $mode = app_setting('ai_matching_mode', 'balanced');
    return in_array($mode, ['strict', 'balanced', 'flexible'], true) ? $mode : 'balanced';
}

function ai_matching_mode_label(?string $mode = null): string
{
    $mode ??= ai_matching_mode();
    return match ($mode) {
        'strict' => 'Strict',
        'flexible' => 'Flexible',
        default => 'Balanced',
    };
}


function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');

    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('Security token mismatch. Please refresh the page and try again.');
    }
}

function rich_text_html(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (!str_contains($value, '<')) {
        return nl2br(h($value));
    }

    $allowed = '<p><br><ul><ol><li><strong><b><em><i><u><h3><h4><blockquote>';
    $clean = strip_tags($value, $allowed);
    $clean = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $clean) ?? $clean;

    return $clean;
}

function uploaded_file_label(?string $path, string $fallback = 'Saved CV'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return $fallback;
    }

    $name = basename($path);
    return $name !== '' ? $name : $fallback;
}

function download_url(string $path): string
{
    return app_base_path() . '/index.php?' . http_build_query([
        'page' => 'download',
        'file' => $path,
    ]);
}

function cv_link_html(?string $path, string $label = 'View CV'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '<span class="tiny muted">No CV</span>';
    }

    return '<a class="file-link" href="' . h(download_url($path)) . '" target="_blank" rel="noopener">' . h($label) . '</a>';
}

function validate_password_strength(string $password): void
{
    if (strlen($password) < 10) {
        throw new RuntimeException('Password must be at least 10 characters long.');
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new RuntimeException('Password must include uppercase letters, lowercase letters, and numbers.');
    }
}

function is_allowed_application_status(string $status): bool
{
    return in_array($status, ['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'], true);
}

function can_access_uploaded_file(PDO $pdo, string $path): bool
{
    $path = trim($path);
    if ($path === '' || !isset($_SESSION['user']['id'])) {
        return false;
    }

    $userId = (int) $_SESSION['user']['id'];
    $role = (string) ($_SESSION['user']['role'] ?? '');

    $userStmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND (cv_file = :path OR logo_file = :path) LIMIT 1');
    $userStmt->execute([':id' => $userId, ':path' => $path]);
    if ($userStmt->fetchColumn()) {
        return true;
    }

    if (in_array($role, ['admin', 'superadmin'], true)) {
        $adminStmt = $pdo->prepare('SELECT id FROM applications WHERE cv_file = :path LIMIT 1');
        $adminStmt->execute([':path' => $path]);
        if ($adminStmt->fetchColumn()) {
            return true;
        }
    }

    if ($role === 'company') {
        $companyStmt = $pdo->prepare(
            'SELECT a.id
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN companies c ON c.id = j.company_id
             WHERE a.cv_file = :path AND c.user_id = :user_id
             LIMIT 1'
        );
        $companyStmt->execute([':path' => $path, ':user_id' => $userId]);
        if ($companyStmt->fetchColumn()) {
            return true;
        }
    }

    if ($role === 'admin') {
        $recruiterStmt = $pdo->prepare(
            'SELECT a.id
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             WHERE a.cv_file = :path AND j.recruiter_id = :user_id
             LIMIT 1'
        );
        $recruiterStmt->execute([':path' => $path, ':user_id' => $userId]);
        if ($recruiterStmt->fetchColumn()) {
            return true;
        }
    }

    $candidateStmt = $pdo->prepare(
        'SELECT id FROM applications WHERE cv_file = :path AND (user_id = :user_id OR applicant_email = :email) LIMIT 1'
    );
    $candidateStmt->execute([
        ':path' => $path,
        ':user_id' => $userId,
        ':email' => (string) ($_SESSION['user']['email'] ?? ''),
    ]);

    return (bool) $candidateStmt->fetchColumn();
}

function serve_uploaded_file(PDO $pdo, string $relativePath): never
{
    $relativePath = trim(str_replace('\\', '/', $relativePath));
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
        http_response_code(404);
        exit('File not found.');
    }

    if (!can_access_uploaded_file($pdo, $relativePath)) {
        http_response_code(403);
        exit('You are not allowed to access this file.');
    }

    $filePath = upload_absolute_path($relativePath);
    if (!$filePath) {
        http_response_code(404);
        exit('File not found.');
    }

    header_remove('Content-Security-Policy');
    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) filesize($filePath));
    header('Content-Disposition: inline; filename="' . rawurlencode(basename($filePath)) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

function add_notification(PDO $pdo, int $userId, string $title, string $body, string $link = ''): void
{
    if ($userId <= 0) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, body, link) VALUES (:user_id, :title, :body, :link)');
    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':body' => $body,
        ':link' => $link ?: null,
    ]);
}

function send_app_email(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $from = defined('APP_EMAIL_FROM') ? APP_EMAIL_FROM : 'no-reply@kdxjobs.local';
    $replyTo = defined('APP_EMAIL_REPLY_TO') ? APP_EMAIL_REPLY_TO : $from;
    $appName = defined('APP_NAME') ? APP_NAME : 'KDXJobs';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $appName . ' <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion(),
    ];

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function app_email_greeting(?string $name, string $fallback = 'there'): string
{
    $name = trim((string) $name);
    return $name !== '' ? $name : $fallback;
}

function send_application_status_email(array $applicationInfo, string $status): void
{
    $to = (string) ($applicationInfo['applicant_email'] ?? '');
    if ($to === '') {
        return;
    }

    $jobTitle = (string) ($applicationInfo['job_title'] ?? 'your application');
    $candidateName = app_email_greeting($applicationInfo['applicant_name'] ?? null);
    $subject = 'KDXJobs: application status updated';
    $body = "Hello {$candidateName},\n\n"
        . "Your application for {$jobTitle} is now marked as {$status}.\n\n"
        . "You can sign in to KDXJobs to review the latest update and next steps.\n\n"
        . "KDXJobs Team";

    send_app_email($to, $subject, $body);
}

function send_interview_scheduled_email(array $applicationInfo, string $when, string $location = '', string $note = ''): void
{
    $to = (string) ($applicationInfo['applicant_email'] ?? '');
    if ($to === '') {
        return;
    }

    $jobTitle = (string) ($applicationInfo['job_title'] ?? 'your application');
    $candidateName = app_email_greeting($applicationInfo['applicant_name'] ?? null);
    $body = "Hello {$candidateName},\n\n"
        . "Your interview for {$jobTitle} has been scheduled for {$when}.\n";

    if ($location !== '') {
        $body .= "\nLocation / link: {$location}\n";
    }

    if ($note !== '') {
        $body .= "\nNote: {$note}\n";
    }

    $body .= "\nPlease sign in to KDXJobs to view the latest details.\n\nKDXJobs Team";

    send_app_email($to, 'KDXJobs: interview scheduled', $body);
}

function send_service_reply_email(array $applicationInfo): void
{
    $to = (string) ($applicationInfo['applicant_email'] ?? '');
    if ($to === '') {
        return;
    }

    $jobTitle = (string) ($applicationInfo['job_title'] ?? 'your application');
    $candidateName = app_email_greeting($applicationInfo['applicant_name'] ?? null);
    $body = "Hello {$candidateName},\n\n"
        . "You have a new service center reply about {$jobTitle}.\n\n"
        . "Please sign in to KDXJobs to read the latest message.\n\n"
        . "KDXJobs Team";

    send_app_email($to, 'KDXJobs: new service reply', $body);
}

function send_application_received_emails(PDO $pdo, int $applicationId): void
{
    $stmt = $pdo->prepare(
        'SELECT a.applicant_name, a.applicant_email, j.title AS job_title, j.company_id, j.recruiter_id,
                company_user.email AS company_email, recruiter.email AS recruiter_email
         FROM applications a
         JOIN jobs j ON j.id = a.job_id
         LEFT JOIN companies c ON c.id = j.company_id
         LEFT JOIN users company_user ON company_user.id = c.user_id
         LEFT JOIN users recruiter ON recruiter.id = j.recruiter_id
         WHERE a.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $applicationId]);
    $info = $stmt->fetch();
    if (!$info) {
        return;
    }

    $jobTitle = (string) ($info['job_title'] ?? 'your selected job');
    $candidateName = app_email_greeting($info['applicant_name'] ?? null);
    $candidateEmail = (string) ($info['applicant_email'] ?? '');

    if ($candidateEmail !== '') {
        send_app_email(
            $candidateEmail,
            'KDXJobs: application received',
            "Hello {$candidateName},\n\nYour application for {$jobTitle} has been received successfully.\n\nWe will keep you updated as your application moves forward.\n\nKDXJobs Team"
        );
    }

    $recipientEmails = array_values(array_unique(array_filter([
        (string) ($info['company_email'] ?? ''),
        (string) ($info['recruiter_email'] ?? ''),
    ])));

    foreach ($recipientEmails as $recipientEmail) {
        send_app_email(
            $recipientEmail,
            'KDXJobs: new application received',
            "Hello,\n\nA new application has been submitted for {$jobTitle} by {$candidateName}.\n\nPlease sign in to KDXJobs to review the candidate details.\n\nKDXJobs Team"
        );
    }
}

function add_application_event(PDO $pdo, int $applicationId, string $type, string $title, string $note = ''): void
{
    if ($applicationId <= 0) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO application_events (application_id, event_type, title, note, created_by) VALUES (:application_id, :event_type, :title, :note, :created_by)');
    $stmt->execute([
        ':application_id' => $applicationId,
        ':event_type' => $type,
        ':title' => $title,
        ':note' => $note ?: null,
        ':created_by' => isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null,
    ]);
}

function service_chat_application(PDO $pdo, int $applicationId): ?array
{
    if (!isset($_SESSION['user']['id'])) {
        return null;
    }
    $role = (string) ($_SESSION['user']['role'] ?? '');
    $sql = 'SELECT a.*, j.title AS job_title, j.recruiter_id FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.id = :id';
    $params = [':id' => $applicationId];

    if ($role === 'jobseeker') {
        $sql .= ' AND (a.user_id = :user_id OR a.applicant_email = :email)';
        $params[':user_id'] = (int) $_SESSION['user']['id'];
        $params[':email'] = (string) $_SESSION['user']['email'];
    } elseif ($role === 'admin') {
        $sql .= ' AND j.recruiter_id = :recruiter_id';
        $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
    } elseif ($role !== 'superadmin') {
        return null;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    $application = $stmt->fetch();

    return $application ?: null;
}

function service_chat_messages(PDO $pdo, int $applicationId): array
{
    $stmt = $pdo->prepare('SELECT sm.*, u.full_name, u.company_name FROM service_messages sm LEFT JOIN users u ON u.id = sm.sender_id WHERE sm.application_id = :application_id ORDER BY sm.created_at ASC');
    $stmt->execute([':application_id' => $applicationId]);
    return $stmt->fetchAll();
}

function field(string $name, ?string $default = ''): string
{
    return trim((string) ($_POST[$name] ?? $default));
}

function query_list(string $name): array
{
    $value = $_GET[$name] ?? [];
    $items = is_array($value) ? $value : [$value];
    return array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $items), static fn(string $item): bool => $item !== ''));
}

function post_list(string $name): array
{
    $value = $_POST[$name] ?? [];
    $items = is_array($value) ? $value : [$value];
    return array_values(array_filter(array_map(static fn($item): string => trim((string) $item), $items), static fn(string $item): bool => $item !== ''));
}

function salary_bounds(?string $salary): array
{
    preg_match_all('/\d[\d,]*/', (string) $salary, $matches);
    $numbers = array_map(static fn(string $number): int => (int) str_replace(',', '', $number), $matches[0] ?? []);
    if (!$numbers) {
        return [0, 0];
    }
    return [min($numbers), max($numbers)];
}

function saved_search_signature(array $payload): string
{
    return hash('sha256', json_encode([
        'query_text' => (string) ($payload['query_text'] ?? ''),
        'types' => array_values($payload['types'] ?? []),
        'locations' => array_values($payload['locations'] ?? []),
        'industries' => array_values($payload['industries'] ?? []),
        'tags' => array_values($payload['tags'] ?? []),
        'min_salary' => (int) ($payload['min_salary'] ?? 0),
        'max_salary' => (int) ($payload['max_salary'] ?? 0),
    ], JSON_UNESCAPED_SLASHES));
}

function saved_search_matches_job(array $savedSearch, array $job): bool
{
    $query = strtolower((string) ($savedSearch['query_text'] ?? ''));
    $types = array_values(array_filter(array_map('trim', explode(',', (string) ($savedSearch['types'] ?? '')))));
    $locations = array_values(array_filter(array_map('trim', explode(',', (string) ($savedSearch['locations'] ?? '')))));
    $industries = array_values(array_filter(array_map('trim', explode(',', (string) ($savedSearch['industries'] ?? '')))));
    $tagsFilter = array_values(array_filter(array_map('trim', explode(',', (string) ($savedSearch['tags'] ?? '')))));
    $minSalary = (int) ($savedSearch['min_salary'] ?? 0);
    $maxSalary = (int) ($savedSearch['max_salary'] ?? 0);

    if ($types && !in_array((string) ($job['type'] ?? ''), $types, true)) {
        return false;
    }
    if ($locations && !in_array((string) ($job['location'] ?? ''), $locations, true)) {
        return false;
    }
    if ($industries && !in_array((string) ($job['industry'] ?? ''), $industries, true)) {
        return false;
    }

    $jobTags = tags($job);
    if ($tagsFilter && !array_intersect($tagsFilter, $jobTags)) {
        return false;
    }

    if ($minSalary > 0 || $maxSalary > 0) {
        [$jobMinSalary, $jobMaxSalary] = salary_bounds($job['salary'] ?? '');
        if ($minSalary > 0 && $jobMaxSalary < $minSalary) {
            return false;
        }
        if ($maxSalary > 0 && $jobMinSalary > $maxSalary) {
            return false;
        }
    }

    if ($query !== '') {
        $haystack = strtolower(implode(' ', [
            $job['title'] ?? '',
            $job['company'] ?? '',
            $job['location'] ?? '',
            $job['type'] ?? '',
            $job['tags'] ?? '',
            $job['description'] ?? '',
            $job['industry'] ?? '',
        ]));
        if (!str_contains($haystack, $query)) {
            return false;
        }
    }

    return true;
}

function send_saved_search_alerts(PDO $pdo, array $job): void
{
    $stmt = $pdo->query(
        "SELECT ss.*, u.email, u.full_name
         FROM saved_searches ss
         JOIN users u ON u.id = ss.user_id
         WHERE u.status = 'active' AND u.role = 'jobseeker'
         ORDER BY ss.created_at DESC"
    );
    $notifiedUsers = [];

    foreach ($stmt->fetchAll() as $savedSearch) {
        $userId = (int) ($savedSearch['user_id'] ?? 0);
        if ($userId <= 0 || isset($notifiedUsers[$userId])) {
            continue;
        }
        if (!saved_search_matches_job($savedSearch, $job)) {
            continue;
        }
        $notifiedUsers[$userId] = true;

        add_notification(
            $pdo,
            $userId,
            'New job match',
            'A new job matches your saved search: ' . ($job['title'] ?? 'New job') . '.',
            app_url('jobs', ['job' => $job['id'] ?? 0])
        );

        $to = (string) ($savedSearch['email'] ?? '');
        if ($to !== '') {
            $name = app_email_greeting($savedSearch['full_name'] ?? null);
            $subject = 'KDXJobs: new job matching your saved search';
            $body = "Hello {$name},\n\nA new job matches your saved search.\n\n"
                . "Job title: " . ($job['title'] ?? 'New job') . "\n"
                . "Company: " . ($job['company'] ?? 'KDXJobs') . "\n"
                . "Location: " . ($job['location'] ?? 'Not specified') . "\n\n"
                . "Sign in to KDXJobs to review the opportunity.\n\nKDXJobs Team";
            send_app_email($to, $subject, $body);
        }
    }
}

function need(array $fields): void
{
    foreach ($fields as $field) {
        if (field($field) === '') {
            throw new RuntimeException("Missing field: {$field}");
        }
    }
}

function current_role(): string
{
    return (string) ($_SESSION['user']['role'] ?? '');
}

function is_admin_role(?string $role = null): bool
{
    $role ??= current_role();
    return in_array($role, ['admin', 'superadmin'], true);
}

function is_super_admin(): bool
{
    return current_role() === 'superadmin';
}

function go(string $page, string $message = '', array $extra = []): never
{
    $query = array_merge(['page' => $page], $extra);
    if ($message !== '') {
        $query['message'] = $message;
    }
    header('Location: index.php?' . http_build_query($query));
    exit;
}

$page = $_GET['page'] ?? 'home';
$message = $_GET['message'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$filterTypes = query_list('type');
$filterLocations = query_list('location');
$filterIndustries = query_list('industry');
$filterTags = query_list('tag');
$minSalary = max(0, (int) ($_GET['min_salary'] ?? 0));
$maxSalary = max(0, (int) ($_GET['max_salary'] ?? 0));
$requestedSort = (string) ($_GET['sort'] ?? 'newest');
$jobSort = in_array($requestedSort, ['newest', 'oldest', 'salary_high', 'salary_low'], true) ? $requestedSort : 'newest';
$applicationSearch = trim((string) ($_GET['app_q'] ?? ''));
$jobManageSearch = trim((string) ($_GET['job_q'] ?? ''));
$manageEditJobId = max(0, (int) ($_GET['edit_job'] ?? 0));
$applicationsQuery = trim((string) ($_GET['applications_q'] ?? ''));
$applicationsStatus = trim((string) ($_GET['applications_status'] ?? ''));
$applicationsPage = max(1, (int) ($_GET['applications_page'] ?? 1));
$faqQuery = trim((string) ($_GET['faq_q'] ?? ''));
$error = '';
$pdo = null;

try {
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author_id INT NULL,
            title VARCHAR(180) NOT NULL,
            excerpt VARCHAR(255) NULL,
            content TEXT NOT NULL,
            category VARCHAR(80) NULL,
            status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS saved_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            job_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY saved_user_job (user_id, job_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS saved_searches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            signature VARCHAR(64) NOT NULL,
            query_text VARCHAR(255) NULL,
            types VARCHAR(255) NULL,
            locations VARCHAR(255) NULL,
            industries VARCHAR(255) NULL,
            tags VARCHAR(255) NULL,
            min_salary INT NOT NULL DEFAULT 0,
            max_salary INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY saved_search_user_signature (user_id, signature),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(180) NOT NULL,
            subject VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );
    $pdo->exec("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('ai_matching_mode', 'balanced')");
    $pdo->exec("ALTER TABLE jobs ADD COLUMN IF NOT EXISTS expires_at DATE NULL AFTER status");
    $pdo->exec("UPDATE jobs SET status = 'closed' WHERE expires_at IS NOT NULL AND expires_at < CURDATE() AND status <> 'closed'");
    $pdo->exec("ALTER TABLE applications MODIFY status ENUM('New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected') NOT NULL DEFAULT 'New'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_text MEDIUMTEXT NULL AFTER cv_file");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_skills TEXT NULL AFTER cv_text");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_years INT NULL AFTER cv_ai_skills");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_summary TEXT NULL AFTER cv_ai_years");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_json JSON NULL AFTER cv_ai_summary");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS cv_ai_updated_at TIMESTAMP NULL DEFAULT NULL AFTER cv_ai_summary");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_skills TEXT NULL AFTER cv_file");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_years INT NULL AFTER cv_ai_skills");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_summary TEXT NULL AFTER cv_ai_years");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_ai_json JSON NULL AFTER cv_ai_summary");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS cv_text MEDIUMTEXT NULL AFTER cv_ai_summary");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS ai_match_score INT NULL AFTER cv_text");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS ai_match_fit VARCHAR(40) NULL AFTER ai_match_score");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS ai_match_summary TEXT NULL AFTER ai_match_fit");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS ai_match_json JSON NULL AFTER ai_match_summary");
    $pdo->exec("ALTER TABLE applications ADD COLUMN IF NOT EXISTS ai_match_updated_at TIMESTAMP NULL DEFAULT NULL AFTER ai_match_json");
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            body VARCHAR(255) NOT NULL,
            link VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS application_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            event_type VARCHAR(80) NOT NULL,
            title VARCHAR(180) NOT NULL,
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS interviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            scheduled_at DATETIME NOT NULL,
            location VARCHAR(180) NULL,
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS service_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            application_id INT NOT NULL,
            sender_id INT NULL,
            sender_role ENUM('jobseeker', 'admin', 'superadmin') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
    if (isset($_SESSION['user']['id'])) {
        $sessionUser = $pdo->prepare('SELECT * FROM users WHERE id = :id AND status = "active" LIMIT 1');
        $sessionUser->execute([':id' => (int) $_SESSION['user']['id']]);
        $freshUser = $sessionUser->fetch();
        if (!$freshUser) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
        } else {
            unset($freshUser['password_hash']);
            $_SESSION['user'] = $freshUser;
        }
    }
} catch (Throwable $exception) {
    $error = 'Database is not ready. Start MySQL in XAMPP and open ' . app_base_path() . '/api/install.php once.';
}

if ($pdo && ($_GET['ajax'] ?? '') === 'service_chat') {
    $applicationId = (int) ($_GET['application_id'] ?? 0);
    $applicationInfo = service_chat_application($pdo, $applicationId);
    if (!$applicationInfo) {
        json_response(['ok' => false, 'error' => 'Chat not found.'], 404);
    }
    $messages = service_chat_messages($pdo, $applicationId);
    $latestId = $messages ? max(array_map(static fn(array $message): int => (int) $message['id'], $messages)) : 0;
    json_response([
        'ok' => true,
        'html' => service_chat_messages_html($messages),
        'latest_id' => $latestId,
    ]);
}

if ($pdo && ($_GET['page'] ?? '') === 'download') {
    serve_uploaded_file($pdo, (string) ($_GET['file'] ?? ''));
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        verify_csrf();

        if ($action === 'login') {
            enforce_rate_limit('web_login', 6, 300);
        } elseif ($action === 'register') {
            enforce_rate_limit('web_register', 50, 900);
        } elseif ($action === 'apply') {
            enforce_rate_limit('web_apply', 8, 600);
        } elseif ($action === 'contact_message') {
            enforce_rate_limit('web_contact_message', 5, 600);
        } elseif ($action === 'send_service_message') {
            enforce_rate_limit('web_service_message', 20, 300);
        } elseif (in_array($action, ['application_status', 'edit_application', 'schedule_interview', 'post_job', 'update_password', 'update_ai_screening_settings', 'rescreen_application_ai'], true)) {
            enforce_rate_limit('web_sensitive_' . $action, 20, 600);
        }

        if ($action === 'login') {
            need(['email', 'password']);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
            $stmt->execute([':email' => field('email')]);
            $user = $stmt->fetch();
            if (!$user || !password_verify(field('password'), $user['password_hash'])) {
                throw new RuntimeException('Invalid email or password.');
            }
            unset($user['password_hash']);
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            go(is_admin_role($user['role']) ? 'admin' : ($user['role'] === 'company' ? 'company' : 'user'), 'Login successful.', ['tab' => is_admin_role($user['role']) ? 'manage' : 'profile']);
        }

        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
            }
            session_destroy();
            go('home', 'Logged out.');
        }

        if ($action === 'register') {
            need(['role', 'email', 'password']);
            validate_password_strength(field('password'));
            $role = field('role') === 'company' ? 'company' : 'jobseeker';
            $role === 'company' ? need(['company_name', 'industry', 'location']) : need(['full_name']);
            $emailExists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $emailExists->execute([':email' => field('email')]);
            if ($emailExists->fetchColumn()) {
                throw new RuntimeException('This email is already registered. Please login instead, or use a different email.');
            }
            $selectedSkills = post_list('skills');
            $cvFile = null;
            if ($role === 'jobseeker') {
                if (!isset($_FILES['cv_file']) || (int) ($_FILES['cv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Please upload your CV as a PDF file.');
                }
                $cvFile = upload_file('cv_file', ['pdf'], MAX_CV_UPLOAD_BYTES);
            }
            $cvScreen = $role === 'jobseeker' ? screen_cv($cvFile, implode(', ', $selectedSkills)) : ['text' => null, 'skills' => null, 'summary' => null, 'json' => null];

            $stmt = $pdo->prepare(
                'INSERT INTO users (role, full_name, company_name, email, phone, password_hash, skills, industry, location, cv_file, cv_text, cv_ai_skills, cv_ai_years, cv_ai_summary, cv_ai_json, cv_ai_updated_at)
                 VALUES (:role, :full_name, :company_name, :email, :phone, :password_hash, :skills, :industry, :location, :cv_file, :cv_text, :cv_ai_skills, :cv_ai_years, :cv_ai_summary, :cv_ai_json, :cv_ai_updated_at)'
            );
            $stmt->execute([
                ':role' => $role,
                ':full_name' => field('full_name') ?: null,
                ':company_name' => field('company_name') ?: null,
                ':email' => field('email'),
                ':phone' => field('phone') ?: null,
                ':password_hash' => password_hash(field('password'), PASSWORD_DEFAULT),
                ':skills' => $selectedSkills ? implode(', ', $selectedSkills) : null,
                ':industry' => field('industry') ?: null,
                ':location' => field('location') ?: null,
                ':cv_file' => $cvFile,
                ':cv_text' => $cvScreen['text'] ?: null,
                ':cv_ai_skills' => $cvScreen['skills'] ?: null,
                ':cv_ai_years' => $cvScreen['years'] ?? null,
                ':cv_ai_summary' => $cvScreen['summary'] ?: null,
                ':cv_ai_json' => $cvScreen['json'] ?? null,
                ':cv_ai_updated_at' => $role === 'jobseeker' ? date('Y-m-d H:i:s') : null,
            ]);

            if ($role === 'company') {
                $company = $pdo->prepare('INSERT INTO companies (user_id, name, industry, location) VALUES (:user_id, :name, :industry, :location)');
                $company->execute([
                    ':user_id' => (int) $pdo->lastInsertId(),
                    ':name' => field('company_name'),
                    ':industry' => field('industry'),
                    ':location' => field('location'),
                ]);
            }
            go('login', 'Account created. You can login now.');
        }

        if ($action === 'apply') {
            if (!isset($_SESSION['user']['id'])) {
                go('login', 'Please login or register before applying to a job.');
            }
            need(['job_id', 'applicant_name', 'applicant_email', 'role']);
            $cvChoice = field('cv_option', 'saved');
            $savedCv = trim((string) ($_SESSION['user']['cv_file'] ?? ''));
            $applicationCv = null;

            if ($cvChoice === 'new') {
                $applicationCv = upload_file('application_cv', ['pdf'], MAX_CV_UPLOAD_BYTES);
                if (!$applicationCv) {
                    throw new RuntimeException('Please upload a new CV file.');
                }
            } elseif ($savedCv !== '') {
                $applicationCv = $savedCv;
            } else {
                throw new RuntimeException('No saved CV found. Please upload a new CV file.');
            }
            $profileSkills = (string) ($_SESSION['user']['skills'] ?? '');
            $applicationScreen = screen_cv($applicationCv, $profileSkills);
            $stmt = $pdo->prepare(
                'INSERT INTO applications (job_id, user_id, applicant_name, applicant_email, applicant_phone, role, cover_note, cv_file, cv_ai_skills, cv_ai_years, cv_ai_summary, cv_ai_json, cv_text)
                 VALUES (:job_id, :user_id, :applicant_name, :applicant_email, :applicant_phone, :role, :cover_note, :cv_file, :cv_ai_skills, :cv_ai_years, :cv_ai_summary, :cv_ai_json, :cv_text)'
            );
            $stmt->execute([
                ':job_id' => (int) field('job_id'),
                ':user_id' => isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null,
                ':applicant_name' => field('applicant_name'),
                ':applicant_email' => field('applicant_email'),
                ':applicant_phone' => field('applicant_phone') ?: null,
                ':role' => field('role'),
                ':cover_note' => field('cover_note') ?: null,
                ':cv_file' => $applicationCv,
                ':cv_ai_skills' => $applicationScreen['skills'] ?: null,
                ':cv_ai_years' => $applicationScreen['years'] ?? null,
                ':cv_ai_summary' => $applicationScreen['summary'] ?: null,
                ':cv_ai_json' => $applicationScreen['json'] ?? null,
                ':cv_text' => $applicationScreen['text'] ?: null,
            ]);
            $applicationId = (int) $pdo->lastInsertId();
            $matchStmt = $pdo->prepare(
                "SELECT a.*, j.title AS job_title, j.description AS job_description, j.requirements AS job_requirements, j.company_id, j.recruiter_id,
                        c.name AS company, u.full_name AS recruiter_name, candidate.skills AS candidate_skills,
                        candidate.cv_text AS candidate_cv_text, candidate.cv_ai_skills AS candidate_cv_ai_skills,
                        candidate.cv_ai_years AS candidate_cv_ai_years, candidate.cv_ai_summary AS candidate_cv_ai_summary,
                        candidate.cv_ai_json AS candidate_cv_ai_json,
                        GROUP_CONCAT(t.tag ORDER BY t.id SEPARATOR ',') AS job_tags
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 JOIN companies c ON c.id = j.company_id
                 LEFT JOIN users u ON u.id = j.recruiter_id
                 LEFT JOIN users candidate ON candidate.id = a.user_id OR candidate.email = a.applicant_email
                 LEFT JOIN job_tags t ON t.job_id = j.id
                 WHERE a.id = :id
                 GROUP BY a.id
                 LIMIT 1"
            );
            $matchStmt->execute([':id' => $applicationId]);
            $matchApplication = $matchStmt->fetch();
            if (is_array($matchApplication)) {
                candidate_match_score($matchApplication);
            }
            add_application_event($pdo, $applicationId, 'applied', 'Application submitted', 'Your application was sent to the recruiter.');
            send_application_received_emails($pdo, $applicationId);
            go('jobs', 'Application submitted successfully.');
        }

        if ($action === 'toggle_saved_job') {
            if (!isset($_SESSION['user']['id'])) {
                go('login', 'Please login or register before saving jobs.');
            }
            if (($_SESSION['user']['role'] ?? '') !== 'jobseeker') {
                throw new RuntimeException('Only job seekers can save jobs.');
            }
            need(['job_id', 'save_state']);
            $jobId = (int) field('job_id');
            $redirectPage = field('redirect_page', 'jobs') ?: 'jobs';
            $redirectTab = field('redirect_tab');
            $extra = $redirectTab !== '' ? ['tab' => $redirectTab] : [];

            if (field('save_state') === 'saved') {
                $stmt = $pdo->prepare('DELETE FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id');
                $stmt->execute([
                    ':user_id' => (int) $_SESSION['user']['id'],
                    ':job_id' => $jobId,
                ]);
                go($redirectPage, 'Job removed from saved list.', $extra);
            }

            $stmt = $pdo->prepare('INSERT IGNORE INTO saved_jobs (user_id, job_id) VALUES (:user_id, :job_id)');
            $stmt->execute([
                ':user_id' => (int) $_SESSION['user']['id'],
                ':job_id' => $jobId,
            ]);
            go($redirectPage, 'Job saved.', $extra);
        }

        if ($action === 'save_search') {
            if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'jobseeker') {
                throw new RuntimeException('Only job seekers can save searches.');
            }

            $payload = [
                'query_text' => field('search_query'),
                'types' => post_list('search_type'),
                'locations' => post_list('search_location'),
                'industries' => post_list('search_industry'),
                'tags' => post_list('search_tag'),
                'min_salary' => max(0, (int) field('search_min_salary')),
                'max_salary' => max(0, (int) field('search_max_salary')),
            ];

            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO saved_searches (user_id, signature, query_text, types, locations, industries, tags, min_salary, max_salary)
                 VALUES (:user_id, :signature, :query_text, :types, :locations, :industries, :tags, :min_salary, :max_salary)'
            );
            $stmt->execute([
                ':user_id' => (int) $_SESSION['user']['id'],
                ':signature' => saved_search_signature($payload),
                ':query_text' => $payload['query_text'] ?: null,
                ':types' => $payload['types'] ? implode(', ', $payload['types']) : null,
                ':locations' => $payload['locations'] ? implode(', ', $payload['locations']) : null,
                ':industries' => $payload['industries'] ? implode(', ', $payload['industries']) : null,
                ':tags' => $payload['tags'] ? implode(', ', $payload['tags']) : null,
                ':min_salary' => $payload['min_salary'],
                ':max_salary' => $payload['max_salary'],
            ]);

            go('jobs', 'Search saved successfully.');
        }

        if ($action === 'save_ai_application_search') {
            if (!isset($_SESSION['user']['id']) || !is_admin_role($_SESSION['user']['role'] ?? null)) {
                throw new RuntimeException('Only admins can save AI application searches.');
            }

            $queryText = trim(field('applications_q'));
            if ($queryText === '') {
                throw new RuntimeException('Enter an AI application search before saving.');
            }

            $payload = [
                'query_text' => $queryText,
                'types' => ['ai_application_search'],
                'locations' => [],
                'industries' => [],
                'tags' => [],
                'min_salary' => 0,
                'max_salary' => 0,
            ];
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO saved_searches (user_id, signature, query_text, types, locations, industries, tags, min_salary, max_salary)
                 VALUES (:user_id, :signature, :query_text, :types, NULL, NULL, NULL, 0, 0)'
            );
            $stmt->execute([
                ':user_id' => (int) $_SESSION['user']['id'],
                ':signature' => substr('appai' . saved_search_signature($payload), 0, 64),
                ':query_text' => $queryText,
                ':types' => 'ai_application_search',
            ]);

            go('admin', 'AI application search saved.', ['tab' => 'applications', 'applications_q' => $queryText]);
        }

        if ($action === 'delete_ai_application_search') {
            if (!isset($_SESSION['user']['id']) || !is_admin_role($_SESSION['user']['role'] ?? null)) {
                throw new RuntimeException('Only admins can manage AI application searches.');
            }
            need(['saved_search_id']);
            $stmt = $pdo->prepare("DELETE FROM saved_searches WHERE id = :id AND user_id = :user_id AND types = 'ai_application_search'");
            $stmt->execute([
                ':id' => (int) field('saved_search_id'),
                ':user_id' => (int) $_SESSION['user']['id'],
            ]);
            go('admin', 'AI application search removed.', ['tab' => 'applications']);
        }

        if ($action === 'delete_saved_search') {
            if (!isset($_SESSION['user']['id']) || ($_SESSION['user']['role'] ?? '') !== 'jobseeker') {
                throw new RuntimeException('Only job seekers can manage saved searches.');
            }
            need(['saved_search_id']);
            $stmt = $pdo->prepare('DELETE FROM saved_searches WHERE id = :id AND user_id = :user_id');
            $stmt->execute([
                ':id' => (int) field('saved_search_id'),
                ':user_id' => (int) $_SESSION['user']['id'],
            ]);
            go('user', 'Saved search removed.', ['tab' => 'saved_searches']);
        }

        if ($action === 'post_job') {
            need(['company_id', 'title', 'location', 'salary', 'type', 'description']);
            if (!isset($_SESSION['user']['id']) || (!is_admin_role() && current_role() !== 'company')) {
                throw new RuntimeException('Only companies and admin recruiters can post jobs.');
            }
            $companyId = (int) field('company_id');
            if (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $ownedCompanyId = (int) ($companyStmt->fetchColumn() ?: 0);
                if ($ownedCompanyId <= 0) {
                    throw new RuntimeException('No company profile is linked to this account.');
                }
                $companyId = $ownedCompanyId;
            }
            $recruiterId = is_admin_role() ? (int) $_SESSION['user']['id'] : null;
            $stmt = $pdo->prepare(
                'INSERT INTO jobs (company_id, recruiter_id, title, location, salary, type, description, requirements, expires_at)
                 VALUES (:company_id, :recruiter_id, :title, :location, :salary, :type, :description, :requirements, :expires_at)'
            );
            $stmt->execute([
                ':company_id' => $companyId,
                ':recruiter_id' => $recruiterId,
                ':title' => field('title'),
                ':location' => field('location'),
                ':salary' => field('salary'),
                ':type' => field('type'),
                ':description' => field('description'),
                ':requirements' => field('requirements') ?: null,
                ':expires_at' => field('expires_at') ?: null,
            ]);

            $jobId = (int) $pdo->lastInsertId();
            $tagStmt = $pdo->prepare('INSERT INTO job_tags (job_id, tag) VALUES (:job_id, :tag)');
            foreach (array_filter(array_map('trim', explode(',', field('tags')))) as $tag) {
                $tagStmt->execute([':job_id' => $jobId, ':tag' => $tag]);
            }
            $jobInfoStmt = $pdo->prepare(
                "SELECT j.*, c.name AS company, c.industry,
                        GROUP_CONCAT(t.tag ORDER BY t.id SEPARATOR ',') AS tags
                 FROM jobs j
                 JOIN companies c ON c.id = j.company_id
                 LEFT JOIN job_tags t ON t.job_id = j.id
                 WHERE j.id = :id
                 GROUP BY j.id
                 LIMIT 1"
            );
            $jobInfoStmt->execute([':id' => $jobId]);
            $postedJob = $jobInfoStmt->fetch();
            if ($postedJob) {
                send_saved_search_alerts($pdo, $postedJob);
            }
            go(is_admin_role() ? 'admin' : 'company', 'Job posted successfully.', ['tab' => 'manage']);
        }

        if ($action === 'contact_message') {
            need(['full_name', 'email', 'subject', 'message']);
            if (!filter_var(field('email'), FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO contact_messages (full_name, email, subject, message)
                 VALUES (:full_name, :email, :subject, :message)'
            );
            $stmt->execute([
                ':full_name' => field('full_name'),
                ':email' => field('email'),
                ':subject' => field('subject'),
                ':message' => field('message'),
            ]);

            $admins = $pdo->query("SELECT email FROM users WHERE role IN ('admin', 'superadmin') AND status = 'active'")->fetchAll();
            foreach ($admins as $adminUser) {
                $adminEmail = trim((string) ($adminUser['email'] ?? ''));
                if ($adminEmail === '') {
                    continue;
                }
                send_app_email(
                    $adminEmail,
                    'KDXJobs: new contact form message',
                    "A new contact form message has been submitted.\n\nName: " . field('full_name') . "\nEmail: " . field('email') . "\nSubject: " . field('subject') . "\n\nMessage:\n" . field('message')
                );
            }

            go('contact', 'Your message has been sent. We will get back to you soon.');
        }

        if ($action === 'application_status') {
            need(['application_id', 'status']);
            $applicationId = (int) field('application_id');
            $redirectPage = field('redirect_page', $page) ?: $page;
            $redirectExtra = ['tab' => field('redirect_tab', 'applications')];
            if ($redirectPage === 'application') {
                $redirectExtra = ['application' => $applicationId];
                if (field('back_page') !== '') {
                    $redirectExtra['back_page'] = field('back_page');
                }
                if (field('back_tab') !== '') {
                    $redirectExtra['back_tab'] = field('back_tab');
                }
            }
            $nextStatus = field('status');
            if (!is_allowed_application_status($nextStatus)) {
                throw new RuntimeException('Invalid application status.');
            }
            $sql = 'UPDATE applications a JOIN jobs j ON j.id = a.job_id SET a.status = :status WHERE a.id = :id';
            $params = [':status' => $nextStatus, ':id' => $applicationId];
            if (current_role() === 'admin') {
                $sql .= ' AND j.recruiter_id = :recruiter_id';
                $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
            } elseif (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $sql .= ' AND j.company_id = :company_id';
                $params[':company_id'] = (int) ($companyStmt->fetchColumn() ?: 0);
            } elseif (!is_super_admin()) {
                throw new RuntimeException('Not allowed to update applications.');
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() > 0) {
                $info = $pdo->prepare('SELECT a.user_id, a.applicant_name, a.applicant_email, j.title AS job_title FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.id = :id LIMIT 1');
                $info->execute([':id' => $applicationId]);
                $applicationInfo = $info->fetch();
                add_application_event($pdo, $applicationId, 'status', 'Status changed to ' . $nextStatus, 'Your application for ' . ($applicationInfo['job_title'] ?? 'this job') . ' is now ' . $nextStatus . '.');
                add_notification(
                    $pdo,
                    (int) ($applicationInfo['user_id'] ?? 0),
                    'Application update',
                    'Your application for ' . ($applicationInfo['job_title'] ?? 'a job') . ' is now ' . $nextStatus . '.',
                    app_url('user', ['tab' => 'applications'])
                );
                send_application_status_email(is_array($applicationInfo) ? $applicationInfo : [], $nextStatus);
            }
            go($redirectPage, 'Application updated.', $redirectExtra);
        }

        if ($action === 'edit_application') {
            need(['application_id', 'applicant_name', 'applicant_email', 'role', 'status']);
            $applicationId = (int) field('application_id');
            $redirectPage = field('redirect_page', $page) ?: $page;
            $redirectExtra = ['tab' => field('redirect_tab', 'applications')];
            if ($redirectPage === 'application') {
                $redirectExtra = ['application' => $applicationId];
                if (field('back_page') !== '') {
                    $redirectExtra['back_page'] = field('back_page');
                }
                if (field('back_tab') !== '') {
                    $redirectExtra['back_tab'] = field('back_tab');
                }
            }
            $nextStatus = field('status');
            if (!is_allowed_application_status($nextStatus)) {
                throw new RuntimeException('Invalid application status.');
            }
            if (!filter_var(field('applicant_email'), FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid applicant email.');
            }

            $sql = 'UPDATE applications a JOIN jobs j ON j.id = a.job_id
                    SET a.applicant_name = :applicant_name,
                        a.applicant_email = :applicant_email,
                        a.applicant_phone = :applicant_phone,
                        a.role = :role,
                        a.cover_note = :cover_note,
                        a.status = :status
                    WHERE a.id = :id';
            $params = [
                ':applicant_name' => field('applicant_name'),
                ':applicant_email' => field('applicant_email'),
                ':applicant_phone' => field('applicant_phone') ?: null,
                ':role' => field('role'),
                ':cover_note' => field('cover_note') ?: null,
                ':status' => $nextStatus,
                ':id' => $applicationId,
            ];

            if (current_role() === 'admin') {
                $sql .= ' AND j.recruiter_id = :recruiter_id';
                $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
            } elseif (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $sql .= ' AND j.company_id = :company_id';
                $params[':company_id'] = (int) ($companyStmt->fetchColumn() ?: 0);
            } elseif (!is_super_admin()) {
                throw new RuntimeException('Not allowed to edit applications.');
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() > 0) {
                add_application_event($pdo, $applicationId, 'edited', 'Application details edited', 'Recruitment team updated the application details.');
            }

            go($redirectPage, 'Application details saved.', $redirectExtra);
        }

        if ($action === 'rescreen_application_ai') {
            need(['application_id']);
            if (!is_admin_role() && current_role() !== 'company') {
                throw new RuntimeException('Only admins and companies can re-screen applications.');
            }

            $applicationId = (int) field('application_id');
            $redirectPage = field('redirect_page', $page) ?: $page;
            $redirectExtra = ['tab' => field('redirect_tab', 'applications')];
            if ($redirectPage === 'application') {
                $redirectExtra = ['application' => $applicationId];
                if (field('back_page') !== '') {
                    $redirectExtra['back_page'] = field('back_page');
                }
                if (field('back_tab') !== '') {
                    $redirectExtra['back_tab'] = field('back_tab');
                }
            }

            $sql = "SELECT a.id, a.cv_file, candidate.skills AS candidate_skills
                    FROM applications a
                    JOIN jobs j ON j.id = a.job_id
                    LEFT JOIN users candidate ON candidate.id = a.user_id OR candidate.email = a.applicant_email
                    WHERE a.id = :id";
            $params = [':id' => $applicationId];
            if (current_role() === 'admin') {
                $sql .= ' AND j.recruiter_id = :recruiter_id';
                $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
            } elseif (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $sql .= ' AND j.company_id = :company_id';
                $params[':company_id'] = (int) ($companyStmt->fetchColumn() ?: 0);
            }
            $stmt = $pdo->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            $applicationToScreen = $stmt->fetch();
            if (!$applicationToScreen) {
                throw new RuntimeException('Application not found or not allowed.');
            }

            if (!empty($applicationToScreen['cv_file'])) {
                $cvScreen = screen_cv((string) $applicationToScreen['cv_file'], (string) ($applicationToScreen['candidate_skills'] ?? ''));
                $update = $pdo->prepare(
                    'UPDATE applications
                     SET cv_text = :cv_text,
                         cv_ai_skills = :cv_ai_skills,
                         cv_ai_years = :cv_ai_years,
                         cv_ai_summary = :cv_ai_summary,
                         cv_ai_json = :cv_ai_json,
                         ai_match_score = NULL,
                         ai_match_fit = NULL,
                         ai_match_summary = NULL,
                         ai_match_json = NULL,
                         ai_match_updated_at = NULL
                     WHERE id = :id'
                );
                $update->execute([
                    ':cv_text' => $cvScreen['text'] ?? null,
                    ':cv_ai_skills' => $cvScreen['skills'] ?? null,
                    ':cv_ai_years' => $cvScreen['years'] ?? null,
                    ':cv_ai_summary' => $cvScreen['summary'] ?? null,
                    ':cv_ai_json' => $cvScreen['json'] ?? null,
                    ':id' => $applicationId,
                ]);
            } else {
                $update = $pdo->prepare(
                    'UPDATE applications
                     SET ai_match_score = NULL,
                         ai_match_fit = NULL,
                         ai_match_summary = NULL,
                         ai_match_json = NULL,
                         ai_match_updated_at = NULL
                     WHERE id = :id'
                );
                $update->execute([':id' => $applicationId]);
            }
            add_application_event($pdo, $applicationId, 'ai_rescreen', 'AI screening refreshed', 'Recruitment team refreshed the AI CV screening and match score.');

            go($redirectPage, 'AI screening refreshed.', $redirectExtra);
        }

        if ($action === 'schedule_interview') {
            need(['application_id', 'scheduled_at']);
            $applicationId = (int) field('application_id');
            $redirectPage = field('redirect_page', $page) ?: $page;
            $redirectExtra = ['tab' => field('redirect_tab', 'applications')];
            if ($redirectPage === 'application') {
                $redirectExtra = ['application' => $applicationId];
                if (field('back_page') !== '') {
                    $redirectExtra['back_page'] = field('back_page');
                }
                if (field('back_tab') !== '') {
                    $redirectExtra['back_tab'] = field('back_tab');
                }
            }
            $sql = 'SELECT a.user_id, a.applicant_name, a.applicant_email, j.title AS job_title, j.company_id, j.recruiter_id FROM applications a JOIN jobs j ON j.id = a.job_id WHERE a.id = :id';
            $params = [':id' => $applicationId];
            if (current_role() === 'admin') {
                $sql .= ' AND j.recruiter_id = :recruiter_id';
                $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
            } elseif (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $sql .= ' AND j.company_id = :company_id';
                $params[':company_id'] = (int) ($companyStmt->fetchColumn() ?: 0);
            } elseif (!is_super_admin()) {
                throw new RuntimeException('Not allowed to schedule interviews.');
            }
            $info = $pdo->prepare($sql . ' LIMIT 1');
            $info->execute($params);
            $applicationInfo = $info->fetch();
            if (!$applicationInfo) {
                throw new RuntimeException('Application not found.');
            }
            $stmt = $pdo->prepare('INSERT INTO interviews (application_id, scheduled_at, location, note, created_by) VALUES (:application_id, :scheduled_at, :location, :note, :created_by)');
            $stmt->execute([
                ':application_id' => $applicationId,
                ':scheduled_at' => str_replace('T', ' ', field('scheduled_at')) . ':00',
                ':location' => field('interview_location') ?: null,
                ':note' => field('interview_note') ?: null,
                ':created_by' => (int) ($_SESSION['user']['id'] ?? 0) ?: null,
            ]);
            $pdo->prepare("UPDATE applications SET status = 'Interview' WHERE id = :id")->execute([':id' => $applicationId]);
            $when = date('M j, Y g:i A', strtotime(str_replace('T', ' ', field('scheduled_at'))));
            add_application_event($pdo, $applicationId, 'interview', 'Interview scheduled', 'Interview set for ' . $when . (field('interview_location') ? ' at ' . field('interview_location') : '.'));
            add_notification($pdo, (int) ($applicationInfo['user_id'] ?? 0), 'Interview scheduled', 'Your interview for ' . ($applicationInfo['job_title'] ?? 'a job') . ' is on ' . $when . '.', app_url('user', ['tab' => 'applications']));
            send_interview_scheduled_email(
                is_array($applicationInfo) ? $applicationInfo : [],
                $when,
                field('interview_location'),
                field('interview_note')
            );
            go($redirectPage, 'Interview scheduled.', $redirectExtra);
        }

        if ($action === 'send_service_message') {
            if (!isset($_SESSION['user']['id'])) {
                throw new RuntimeException('Please login first.');
            }
            need(['application_id', 'chat_message']);
            $applicationId = (int) field('application_id');
            $role = (string) ($_SESSION['user']['role'] ?? '');
            $applicationInfo = service_chat_application($pdo, $applicationId);
            if (!$applicationInfo) {
                throw new RuntimeException('Application not found for this chat.');
            }

            $senderRole = $role === 'superadmin' ? 'superadmin' : ($role === 'admin' ? 'admin' : 'jobseeker');
            $stmt = $pdo->prepare('INSERT INTO service_messages (application_id, sender_id, sender_role, message) VALUES (:application_id, :sender_id, :sender_role, :message)');
            $stmt->execute([
                ':application_id' => $applicationId,
                ':sender_id' => (int) $_SESSION['user']['id'],
                ':sender_role' => $senderRole,
                ':message' => field('chat_message'),
            ]);

            if ($senderRole === 'jobseeker') {
                $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'superadmin') AND status = 'active'")->fetchAll();
                foreach ($admins as $adminUser) {
                    add_notification($pdo, (int) $adminUser['id'], 'New service message', 'A job seeker sent a message about ' . ($applicationInfo['job_title'] ?? 'an application') . '.', app_url('admin', ['tab' => 'applications']));
                }
            } else {
                add_notification($pdo, (int) ($applicationInfo['user_id'] ?? 0), 'Service center replied', 'You have a new reply about ' . ($applicationInfo['job_title'] ?? 'your application') . '.', app_url('user', ['tab' => 'applications']));
                send_service_reply_email(is_array($applicationInfo) ? $applicationInfo : []);
            }
            if (field('ajax') === '1') {
                $messages = service_chat_messages($pdo, $applicationId);
                $latestId = $messages ? max(array_map(static fn(array $message): int => (int) $message['id'], $messages)) : 0;
                json_response([
                    'ok' => true,
                    'html' => service_chat_messages_html($messages),
                    'latest_id' => $latestId,
                ]);
            }
            go($role === 'jobseeker' ? 'user' : 'admin', 'Message sent.', ['tab' => 'applications']);
        }

        if ($action === 'withdraw_application') {
            if (!isset($_SESSION['user']['id'])) {
                throw new RuntimeException('Please login first.');
            }
            need(['application_id']);
            $applicationId = (int) field('application_id');
            $redirectPage = field('redirect_page', 'user') ?: 'user';
            $redirectExtra = ['tab' => field('redirect_tab', 'applications')];
            $stmt = $pdo->prepare('DELETE FROM applications WHERE id = :id AND (user_id = :user_id OR applicant_email = :email)');
            $stmt->execute([
                ':id' => $applicationId,
                ':user_id' => (int) $_SESSION['user']['id'],
                ':email' => (string) $_SESSION['user']['email'],
            ]);
            if ($redirectPage === 'application') {
                go('user', 'Application withdrawn.', ['tab' => 'applications']);
            }
            go($redirectPage, 'Application withdrawn.', $redirectExtra);
        }

        if ($action === 'delete_job') {
            need(['job_id']);
            $sql = 'DELETE FROM jobs WHERE id = :id';
            $params = [':id' => (int) field('job_id')];
            if (current_role() === 'admin') {
                $sql .= ' AND recruiter_id = :recruiter_id';
                $params[':recruiter_id'] = (int) $_SESSION['user']['id'];
            } elseif (current_role() === 'company') {
                $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE user_id = :user_id LIMIT 1');
                $companyStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
                $sql .= ' AND company_id = :company_id';
                $params[':company_id'] = (int) ($companyStmt->fetchColumn() ?: 0);
            } elseif (!is_super_admin()) {
                throw new RuntimeException('Not allowed to delete this job.');
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            go($page, 'Job deleted.', ['tab' => 'manage']);
        }

        if ($action === 'update_job') {
            need(['job_id', 'company_id', 'title', 'location', 'salary', 'type', 'description', 'status']);
            if (!isset($_SESSION['user']['id']) || !is_admin_role()) {
                throw new RuntimeException('Only admin recruiters can edit job posts here.');
            }

            $jobId = (int) field('job_id');
            $companyId = (int) field('company_id');
            $jobStmt = $pdo->prepare('SELECT id, recruiter_id FROM jobs WHERE id = :id LIMIT 1');
            $jobStmt->execute([':id' => $jobId]);
            $existingJob = $jobStmt->fetch();
            if (!$existingJob) {
                throw new RuntimeException('Job not found.');
            }

            if (!is_super_admin() && (int) ($existingJob['recruiter_id'] ?? 0) !== (int) $_SESSION['user']['id']) {
                throw new RuntimeException('Not allowed to edit this job.');
            }

            $status = field('status');
            if (!in_array($status, ['active', 'closed'], true)) {
                throw new RuntimeException('Invalid job status.');
            }

            $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE id = :id LIMIT 1');
            $companyStmt->execute([':id' => $companyId]);
            if (!$companyStmt->fetchColumn()) {
                throw new RuntimeException('Selected company was not found.');
            }

            $stmt = $pdo->prepare(
                'UPDATE jobs
                 SET company_id = :company_id,
                     title = :title,
                     location = :location,
                     salary = :salary,
                     type = :type,
                     description = :description,
                     requirements = :requirements,
                     expires_at = :expires_at,
                     status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                ':company_id' => $companyId,
                ':title' => field('title'),
                ':location' => field('location'),
                ':salary' => field('salary'),
                ':type' => field('type'),
                ':description' => field('description'),
                ':requirements' => field('requirements') ?: null,
                ':expires_at' => field('expires_at') ?: null,
                ':status' => $status,
                ':id' => $jobId,
            ]);

            $pdo->prepare('DELETE FROM job_tags WHERE job_id = :job_id')->execute([':job_id' => $jobId]);
            $tagStmt = $pdo->prepare('INSERT INTO job_tags (job_id, tag) VALUES (:job_id, :tag)');
            foreach (array_filter(array_map('trim', explode(',', field('tags')))) as $tag) {
                $tagStmt->execute([':job_id' => $jobId, ':tag' => $tag]);
            }

            go($page, 'Job updated successfully.', ['tab' => 'manage']);
        }

        if ($action === 'toggle_user') {
            if (!is_admin_role()) {
                throw new RuntimeException('Admin access required.');
            }
            need(['user_id', 'status']);
            $target = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $target->execute([':id' => (int) field('user_id')]);
            $targetRole = (string) ($target->fetchColumn() ?: '');
            if (in_array($targetRole, ['admin', 'superadmin'], true) && !is_super_admin()) {
                throw new RuntimeException('Only the super admin can change admin accounts.');
            }
            if ($targetRole === 'superadmin') {
                throw new RuntimeException('The super admin account cannot be blocked.');
            }
            $nextStatus = field('status') === 'blocked' ? 'blocked' : 'active';
            $stmt = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $nextStatus, ':id' => (int) field('user_id')]);
            go('admin', 'User status updated.', ['tab' => 'manage']);
        }

        if ($action === 'create_admin') {
            if (!is_super_admin()) {
                throw new RuntimeException('Only the super admin can create admins.');
            }
            need(['full_name', 'email', 'password']);
            validate_password_strength(field('password'));
            $stmt = $pdo->prepare(
                'INSERT INTO users (role, full_name, email, phone, password_hash, status)
                 VALUES (:role, :full_name, :email, :phone, :password_hash, "active")'
            );
            $stmt->execute([
                ':role' => 'admin',
                ':full_name' => field('full_name'),
                ':email' => field('email'),
                ':phone' => field('phone') ?: null,
                ':password_hash' => password_hash(field('password'), PASSWORD_DEFAULT),
            ]);
            go('admin', 'Admin account created.', ['tab' => 'admins']);
        }

        if ($action === 'delete_admin') {
            if (!is_super_admin()) {
                throw new RuntimeException('Only the super admin can remove admins.');
            }
            need(['user_id']);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'admin'");
            $stmt->execute([':id' => (int) field('user_id')]);
            go('admin', 'Admin account removed.', ['tab' => 'admins']);
        }

        if ($action === 'create_blog_post') {
            if (!is_admin_role()) {
                throw new RuntimeException('Admin access required to publish blog posts.');
            }
            need(['title', 'content']);
            $stmt = $pdo->prepare(
                'INSERT INTO blog_posts (author_id, title, excerpt, content, category, status)
                 VALUES (:author_id, :title, :excerpt, :content, :category, :status)'
            );
            $stmt->execute([
                ':author_id' => (int) ($_SESSION['user']['id'] ?? 0) ?: null,
                ':title' => field('title'),
                ':excerpt' => field('excerpt') ?: null,
                ':content' => field('content'),
                ':category' => field('category', 'Career Advice') ?: 'Career Advice',
                ':status' => field('status') === 'draft' ? 'draft' : 'published',
            ]);
            go('admin', 'Blog post saved.', ['tab' => 'blog']);
        }

        if ($action === 'update_profile') {
            if (!isset($_SESSION['user']['id'])) {
                throw new RuntimeException('Please login first.');
            }
            $currentRole = $_SESSION['user']['role'];
            $selectedSkills = $_POST['skills'] ?? [];
            $skillsValue = is_array($selectedSkills)
                ? implode(', ', array_values(array_filter(array_map('trim', $selectedSkills))))
                : field('skills');
            $cvFile = $currentRole === 'jobseeker' ? upload_file('cv_file', ['pdf'], MAX_CV_UPLOAD_BYTES) : null;
            $cvScreen = $cvFile ? screen_cv($cvFile, $skillsValue) : null;
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET full_name = :full_name, company_name = :company_name, phone = :phone, skills = :skills,
                     industry = :industry, location = :location, cv_file = COALESCE(:cv_file, cv_file),
                     cv_text = COALESCE(:cv_text, cv_text),
                     cv_ai_skills = COALESCE(:cv_ai_skills, cv_ai_skills),
                     cv_ai_years = COALESCE(:cv_ai_years, cv_ai_years),
                     cv_ai_summary = COALESCE(:cv_ai_summary, cv_ai_summary),
                     cv_ai_json = COALESCE(:cv_ai_json, cv_ai_json),
                     cv_ai_updated_at = COALESCE(:cv_ai_updated_at, cv_ai_updated_at)
                 WHERE id = :id'
            );
            $stmt->execute([
                ':full_name' => field('full_name') ?: null,
                ':company_name' => field('company_name') ?: null,
                ':phone' => field('phone') ?: null,
                ':skills' => $skillsValue ?: null,
                ':industry' => field('industry') ?: null,
                ':location' => field('location') ?: null,
                ':cv_file' => $cvFile,
                ':cv_text' => $cvScreen['text'] ?? null,
                ':cv_ai_skills' => $cvScreen['skills'] ?? null,
                ':cv_ai_years' => $cvScreen['years'] ?? null,
                ':cv_ai_summary' => $cvScreen['summary'] ?? null,
                ':cv_ai_json' => $cvScreen['json'] ?? null,
                ':cv_ai_updated_at' => $cvScreen ? date('Y-m-d H:i:s') : null,
                ':id' => (int) $_SESSION['user']['id'],
            ]);
            $fresh = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $fresh->execute([':id' => (int) $_SESSION['user']['id']]);
            $_SESSION['user'] = $fresh->fetch();
            unset($_SESSION['user']['password_hash']);
            go(is_admin_role($currentRole) ? 'admin' : ($currentRole === 'company' ? 'company' : 'user'), 'Profile updated.', ['tab' => 'settings']);
        }

        if ($action === 'update_password') {
            if (!isset($_SESSION['user']['id'])) {
                throw new RuntimeException('Please login first.');
            }
            need(['password']);
            validate_password_strength(field('password'));
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                ':password_hash' => password_hash(field('password'), PASSWORD_DEFAULT),
                ':id' => (int) $_SESSION['user']['id'],
            ]);
            go(is_admin_role($_SESSION['user']['role']) ? 'admin' : ($_SESSION['user']['role'] === 'company' ? 'company' : 'user'), 'Password updated.', ['tab' => 'settings']);
        }

        if ($action === 'update_ai_screening_settings') {
            if (!is_admin_role()) {
                throw new RuntimeException('Admin access required.');
            }
            $mode = field('ai_matching_mode', 'balanced');
            if (!in_array($mode, ['strict', 'balanced', 'flexible'], true)) {
                throw new RuntimeException('Invalid AI matching mode.');
            }
            set_app_setting('ai_matching_mode', $mode);
            $_SESSION['ai_matching_mode'] = $mode;
            $pdo->exec('UPDATE applications SET ai_match_score = NULL, ai_match_fit = NULL, ai_match_summary = NULL, ai_match_json = NULL, ai_match_updated_at = NULL');
            $redirectPage = field('redirect_page', is_admin_role($_SESSION['user']['role'] ?? null) ? 'admin' : 'company');
            $redirectTab = field('redirect_tab', 'settings');
            go($redirectPage, 'AI screening mode changed to ' . ai_matching_mode_label($mode) . '. Existing match scores will refresh with the new mode.', ['tab' => $redirectTab]);
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$jobs = [];
$allJobs = [];
$companies = [];
$applicants = [];
$users = [];
$blogPosts = [];
$savedJobs = [];
$savedJobIds = [];
$savedSearches = [];
$savedAiApplicationSearches = [];
$notifications = [];
$contactMessages = [];
$applicationEventsByApplication = [];
$interviewsByApplication = [];
$serviceMessagesByApplication = [];
$analytics = [];
$stats = ['openJobs' => '0', 'companies' => '0', 'jobSeekers' => '0', 'applications' => '0', 'users' => '0'];

if ($pdo) {
    $jobs = $pdo->query(
        "SELECT j.*, c.name AS company, c.industry, c.user_id AS company_user_id,
                u.full_name AS recruiter_name,
                GROUP_CONCAT(t.tag ORDER BY t.id SEPARATOR ',') AS tags
         FROM jobs j
         JOIN companies c ON c.id = j.company_id
         LEFT JOIN users u ON u.id = j.recruiter_id
         LEFT JOIN job_tags t ON t.job_id = j.id
         WHERE j.status = 'active' AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
         GROUP BY j.id
         ORDER BY j.created_at DESC"
    )->fetchAll();
    $allJobs = $jobs;

    if ($search !== '') {
        $jobs = array_values(array_filter($jobs, static function (array $job) use ($search): bool {
            $haystack = strtolower(implode(' ', [
                $job['title'] ?? '',
                $job['company'] ?? '',
                $job['location'] ?? '',
                $job['type'] ?? '',
                $job['tags'] ?? '',
                $job['description'] ?? '',
            ]));
            return str_contains($haystack, strtolower($search));
        }));
    }

    $jobs = array_values(array_filter($jobs, static function (array $job) use ($filterTypes, $filterLocations, $filterIndustries, $filterTags, $minSalary, $maxSalary): bool {
        if ($filterTypes && !in_array((string) ($job['type'] ?? ''), $filterTypes, true)) {
            return false;
        }
        if ($filterLocations && !in_array((string) ($job['location'] ?? ''), $filterLocations, true)) {
            return false;
        }
        if ($filterIndustries && !in_array((string) ($job['industry'] ?? ''), $filterIndustries, true)) {
            return false;
        }
        $jobTags = tags($job);
        if ($filterTags && !array_intersect($filterTags, $jobTags)) {
            return false;
        }
        if ($minSalary > 0 || $maxSalary > 0) {
            [$jobMinSalary, $jobMaxSalary] = salary_bounds($job['salary'] ?? '');
            if ($minSalary > 0 && $jobMaxSalary < $minSalary) {
                return false;
            }
            if ($maxSalary > 0 && $jobMinSalary > $maxSalary) {
                return false;
            }
        }
        return true;
    }));

    usort($jobs, static function (array $a, array $b) use ($jobSort): int {
        [$aMin, $aMax] = salary_bounds($a['salary'] ?? '');
        [$bMin, $bMax] = salary_bounds($b['salary'] ?? '');
        return match ($jobSort) {
            'oldest' => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')),
            'salary_high' => $bMax <=> $aMax,
            'salary_low' => $aMin <=> $bMin,
            default => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')),
        };
    });

    $companies = $pdo->query(
        "SELECT c.*, COUNT(j.id) AS jobs
         FROM companies c
         LEFT JOIN jobs j ON j.company_id = c.id AND j.status = 'active'
         GROUP BY c.id
         ORDER BY c.name"
    )->fetchAll();

    $applicants = $pdo->query(
        "SELECT a.*, j.title AS job_title, j.description AS job_description, j.requirements AS job_requirements, j.company_id, j.recruiter_id,
                c.name AS company, u.full_name AS recruiter_name, candidate.skills AS candidate_skills,
                candidate.cv_text AS candidate_cv_text, candidate.cv_ai_skills AS candidate_cv_ai_skills,
                candidate.cv_ai_years AS candidate_cv_ai_years, candidate.cv_ai_summary AS candidate_cv_ai_summary,
                candidate.cv_ai_json AS candidate_cv_ai_json,
                GROUP_CONCAT(t.tag ORDER BY t.id SEPARATOR ',') AS job_tags
         FROM applications a
         JOIN jobs j ON j.id = a.job_id
         JOIN companies c ON c.id = j.company_id
         LEFT JOIN users u ON u.id = j.recruiter_id
         LEFT JOIN users candidate ON candidate.id = a.user_id OR candidate.email = a.applicant_email
         LEFT JOIN job_tags t ON t.job_id = j.id
         GROUP BY a.id
         ORDER BY a.created_at DESC"
    )->fetchAll();

    $users = $pdo->query(
        "SELECT id, role, full_name, company_name, email, phone, status, created_at
         FROM users
         ORDER BY created_at DESC"
    )->fetchAll();

    $blogPosts = $pdo->query(
        "SELECT p.*, u.full_name AS author_name
         FROM blog_posts p
         LEFT JOIN users u ON u.id = p.author_id
         ORDER BY p.created_at DESC"
    )->fetchAll();

    if (isset($_SESSION['user']['id']) && ($_SESSION['user']['role'] ?? '') === 'jobseeker') {
        $savedStmt = $pdo->prepare(
            "SELECT j.*, c.name AS company, c.industry, c.user_id AS company_user_id,
                    u.full_name AS recruiter_name,
                    GROUP_CONCAT(t.tag ORDER BY t.id SEPARATOR ',') AS tags,
                    sj.created_at AS saved_at
             FROM saved_jobs sj
             JOIN jobs j ON j.id = sj.job_id
             JOIN companies c ON c.id = j.company_id
             LEFT JOIN users u ON u.id = j.recruiter_id
             LEFT JOIN job_tags t ON t.job_id = j.id
             WHERE sj.user_id = :user_id
             GROUP BY sj.id, j.id
             ORDER BY sj.created_at DESC"
        );
        $savedStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
        $savedJobs = $savedStmt->fetchAll();
        $savedJobIds = array_map(static fn(array $job): int => (int) $job['id'], $savedJobs);

        $savedSearchStmt = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = :user_id ORDER BY created_at DESC');
        $savedSearchStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
        $savedSearches = $savedSearchStmt->fetchAll();

    }

    if (isset($_SESSION['user']['id']) && is_admin_role($_SESSION['user']['role'] ?? null)) {
        $contactMessages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 40')->fetchAll();
        $savedAiStmt = $pdo->prepare("SELECT * FROM saved_searches WHERE user_id = :user_id AND types = 'ai_application_search' ORDER BY created_at DESC LIMIT 8");
        $savedAiStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
        $savedAiApplicationSearches = $savedAiStmt->fetchAll();
    }

    if (isset($_SESSION['user']['id'])) {
        $notificationStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 8');
        $notificationStmt->execute([':user_id' => (int) $_SESSION['user']['id']]);
        $notifications = $notificationStmt->fetchAll();
    }

    foreach ($pdo->query('SELECT * FROM application_events ORDER BY created_at ASC')->fetchAll() as $event) {
        $applicationEventsByApplication[(int) $event['application_id']][] = $event;
    }
    foreach ($pdo->query('SELECT * FROM interviews ORDER BY scheduled_at ASC')->fetchAll() as $interview) {
        $interviewsByApplication[(int) $interview['application_id']][] = $interview;
    }
    foreach ($pdo->query('SELECT sm.*, u.full_name, u.company_name FROM service_messages sm LEFT JOIN users u ON u.id = sm.sender_id ORDER BY sm.created_at ASC')->fetchAll() as $chatMessage) {
        $serviceMessagesByApplication[(int) $chatMessage['application_id']][] = $chatMessage;
    }

    $analytics = [
        'statuses' => $pdo->query('SELECT status, COUNT(*) AS total FROM applications GROUP BY status ORDER BY total DESC')->fetchAll(),
        'locations' => $pdo->query("SELECT location, COUNT(*) AS total FROM jobs WHERE status = 'active' GROUP BY location ORDER BY total DESC LIMIT 5")->fetchAll(),
        'types' => $pdo->query("SELECT type, COUNT(*) AS total FROM jobs WHERE status = 'active' GROUP BY type ORDER BY total DESC LIMIT 5")->fetchAll(),
        'expiring' => $pdo->query("SELECT title, expires_at FROM jobs WHERE status = 'active' AND expires_at IS NOT NULL ORDER BY expires_at ASC LIMIT 5")->fetchAll(),
        'jobs_by_company' => $pdo->query(
            "SELECT c.name AS company, COUNT(j.id) AS total
             FROM companies c
             LEFT JOIN jobs j ON j.company_id = c.id
             GROUP BY c.id, c.name
             HAVING total > 0
             ORDER BY total DESC
             LIMIT 5"
        )->fetchAll(),
    ];
    $skillCounts = [];
    $matchBuckets = ['Strong' => 0, 'Possible' => 0, 'Weak' => 0];
    foreach ($applicants as $applicationForAnalytics) {
        $screen = application_screen_data($applicationForAnalytics);
        $screenSkills = normalize_text_list($screen['skills'] ?? []);
        $screenTools = normalize_text_list($screen['tools'] ?? []);
        foreach (array_merge(
            selected_skills($applicationForAnalytics['candidate_skills'] ?? ''),
            selected_skills($applicationForAnalytics['candidate_cv_ai_skills'] ?? ''),
            $screenSkills,
            $screenTools
        ) as $skillName) {
            $skillName = trim((string) $skillName);
            if ($skillName === '') {
                continue;
            }
            $skillCounts[$skillName] = ($skillCounts[$skillName] ?? 0) + 1;
        }

        $analyticsSkills = normalize_skill_list(array_merge(
            selected_skills($applicationForAnalytics['candidate_skills'] ?? ''),
            selected_skills($applicationForAnalytics['candidate_cv_ai_skills'] ?? ''),
            selected_skills($applicationForAnalytics['cv_ai_skills'] ?? ''),
            $screenSkills,
            $screenTools
        ));
        $analyticsSignals = job_match_signals($applicationForAnalytics);
        $matchScore = (int) ($applicationForAnalytics['ai_match_score'] ?? 0);
        if ($matchScore <= 0 && $analyticsSkills && $analyticsSignals) {
            $lookup = array_flip(array_map('strtolower', $analyticsSkills));
            $matchedSignals = array_filter($analyticsSignals, static fn(string $signal): bool => isset($lookup[strtolower($signal)]));
            $matchScore = (int) round((count($matchedSignals) / max(1, count($analyticsSignals))) * 100);
        }
        if ($matchScore >= 70) {
            $matchBuckets['Strong']++;
        } elseif ($matchScore >= 40) {
            $matchBuckets['Possible']++;
        } else {
            $matchBuckets['Weak']++;
        }
    }
    arsort($skillCounts);
    $analytics['top_skills'] = array_map(
        static fn(string $skill, int $total): array => ['skill' => $skill, 'total' => $total],
        array_keys(array_slice($skillCounts, 0, 8, true)),
        array_values(array_slice($skillCounts, 0, 8, true))
    );
    $analytics['ai_match_distribution'] = array_map(
        static fn(string $fit, int $total): array => ['fit' => $fit, 'total' => $total],
        array_keys($matchBuckets),
        array_values($matchBuckets)
    );

    $stats = [
        'openJobs' => number_format((int) $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active' AND (expires_at IS NULL OR expires_at >= CURDATE())")->fetchColumn()),
        'companies' => number_format((int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn()),
        'jobSeekers' => number_format((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'jobseeker'")->fetchColumn()),
        'applications' => number_format((int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn()),
        'users' => number_format((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()),
    ];
}

$selectedJobId = (int) ($_GET['job'] ?? ($jobs[0]['id'] ?? 0));
$selectedJob = null;
foreach ($jobs as $job) {
    if ((int) $job['id'] === $selectedJobId) {
        $selectedJob = $job;
        break;
    }
}
$selectedJob ??= $jobs[0] ?? null;
$jobTypeValues = array_filter(array_map(static fn(array $job): string => (string) ($job['type'] ?? ''), $allJobs));
$jobLocationValues = array_filter(array_map(static fn(array $job): string => (string) ($job['location'] ?? ''), $allJobs));
$jobIndustryValues = array_filter(array_map(static fn(array $job): string => (string) ($job['industry'] ?? ''), $allJobs));
$jobTypeCounts = array_count_values($jobTypeValues);
$jobLocationCounts = array_count_values($jobLocationValues);
$jobIndustryCounts = array_count_values($jobIndustryValues);
$jobTagCounts = [];
$jobTypes = array_values(array_unique($jobTypeValues));
$jobLocations = array_values(array_unique($jobLocationValues));
$jobIndustries = array_values(array_unique($jobIndustryValues));
$jobTags = [];
foreach ($allJobs as $job) {
    foreach (tags($job) as $tag) {
        $jobTags[] = $tag;
        $jobTagCounts[$tag] = ($jobTagCounts[$tag] ?? 0) + 1;
    }
}
$jobTags = array_values(array_unique(array_filter($jobTags)));
sort($jobTypes);
sort($jobLocations);
sort($jobIndustries);
sort($jobTags);
$activeJobFilters = ($search !== '' ? 1 : 0) + count($filterTypes) + count($filterLocations) + count($filterIndustries) + count($filterTags) + ($minSalary > 0 ? 1 : 0) + ($maxSalary > 0 ? 1 : 0);
$faqItems = [
    ['question' => 'How do I apply for a job on KDXJobs?', 'answer' => 'Create a job seeker account, complete your profile, open a job that matches your goals, and submit your application with your CV.'],
    ['question' => 'Can I track my application progress?', 'answer' => 'Yes. KDXJobs shows application updates, interview scheduling, service messages, and status changes so you can follow your journey clearly.'],
    ['question' => 'How do saved searches work?', 'answer' => 'When you save a search, KDXJobs remembers your query, filters, and salary preferences, then notifies you when matching jobs are posted.'],
    ['question' => 'How can companies post jobs?', 'answer' => 'Companies can create an account, complete their company profile, and publish job posts from the dashboard.'],
    ['question' => 'What makes KDXJobs different from other recruitment services?', 'answer' => 'We focus on support, visibility, and guidance. We do not just collect applications. We stay with people through each important hiring step.'],
    ['question' => 'How do I contact KDXJobs support?', 'answer' => 'Use the Contact Us page to send a message. Admins receive it in their inbox and can follow up with you directly.'],
];
$filteredFaqItems = $faqItems;
if ($faqQuery !== '') {
    $filteredFaqItems = array_values(array_filter($faqItems, static function (array $item) use ($faqQuery): bool {
        $haystack = strtolower(($item['question'] ?? '') . ' ' . ($item['answer'] ?? ''));
        return str_contains($haystack, strtolower($faqQuery));
    }));
}
$user = $_SESSION['user'] ?? null;
$tab = strtolower((string) ($_GET['tab'] ?? ($page === 'admin' ? 'manage' : 'profile')));
$selectedApplicationId = (int) ($_GET['application'] ?? $_GET['application_id'] ?? 0);
$selectedApplication = null;

if ($page === 'application' && !$user) {
    $page = 'login';
    $error = $error ?: 'Please login to view the application details.';
}

if ($page === 'admin' && !is_admin_role($user['role'] ?? '')) {
    $page = 'login';
    $error = $error ?: 'Please login with the admin account to open the separated admin dashboard.';
}

if ($page === 'company' && (($user['role'] ?? '') !== 'company') && !is_admin_role($user['role'] ?? '')) {
    $page = 'login';
    $error = $error ?: 'Please login with a company account to open the company dashboard.';
}

if ($page === 'user' && !$user) {
    $page = 'login';
    $error = $error ?: 'Please login to open your job seeker dashboard.';
}

if ($page === 'application' && $user) {
    foreach ($applicants as $applicationItem) {
        if ((int) ($applicationItem['id'] ?? 0) !== $selectedApplicationId) {
            continue;
        }

        $allowed = false;
        if (is_admin_role($user['role'] ?? '')) {
            $allowed = is_super_admin() || (int) ($applicationItem['recruiter_id'] ?? 0) === (int) ($user['id'] ?? 0);
        } elseif (($user['role'] ?? '') === 'company') {
            $allowed = isset($user['id']) && (int) ($applicationItem['company_id'] ?? 0) > 0;
            if ($allowed) {
                foreach ($companies as $companyItem) {
                    if ((int) ($companyItem['id'] ?? 0) === (int) ($applicationItem['company_id'] ?? 0) && (int) ($companyItem['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) {
                        $selectedApplication = $applicationItem;
                        break;
                    }
                }
                $allowed = $selectedApplication !== null;
            }
        } else {
            $allowed = ((int) ($applicationItem['user_id'] ?? 0) === (int) ($user['id'] ?? 0))
                || (strcasecmp((string) ($applicationItem['applicant_email'] ?? ''), (string) ($user['email'] ?? '')) === 0);
        }

        if (is_admin_role($user['role'] ?? '') && $allowed) {
            $selectedApplication = $applicationItem;
        } elseif (($user['role'] ?? '') !== 'company' && $allowed) {
            $selectedApplication = $applicationItem;
        }
        break;
    }

    if (!$selectedApplication) {
        $page = 'home';
        $error = $error ?: 'That application could not be found or you do not have permission to view it.';
    }
}

function app_url(string $page, array $extra = []): string
{
    return app_base_path() . '/index.php?' . http_build_query(array_merge(['page' => $page], $extra));
}

function application_page_url(array $application, string $backPage = 'admin', string $backTab = 'applications'): string
{
    return app_url('application', [
        'application' => (int) ($application['id'] ?? 0),
        'back_page' => $backPage,
        'back_tab' => $backTab,
    ]);
}

function skill_options(): array
{
    return [
        'SQL', 'MySQL', 'Excel', 'Power BI', 'Tableau', 'Python', 'JavaScript', 'HTML', 'CSS', 'PHP', 'Laravel',
        'React', 'Tailwind', 'API', 'REST API', 'AWS', 'Cloud', 'GitHub', 'Data Analysis', 'Data Analytics',
        'Dashboarding', 'KPI Reporting', 'Accounting', 'Finance', 'Operations', 'Vendor Management',
        'Account Management', 'Digital Marketing', 'Commercial Analytics', 'Recruitment', 'Screening',
        'Interviewing', 'HR', 'Communication', 'Problem Solving', 'Project Management',
    ];
}

function job_title_options(): array
{
    return [
        'Accountant',
        'Administrative Assistant',
        'Business Development Officer',
        'Civil Engineer',
        'Customer Service Representative',
        'Data Analyst',
        'Finance Officer',
        'HR Officer',
        'IT Support Specialist',
        'Marketing Specialist',
        'Operations Coordinator',
        'Project Manager',
        'Recruiter',
        'Sales Executive',
        'Security Manager',
        'Software Engineer',
        'Supervisor',
        'Warehouse Officer',
    ];
}

function job_location_options(): array
{
    return [
        'Erbil',
        'Sulaymaniyah',
        'Duhok',
        'Baghdad',
        'Basra',
        'Kirkuk',
        'Mosul',
        'Najaf',
        'Remote',
        'Hybrid - Iraq',
    ];
}

function salary_range_options(): array
{
    return [
        '500,000 - 750,000 IQD',
        '750,000 - 1,000,000 IQD',
        '1,000,000 - 1,500,000 IQD',
        '1,500,000 - 2,000,000 IQD',
        '2,000,000 - 2,500,000 IQD',
        '2,500,000 - 3,000,000 IQD',
        '3,000,000 - 4,000,000 IQD',
        '4,000,000+ IQD',
        'Negotiable',
    ];
}

function select_options(array $options, string $placeholder, ?string $selected = null): string
{
    $html = '<option value="">' . h($placeholder) . '</option>';
    foreach ($options as $option) {
        $html .= '<option value="' . h($option) . '"' . ($selected === $option ? ' selected' : '') . '>' . h($option) . '</option>';
    }

    return $html;
}

function job_edit_form(array $job, array $companies, bool $canChangeCompany = true): string
{
    $selectedCompanyId = (int) ($job['company_id'] ?? 0);
    $expiresAt = trim((string) ($job['expires_at'] ?? ''));
    $expiresAtValue = $expiresAt !== '' ? date('Y-m-d', strtotime($expiresAt)) : '';
    ob_start();
    ?>
    <form method="post" class="application-edit-form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="update_job">
        <input type="hidden" name="redirect_tab" value="manage">
        <input type="hidden" name="job_id" value="<?= h((string) ($job['id'] ?? '')) ?>">
        <label class="label">Company
            <?php if ($canChangeCompany): ?>
                <select class="select" name="company_id" required>
                    <option value="">Select company</option>
                    <?php foreach ($companies as $companyOption): ?>
                        <option value="<?= h((string) ($companyOption['id'] ?? '')) ?>" <?= (int) ($companyOption['id'] ?? 0) === $selectedCompanyId ? 'selected' : '' ?>><?= h($companyOption['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="company_id" value="<?= h((string) $selectedCompanyId) ?>">
                <input class="input" value="<?= h($job['company'] ?? '') ?>" disabled>
            <?php endif; ?>
        </label>
        <label class="label">Job Title<select class="select" required name="title"><?= select_options(job_title_options(), 'Select job title', (string) ($job['title'] ?? '')) ?></select></label>
        <label class="label">Location<select class="select" required name="location"><?= select_options(job_location_options(), 'Select location', (string) ($job['location'] ?? '')) ?></select></label>
        <label class="label">Salary<select class="select" required name="salary"><?= select_options(salary_range_options(), 'Select salary range', (string) ($job['salary'] ?? '')) ?></select></label>
        <label class="label">Type
            <select class="select" name="type" required>
                <?php foreach (['Full-time', 'Remote', 'Hybrid', 'Contract'] as $typeOption): ?>
                    <option value="<?= h($typeOption) ?>" <?= ($job['type'] ?? '') === $typeOption ? 'selected' : '' ?>><?= h($typeOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="label">Status
            <select class="select" name="status" required>
                <?php foreach (['active', 'closed'] as $statusOption): ?>
                    <option value="<?= h($statusOption) ?>" <?= ($job['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= h(ucfirst($statusOption)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="label">Description
            <div class="editor-wrap" data-editor>
                <div class="editor-toolbar" aria-label="Description editor tools">
                    <button type="button" data-command="formatBlock" data-value="h3" title="Heading">H</button>
                    <button type="button" data-command="bold" title="Bold">B</button>
                    <button type="button" data-command="italic" title="Italic">I</button>
                    <button type="button" data-command="underline" title="Underline">U</button>
                    <button type="button" data-command="insertUnorderedList" title="Bullet list">•</button>
                    <button type="button" data-command="insertOrderedList" title="Numbered list">1.</button>
                    <button type="button" data-command="formatBlock" data-value="p" title="Paragraph">P</button>
                </div>
                <div class="rich-editor" contenteditable="true" data-placeholder="Describe the role, responsibilities, benefits, and working style."></div>
                <textarea class="rich-editor-source" name="description"><?= h($job['description'] ?? '') ?></textarea>
            </div>
        </label>
        <label class="label">Requirements<textarea class="textarea" name="requirements" rows="4"><?= h($job['requirements'] ?? '') ?></textarea></label>
        <label class="label">Application Deadline<input class="input" type="date" name="expires_at" value="<?= h($expiresAtValue) ?>"></label>
        <label class="label">Tags<input class="input" name="tags" value="<?= h($job['tags'] ?? '') ?>" placeholder="React, API, SQL"></label>
        <button class="btn">Save Job Changes</button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function skills_checkboxes(array $chosenSkills = []): string
{
    $summary = $chosenSkills ? implode(', ', $chosenSkills) : 'Select skills';
    $html = '<details class="skills-dropdown"><summary><span>' . h($summary) . '</span></summary><div class="skills-dropdown-menu">';
    foreach (skill_options() as $skill) {
        $checked = in_array($skill, $chosenSkills, true) ? ' checked' : '';
        $html .= '<label class="skill-option"><input type="checkbox" name="skills[]" value="' . h($skill) . '"' . $checked . '> ' . h($skill) . '</label>';
    }
    $html .= '</div></details>';

    return $html;
}

function tags(array $job): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) ($job['tags'] ?? '')))));
}

function status_class(string $status): string
{
    return match ($status) {
        'Accepted' => 'accepted',
        'Rejected' => 'rejected',
        'Interview' => 'shortlisted',
        'Shortlisted' => 'shortlisted',
        'Reviewed' => 'reviewed',
        default => 'new',
    };
}

function progress_html(string $status): string
{
    $steps = ['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted'];
    $current = array_search($status, $steps, true);
    $current = $current === false ? 0 : $current;
    $html = '<div class="progress-track">';
    foreach ($steps as $index => $step) {
        $state = $index < $current ? 'done' : ($index === $current ? 'current' : '');
        if ($status === 'Rejected' && $index > 0) {
            $state = '';
        }
        $html .= '<span class="progress-step ' . h($state) . '"><i></i>' . h($step) . '</span>';
    }
    $html .= '</div>';
    if ($status === 'Rejected') {
        $html .= '<p class="status-note rejected">This application was rejected.</p>';
    } elseif ($status === 'Shortlisted') {
        $html .= '<p class="status-note shortlisted">You are shortlisted. The company may contact you for the next step.</p>';
    } elseif ($status === 'Interview') {
        $html .= '<p class="status-note shortlisted">An interview has been scheduled. Check the timeline for details.</p>';
    } elseif ($status === 'Accepted') {
        $html .= '<p class="status-note accepted">Congratulations. Your application was accepted.</p>';
    } elseif ($status === 'Reviewed') {
        $html .= '<p class="status-note reviewed">Your application has been reviewed.</p>';
    }
    return $html;
}

function current_progress_html(string $status): string
{
    $notes = [
        'New' => 'New application',
        'Reviewed' => 'Reviewed by recruiter',
        'Shortlisted' => 'Shortlisted for next step',
        'Interview' => 'Interview scheduled',
        'Accepted' => 'Accepted',
        'Rejected' => 'Rejected',
    ];
    return '<span class="status-pill ' . h(status_class($status)) . '">' . h($status) . '</span>'
        . '<p class="tiny muted" style="margin:8px 0 0">' . h($notes[$status] ?? $status) . '</p>';
}

function status_note_for(string $status): string
{
    return match ($status) {
        'Reviewed' => 'Checked by recruiter',
        'Shortlisted' => 'Moved to next step',
        'Interview' => 'Interview stage',
        'Accepted' => 'Ready to hire',
        'Rejected' => 'Closed application',
        default => 'Waiting for review',
    };
}

function timeline_html(int $applicationId, array $eventsByApplication, array $interviewsByApplication): string
{
    $items = $eventsByApplication[$applicationId] ?? [];
    $html = '<div class="timeline-list">';
    if (!$items) {
        $html .= '<div class="timeline-item"><strong>Application received</strong><span class="tiny muted">Timeline will update as recruiters take action.</span></div>';
    }
    foreach ($items as $event) {
        $html .= '<div class="timeline-item"><strong>' . h($event['title'] ?? 'Update') . '</strong>';
        if (!empty($event['note'])) {
            $html .= '<span class="tiny muted">' . h($event['note']) . '</span>';
        }
        $html .= '<span class="tiny muted">' . h(date('M j, Y g:i A', strtotime((string) ($event['created_at'] ?? 'now')))) . '</span></div>';
    }
    foreach (($interviewsByApplication[$applicationId] ?? []) as $interview) {
        $html .= '<div class="timeline-item interview"><strong>Interview: ' . h(date('M j, Y g:i A', strtotime((string) $interview['scheduled_at']))) . '</strong>';
        $details = array_filter([$interview['location'] ?? '', $interview['note'] ?? '']);
        if ($details) {
            $html .= '<span class="tiny muted">' . h(implode(' - ', $details)) . '</span>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

function service_chat_html(array $application, array $messagesByApplication, bool $canReply): string
{
    $applicationId = (int) ($application['id'] ?? 0);
    $messages = $messagesByApplication[$applicationId] ?? [];
    $latestId = $messages ? max(array_map(static fn(array $message): int => (int) $message['id'], $messages)) : 0;
    $html = '<div class="service-chat" data-service-chat data-application-id="' . h((string) $applicationId) . '" data-latest-message-id="' . h((string) $latestId) . '"><div class="service-chat-head"><strong>Service Center Chat</strong><span class="tiny muted">Ask about this application</span></div><div class="service-chat-body">';
    $html .= service_chat_messages_html($messages);
    $html .= '</div>';
    if ($canReply) {
        $html .= '<form method="post" class="service-chat-form">' . csrf_input() . '<input type="hidden" name="action" value="send_service_message"><input type="hidden" name="application_id" value="' . h((string) $applicationId) . '"><textarea class="textarea" required name="chat_message" rows="2" placeholder="Write a message to the service center"></textarea><button class="btn">Send</button></form>';
    }
    return $html . '</div>';
}

function service_chat_messages_html(array $messages): string
{
    $html = '';
    if (!$messages) {
        $html .= '<p class="tiny muted" style="margin:0">No messages yet. Start a conversation with the service center.</p>';
    }
    foreach ($messages as $message) {
        $isSupport = in_array((string) ($message['sender_role'] ?? ''), ['admin', 'superadmin'], true);
        $sender = $isSupport ? 'Service Center' : (($message['full_name'] ?? '') ?: 'Job Seeker');
        $html .= '<div class="chat-bubble ' . ($isSupport ? 'support' : 'candidate') . '">';
        $html .= '<strong>' . h($sender) . '</strong><p>' . nl2br(h($message['message'] ?? '')) . '</p>';
        $html .= '<span class="tiny muted">' . h(date('M j, Y g:i A', strtotime((string) ($message['created_at'] ?? 'now')))) . '</span></div>';
    }
    return $html;
}

function filter_applications_list(array $applications, string $query, string $status, array $allApplications = []): array
{
    $query = strtolower(trim($query));
    $status = trim($status);
    $allApplications = $allApplications ?: $applications;

    return array_values(array_filter($applications, static function (array $application) use ($query, $status, $allApplications): bool {
        if ($status !== '' && ($application['status'] ?? '') !== $status) {
            return false;
        }

        if ($query === '') {
            return true;
        }

        return application_ai_search_matches($application, $query, $allApplications);
    }));
}

function application_search_text(array $application, ?array $match = null, array $allApplications = []): string
{
    $screen = application_screen_data($application);
    $education = education_entry_labels($screen['education'] ?? []);
    $match ??= candidate_match_score($application);
    $duplicates = $allApplications ? duplicate_application_reasons($application, $allApplications) : [];
    $quality = is_array($match['cv_quality'] ?? null) ? $match['cv_quality'] : [];

    return strtolower(implode(' ', array_filter([
        $application['applicant_name'] ?? '',
        $application['applicant_email'] ?? '',
        $application['applicant_phone'] ?? '',
        $application['role'] ?? '',
        $application['job_title'] ?? '',
        $application['company'] ?? '',
        $application['status'] ?? '',
        $application['recruiter_name'] ?? '',
        $application['job_tags'] ?? '',
        $application['candidate_skills'] ?? '',
        $application['candidate_cv_ai_skills'] ?? '',
        $application['cv_ai_skills'] ?? '',
        $application['cv_ai_summary'] ?? '',
        $application['candidate_cv_ai_summary'] ?? '',
        $application['cv_text'] ?? '',
        implode(' ', normalize_text_list($screen['skills'] ?? [])),
        implode(' ', normalize_text_list($screen['tools'] ?? [])),
        implode(' ', normalize_text_list($screen['roles'] ?? [])),
        implode(' ', $education),
        implode(' ', $match['matches'] ?? []),
        implode(' ', $match['missing'] ?? []),
        $duplicates ? 'duplicate repeated multiple ' . implode(' ', $duplicates) : '',
        !empty($quality) ? 'cv quality ' . ($quality['label'] ?? '') . ' ' . ($quality['score'] ?? '') . ' ' . implode(' ', $quality['warnings'] ?? []) : '',
        (string) ($match['fit_label'] ?? ''),
        ai_matching_mode_label((string) ($match['matching_mode'] ?? ai_matching_mode())),
    ])));
}

function application_ai_search_matches(array $application, string $query, array $allApplications = []): bool
{
    $match = candidate_match_score($application);
    $haystack = application_search_text($application, $match, $allApplications);
    $score = (int) ($match['score'] ?? 0);
    $duplicates = $allApplications ? duplicate_application_reasons($application, $allApplications) : [];

    $query = preg_replace('/[,+]/', ' ', strtolower($query)) ?? $query;
    $tokens = array_values(array_filter(preg_split('/\s+/', $query) ?: []));
    $stopWords = array_flip(['show', 'find', 'candidate', 'candidates', 'applicant', 'applicants', 'with', 'and', 'or', 'the', 'a', 'an', 'who', 'has', 'have', 'degree']);

    foreach ($tokens as $token) {
        $token = trim($token);
        if ($token === '' || isset($stopWords[$token])) {
            continue;
        }
        if (in_array($token, ['strong', 'best', 'high'], true)) {
            if ($score < 70) {
                return false;
            }
            continue;
        }
        if (in_array($token, ['possible', 'medium'], true)) {
            if ($score < 40) {
                return false;
            }
            continue;
        }
        if (in_array($token, ['weak', 'low'], true)) {
            if ($score >= 40) {
                return false;
            }
            continue;
        }
        if (in_array($token, ['duplicate', 'duplicates', 'repeated'], true)) {
            if (!$duplicates) {
                return false;
            }
            continue;
        }
        if (!str_contains($haystack, $token)) {
            return false;
        }
    }

    return true;
}

function duplicate_application_reasons(array $application, array $allApplications): array
{
    $email = strtolower(trim((string) ($application['applicant_email'] ?? '')));
    $cv = strtolower(trim((string) ($application['cv_file'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string) ($application['applicant_phone'] ?? '')) ?? '';
    $currentId = (int) ($application['id'] ?? 0);
    $emailCount = 0;
    $cvCount = 0;
    $phoneCount = 0;

    foreach ($allApplications as $other) {
        if ((int) ($other['id'] ?? 0) === $currentId) {
            continue;
        }
        if ($email !== '' && strtolower(trim((string) ($other['applicant_email'] ?? ''))) === $email) {
            $emailCount++;
        }
        if ($cv !== '' && strtolower(trim((string) ($other['cv_file'] ?? ''))) === $cv) {
            $cvCount++;
        }
        $otherPhone = preg_replace('/\D+/', '', (string) ($other['applicant_phone'] ?? '')) ?? '';
        if ($phone !== '' && strlen($phone) >= 7 && $otherPhone === $phone) {
            $phoneCount++;
        }
    }

    $reasons = [];
    if ($emailCount > 0) {
        $reasons[] = 'same email';
    }
    if ($cvCount > 0) {
        $reasons[] = 'same CV';
    }
    if ($phoneCount > 0) {
        $reasons[] = 'same phone';
    }

    return $reasons;
}

function applications_browser_controls(string $page, string $tab, string $query, string $status, array $statusOptions, int $total, array $savedAiSearches = []): void
{
    ?>
    <form class="application-toolbar" method="get">
        <input type="hidden" name="page" value="<?= h($page) ?>">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <div class="search-inner"><span>🔍</span><input name="applications_q" value="<?= h($query) ?>" placeholder="AI search: strong SQL Power BI bachelor, weak fit, same email"></div>
        <select class="select" name="applications_status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= h($statusOption) ?>" <?= $status === $statusOption ? 'selected' : '' ?>><?= h($statusOption) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Apply</button>
        <?php if ($query !== '' || $status !== ''): ?>
            <a class="btn outline" href="<?= h(app_url($page, ['tab' => $tab])) ?>">Clear</a>
        <?php endif; ?>
        <span class="toolbar-meta"><?= h((string) $total) ?> result<?= $total === 1 ? '' : 's' ?></span>
    </form>
    <?php if ($query !== '' || $savedAiSearches): ?>
        <div class="tiny muted" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0 0">
            <?php if ($query !== ''): ?>
                <form method="post" style="margin:0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save_ai_application_search">
                    <input type="hidden" name="applications_q" value="<?= h($query) ?>">
                    <button class="btn outline" type="submit">Save AI Search</button>
                </form>
            <?php endif; ?>
            <?php foreach ($savedAiSearches as $savedSearch): ?>
                <a class="badge" href="<?= h(app_url($page, ['tab' => $tab, 'applications_q' => $savedSearch['query_text'] ?? ''])) ?>"><?= h($savedSearch['query_text'] ?: 'Saved AI search') ?></a>
                <form method="post" data-confirm="Remove this saved AI search?" style="margin:0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="delete_ai_application_search">
                    <input type="hidden" name="saved_search_id" value="<?= h((string) $savedSearch['id']) ?>">
                    <button class="btn outline" type="submit" style="padding:6px 10px">Remove</button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
}

function applications_pagination(string $page, string $tab, string $query, string $status, int $currentPage, int $totalPages): void
{
    if ($totalPages <= 1) {
        return;
    }

    $buildUrl = static function (int $targetPage) use ($page, $tab, $query, $status): string {
        return app_url($page, [
            'tab' => $tab,
            'applications_q' => $query,
            'applications_status' => $status,
            'applications_page' => $targetPage,
        ]);
    };
    ?>
    <div class="pagination-bar">
        <a class="btn outline<?= $currentPage <= 1 ? ' is-disabled' : '' ?>" href="<?= h($currentPage <= 1 ? '#' : $buildUrl($currentPage - 1)) ?>">Previous</a>
        <span class="pagination-meta">Page <?= h((string) $currentPage) ?> of <?= h((string) $totalPages) ?></span>
        <a class="btn outline<?= $currentPage >= $totalPages ? ' is-disabled' : '' ?>" href="<?= h($currentPage >= $totalPages ? '#' : $buildUrl($currentPage + 1)) ?>">Next</a>
    </div>
    <?php
}

function profile_score(array $user): int
{
    $fields = [
        $user['full_name'] ?? $user['company_name'] ?? '',
        $user['email'] ?? '',
        $user['phone'] ?? '',
        $user['skills'] ?? $user['industry'] ?? '',
        $user['location'] ?? '',
        $user['cv_file'] ?? $user['logo_file'] ?? '',
    ];
    $complete = 0;
    foreach ($fields as $value) {
        if (trim((string) $value) !== '') {
            $complete++;
        }
    }
    return (int) round(($complete / count($fields)) * 100);
}

function selected_skills(?string $skills): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $skills))));
}

function normalize_skill_list(array $skills): array
{
    $normalized = [];
    foreach ($skills as $skill) {
        $skill = trim((string) $skill);
        if ($skill === '') {
            continue;
        }
        $key = strtolower($skill);
        $normalized[$key] = $skill;
    }

    return array_values($normalized);
}

function decode_cv_ai_json(?string $payload): array
{
    $decoded = json_decode((string) $payload, true);
    return is_array($decoded) ? $decoded : [];
}

function normalize_text_list(array $values): array
{
    return array_values(array_filter(array_map(static function ($value): string {
        return trim((string) $value);
    }, $values)));
}

function normalize_education_entries(array $entries): array
{
    $normalized = [];
    foreach ($entries as $entry) {
        if (is_string($entry)) {
            $label = trim($entry);
            if ($label !== '') {
                $normalized[] = [
                    'degree' => $label,
                    'field' => '',
                    'institution' => '',
                    'year' => '',
                    'raw' => $label,
                ];
            }
            continue;
        }
        if (!is_array($entry)) {
            continue;
        }

        $degree = trim((string) ($entry['degree'] ?? $entry['level'] ?? ''));
        $field = trim((string) ($entry['field'] ?? $entry['major'] ?? $entry['specialization'] ?? ''));
        $institution = trim((string) ($entry['institution'] ?? $entry['college'] ?? $entry['university'] ?? $entry['school'] ?? ''));
        $year = trim((string) ($entry['year'] ?? $entry['graduation_year'] ?? $entry['dates'] ?? ''));
        $raw = trim((string) ($entry['raw'] ?? implode(', ', array_filter([$degree, $field, $institution, $year]))));

        if ($degree === '' && $field === '' && $institution === '' && $year === '' && $raw === '') {
            continue;
        }

        $normalized[] = [
            'degree' => $degree,
            'field' => $field,
            'institution' => $institution,
            'year' => $year,
            'raw' => $raw,
        ];
    }

    $unique = [];
    foreach ($normalized as $entry) {
        $key = strtolower(implode('|', $entry));
        $unique[$key] = $entry;
    }

    return array_values($unique);
}

function education_entry_labels(array $entries): array
{
    $labels = [];
    foreach (normalize_education_entries($entries) as $entry) {
        $degreeField = trim(implode(' in ', array_filter([$entry['degree'], $entry['field']])));
        $parts = array_filter([$degreeField, $entry['institution'], $entry['year']]);
        $label = $parts ? implode(', ', $parts) : (string) $entry['raw'];
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    return array_values(array_unique($labels));
}

function ai_screening_enabled(): bool
{
    return defined('OPENAI_API_KEY') && trim((string) OPENAI_API_KEY) !== '';
}

function response_output_text(array $payload): string
{
    $text = trim((string) ($payload['output_text'] ?? ''));
    if ($text !== '') {
        return $text;
    }

    $parts = [];
    foreach (($payload['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') {
            continue;
        }
        foreach (($item['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                $parts[] = (string) $content['text'];
            }
        }
    }

    return trim(implode("\n", $parts));
}

function openai_cv_model(): string
{
    return (string) (defined('OPENAI_CV_MODEL') ? OPENAI_CV_MODEL : 'gpt-5.2');
}

function openai_model_supports_reasoning(string $model): bool
{
    return str_starts_with(strtolower($model), 'gpt-5');
}

function openai_structured_json(array $request, int $timeout = 35): ?array
{
    if (!ai_screening_enabled()) {
        return null;
    }

    $model = (string) ($request['model'] ?? openai_cv_model());
    if (openai_model_supports_reasoning($model) && !isset($request['reasoning'])) {
        $request['reasoning'] = ['effort' => 'low'];
    }

    $baseUrl = rtrim((string) OPENAI_BASE_URL, '/');
    $url = $baseUrl . '/responses';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ];
    $body = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return null;
    }

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            return null;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return null;
    }

    $jsonText = response_output_text($payload);
    if ($jsonText === '') {
        return null;
    }

    $decoded = json_decode($jsonText, true);
    return is_array($decoded) ? $decoded : null;
}

function openai_cv_screening(string $text, string $manualSkills = ''): ?array
{
    if (!ai_screening_enabled() || trim($text) === '') {
        return null;
    }

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['skills', 'tools', 'roles', 'industries', 'years', 'languages', 'education', 'certifications', 'achievements', 'contact_signals', 'strengths', 'warnings', 'summary'],
        'properties' => [
            'skills' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'tools' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'roles' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'industries' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'years' => [
                'anyOf' => [
                    ['type' => 'integer'],
                    ['type' => 'null'],
                ],
            ],
            'languages' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'education' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['degree', 'field', 'institution', 'year', 'raw'],
                    'properties' => [
                        'degree' => ['type' => 'string'],
                        'field' => ['type' => 'string'],
                        'institution' => ['type' => 'string'],
                        'year' => ['type' => 'string'],
                        'raw' => ['type' => 'string'],
                    ],
                ],
            ],
            'certifications' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'achievements' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'contact_signals' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'strengths' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'summary' => [
                'type' => 'string',
            ],
        ],
    ];

    $model = openai_cv_model();
    $request = [
        'model' => $model,
        'instructions' => 'You are an expert recruitment screening assistant. Extract every relevant CV signal for hiring: technical skills, tools, roles, industries, experience, education, languages, certifications, achievements, and risks. For education, capture the exact degree level, field/major, college or university name, graduation year/dates, and raw CV line when present. Be conservative, do not invent facts, and keep the summary practical for recruiters.',
        'input' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => "Manual profile skills: " . ($manualSkills !== '' ? $manualSkills : 'None provided') . "\n\nReturn JSON only. Merge manual skills only when they are supported by the CV or useful as profile context.\n\nCV text:\n" . substr($text, 0, 18000),
            ]],
        ]],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'cv_screening',
                'strict' => true,
                'schema' => $schema,
            ],
        ],
        'max_output_tokens' => 1600,
    ];

    $decoded = openai_structured_json($request, 35);
    if (!is_array($decoded)) {
        return null;
    }

    return [
        'skills' => normalize_skill_list($decoded['skills'] ?? []),
        'tools' => normalize_skill_list($decoded['tools'] ?? []),
        'roles' => normalize_skill_list($decoded['roles'] ?? []),
        'industries' => normalize_skill_list($decoded['industries'] ?? []),
        'years' => isset($decoded['years']) && $decoded['years'] !== null ? (int) $decoded['years'] : null,
        'languages' => normalize_skill_list($decoded['languages'] ?? []),
        'education' => normalize_education_entries($decoded['education'] ?? []),
        'certifications' => normalize_skill_list($decoded['certifications'] ?? []),
        'achievements' => array_values(array_filter(array_map('trim', $decoded['achievements'] ?? []))),
        'contact_signals' => normalize_skill_list($decoded['contact_signals'] ?? []),
        'strengths' => array_values(array_filter(array_map('trim', $decoded['strengths'] ?? []))),
        'warnings' => array_values(array_filter(array_map('trim', $decoded['warnings'] ?? []))),
        'summary' => trim((string) ($decoded['summary'] ?? '')),
        'provider' => 'openai',
        'model' => $model,
    ];
}

function upload_absolute_path(?string $relativePath): ?string
{
    $relativePath = trim(str_replace('\\', '/', (string) $relativePath));
    if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
        return null;
    }

    $uploadRoot = defined('UPLOAD_PUBLIC_ROOT')
        ? (string) UPLOAD_PUBLIC_ROOT
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    $baseUploadDir = realpath($uploadRoot);
    $filePath = realpath($uploadRoot . DIRECTORY_SEPARATOR . basename($relativePath));

    if (!$baseUploadDir || !$filePath || !str_starts_with($filePath, $baseUploadDir . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        return null;
    }

    return $filePath;
}

function command_available(string $command): bool
{
    static $cache = [];

    $command = trim($command);
    if ($command === '') {
        return false;
    }
    if (array_key_exists($command, $cache)) {
        return $cache[$command];
    }
    if (is_file($command)) {
        $cache[$command] = true;
        return true;
    }

    $finder = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'command';
    $args = $finder === 'where' ? [$finder, $command] : [$finder, '-v', $command];
    $cache[$command] = run_process_text($args, 5) !== '';
    return $cache[$command];
}

function run_process_text(array $command, int $timeoutSeconds = 20): string
{
    if (!$command || !function_exists('proc_open')) {
        return '';
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        return '';
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $output = '';
    $error = '';
    $started = time();
    while (true) {
        $output .= (string) stream_get_contents($pipes[1]);
        $error .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!($status['running'] ?? false)) {
            break;
        }
        if ((time() - $started) > $timeoutSeconds) {
            proc_terminate($process);
            break;
        }
        usleep(100000);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 && trim($output) === '') {
        return '';
    }

    return trim(preg_replace('/\s+/', ' ', $output) ?? $output);
}

function configured_binary(string $configured, array $fallbacks): string
{
    $configured = trim($configured);
    if ($configured !== '' && command_available($configured)) {
        return $configured;
    }
    foreach ($fallbacks as $fallback) {
        if (command_available($fallback)) {
            return $fallback;
        }
    }

    return '';
}

function decode_pdf_string(string $value): string
{
    $value = substr($value, 1, -1);
    $value = preg_replace('/\\\\([nrtbf()\\\\])/', ' ', $value) ?? $value;
    $value = preg_replace('/\\\\[0-7]{1,3}/', ' ', $value) ?? $value;

    return $value;
}

function extract_pdf_text_segments(string $chunk): array
{
    $segments = [];

    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $chunk, $arrays)) {
        foreach ($arrays[1] as $arrayBody) {
            $line = '';
            if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', (string) $arrayBody, $strings)) {
                foreach ($strings[0] as $stringValue) {
                    $line .= decode_pdf_string($stringValue);
                }
            }
            $line = trim($line);
            if ($line !== '') {
                $segments[] = $line;
            }
        }
    }

    if (preg_match_all('/(\((?:\\\\.|[^\\\\()])*\))\s*Tj/s', $chunk, $strings)) {
        foreach ($strings[1] as $stringValue) {
            $line = trim(decode_pdf_string($stringValue));
            if ($line !== '') {
                $segments[] = $line;
            }
        }
    }

    return $segments;
}

function external_pdf_text(string $filePath): string
{
    $pdftotext = configured_binary((string) (defined('PDFTOTEXT_BIN') ? PDFTOTEXT_BIN : ''), ['pdftotext']);
    if ($pdftotext === '') {
        return '';
    }

    return run_process_text([$pdftotext, '-layout', $filePath, '-'], 20);
}

function ocr_pdf_text(string $filePath): string
{
    $ocrmypdf = configured_binary((string) (defined('OCRMYPDF_BIN') ? OCRMYPDF_BIN : ''), ['ocrmypdf']);
    if ($ocrmypdf !== '') {
        $tmpOutput = tempnam(sys_get_temp_dir(), 'kdx_ocr_');
        if (is_string($tmpOutput)) {
            $ocrPdf = $tmpOutput . '.pdf';
            @unlink($tmpOutput);
            $langs = trim((string) (defined('TESSERACT_LANGS') ? TESSERACT_LANGS : 'eng')) ?: 'eng';
            run_process_text([$ocrmypdf, '--skip-text', '--language', $langs, $filePath, $ocrPdf], 90);
            if (is_file($ocrPdf)) {
                $text = external_pdf_text($ocrPdf);
                @unlink($ocrPdf);
                if (cv_text_is_usable($text)) {
                    return $text;
                }
            }
        }
    }

    $tesseract = configured_binary((string) (defined('TESSERACT_BIN') ? TESSERACT_BIN : ''), ['tesseract']);
    if ($tesseract === '') {
        return '';
    }

    $langs = trim((string) (defined('TESSERACT_LANGS') ? TESSERACT_LANGS : 'eng')) ?: 'eng';
    return run_process_text([$tesseract, $filePath, 'stdout', '-l', $langs], 45);
}

function cv_text_is_usable(string $text): bool
{
    $text = trim($text);
    if (strlen($text) < 180) {
        return false;
    }

    preg_match_all('/[A-Za-z]{3,}/', $text, $words);
    return count($words[0] ?? []) >= 25;
}

function extract_pdf_text(?string $relativePath): string
{
    $filePath = upload_absolute_path($relativePath);
    if (!$filePath) {
        return '';
    }

    $raw = (string) file_get_contents($filePath);
    if ($raw === '') {
        return '';
    }

    $chunks = [];
    if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $raw, $streams)) {
        foreach ($streams[1] as $stream) {
            $stream = trim((string) $stream);
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @zlib_decode($stream);
            }
            if (is_string($decoded) && $decoded !== '') {
                $chunks[] = $decoded;
            }
        }
    }
    if (!$chunks) {
        $chunks[] = $raw;
    }

    $textParts = [];
    foreach ($chunks as $chunk) {
        $objectText = extract_pdf_text_segments($chunk);
        if ($objectText) {
            $textParts = array_merge($textParts, $objectText);
            continue;
        }
        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $chunk, $matches)) {
            foreach ($matches[0] as $match) {
                $textParts[] = decode_pdf_string($match);
            }
        }
        if (preg_match_all('/[A-Za-z][A-Za-z0-9+#.,:;()\/&% -]{2,}/', $chunk, $matches)) {
            $textParts = array_merge($textParts, $matches[0]);
        }
    }

    $text = implode(' ', $textParts);
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    $text = trim($text);

    $externalText = external_pdf_text($filePath);
    if (strlen($externalText) > strlen($text) || (!cv_text_is_usable($text) && cv_text_is_usable($externalText))) {
        $text = $externalText;
    }

    if (!cv_text_is_usable($text)) {
        $ocrText = ocr_pdf_text($filePath);
        if (cv_text_is_usable($ocrText)) {
            $text = $ocrText;
        }
    }

    return trim(substr($text, 0, 20000));
}

function detect_experience_years(string $text): ?int
{
    $text = strtolower($text);
    $years = [];
    $patterns = [
        '/(\d{1,2})\s*\+?\s*(?:years|year|yrs|yr)\s+(?:of\s+)?(?:relevant\s+)?(?:work\s+)?experience/',
        '/(?:minimum|at least|required|requires?|need(?:s|ed)?|with)\s+(\d{1,2})\s*\+?\s*(?:years|year|yrs|yr)/',
        '/(\d{1,2})\s*\+?\s*(?:years|year|yrs|yr)/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $value = (int) $match;
                if ($value > 0 && $value <= 60) {
                    $years[] = $value;
                }
            }
        }
    }

    return $years ? max($years) : null;
}

function detect_languages(string $text): array
{
    $text = strtolower($text);
    $languages = [];
    $patterns = [
        'English' => '/\benglish\b/',
        'Arabic' => '/\barabic\b/',
        'Kurdish' => '/\bkurd(?:ish|i)\b/',
        'Turkish' => '/\bturkish\b/',
        'French' => '/\bfrench\b/',
        'German' => '/\bgerman\b/',
    ];

    foreach ($patterns as $label => $pattern) {
        if ($text !== '' && preg_match($pattern, $text)) {
            $languages[] = $label;
        }
    }

    return $languages;
}

function detect_education_levels(string $text): array
{
    $text = strtolower($text);
    $levels = [];
    $patterns = [
        'PhD' => '/\b(phd|doctorate)\b/',
        'Master' => '/\b(master|msc|m\.sc|mba)\b/',
        'Bachelor' => '/\b(bachelor|bsc|b\.sc|ba\b|bs\b)\b/',
        'Diploma' => '/\bdiploma\b/',
        'Certificate' => '/\bcertificate\b/',
    ];

    foreach ($patterns as $label => $pattern) {
        if ($text !== '' && preg_match($pattern, $text)) {
            $levels[] = $label;
        }
    }

    return $levels;
}

function detect_education_entries(string $text): array
{
    $entries = [];
    $levelPattern = '(PhD|Doctorate|Master(?:\'s)?|MSc|M\.Sc|MBA|Bachelor(?:\'s)?|BSc|B\.Sc|BA|BS|Diploma|Certificate)';
    $fieldPattern = '([A-Za-z][A-Za-z &\/.-]{2,90})';
    $institutionPattern = '((?:University|College|Institute|School|Faculty|Academy)\s+of\s+[A-Za-z][A-Za-z &\/.-]{2,90}|[A-Za-z][A-Za-z &\/.-]{2,90}\s+(?:University|College|Institute|School|Faculty|Academy))';
    $yearPattern = '((?:19|20)\d{2}|Present|Current)?';
    $patterns = [
        '/\b' . $levelPattern . '\s+(?:degree\s+)?(?:in|of)?\s*' . $fieldPattern . '\s*(?:,|\bat\b|\bfrom\b|\-|\|)?\s*' . $institutionPattern . '?\s*' . $yearPattern . '/i',
        '/' . $institutionPattern . '\s*(?:,|\-|\|)?\s*' . $levelPattern . '\s+(?:in|of)?\s*' . $fieldPattern . '?\s*' . $yearPattern . '/i',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $match) {
            $raw = trim(preg_replace('/\s+/', ' ', $match[0]) ?? $match[0]);
            if (strlen($raw) < 6 || strlen($raw) > 220) {
                continue;
            }

            $degree = '';
            $field = '';
            $institution = '';
            $year = '';
            foreach ($match as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }
                if ($degree === '' && preg_match('/^' . $levelPattern . '$/i', $part)) {
                    $degree = normalize_education_degree($part);
                    continue;
                }
                if ($institution === '' && preg_match('/\b(University|College|Institute|School|Faculty|Academy)\b/i', $part)) {
                    $institution = $part;
                    continue;
                }
                if ($year === '' && preg_match('/^(?:19|20)\d{2}|Present|Current$/i', $part)) {
                    $year = $part;
                    continue;
                }
                if ($field === '' && !preg_match('/\b(University|College|Institute|School|Faculty|Academy)\b/i', $part) && !preg_match('/^' . $levelPattern . '$/i', $part)) {
                    $field = trim($part, " ,-|\t\n\r\0\x0B");
                }
            }

            if ($degree === '' && preg_match('/\b' . $levelPattern . '\b/i', $raw, $degreeMatch)) {
                $degree = normalize_education_degree($degreeMatch[1]);
            }
            if ($year === '' && preg_match('/\b((?:19|20)\d{2})\b/', $raw, $yearMatch)) {
                $year = $yearMatch[1];
            }

            $entries[] = [
                'degree' => $degree,
                'field' => $field,
                'institution' => $institution,
                'year' => $year,
                'raw' => $raw,
            ];
        }
    }

    return normalize_education_entries($entries);
}

function normalize_education_degree(string $degree): string
{
    $degree = trim($degree);
    return match (strtolower(str_replace(['.', "'s"], '', $degree))) {
        'phd', 'doctorate' => 'PhD',
        'master', 'msc', 'msc', 'mba' => strcasecmp($degree, 'MBA') === 0 ? 'MBA' : 'Master',
        'bachelor', 'bsc', 'ba', 'bs' => 'Bachelor',
        'diploma' => 'Diploma',
        'certificate' => 'Certificate',
        default => $degree,
    };
}

function detect_contact_signals(string $text): array
{
    $signals = [];
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text)) {
        $signals[] = 'Email';
    }
    if (preg_match('/(?:\+?\d[\d .() -]{7,}\d)/', $text)) {
        $signals[] = 'Phone';
    }
    if (preg_match('/\blinkedin\b/i', $text)) {
        $signals[] = 'LinkedIn';
    }
    if (preg_match('/\bgithub\b/i', $text)) {
        $signals[] = 'GitHub';
    }
    if (preg_match('/\bportfolio\b|\bbehance\b|\bdribbble\b|\bwebsite\b/i', $text)) {
        $signals[] = 'Portfolio';
    }

    return $signals;
}

function detect_recent_roles(string $text): array
{
    $roles = [];
    $roleWords = 'Manager|Specialist|Analyst|Developer|Officer|Coordinator|Assistant|Engineer|Recruiter|Accountant|Supervisor|Consultant|Administrator';
    $months = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
    $pattern = '/\b([A-Z][A-Za-z&\/, ]{2,90}(?:' . $roleWords . ')[A-Za-z&\/, ]{0,70})\s+([A-Z][A-Za-z0-9&., ]{2,80})\s+\|\s*([A-Za-z ]{2,40})\s+\|\s*((?:' . $months . ')\s+\d{4}\s*(?:[-–—]\s*(?:Present|Current|(?:' . $months . ')\s+\d{4}))?)/i';

    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $role = preg_replace('/\s+/', ' ', trim((string) ($match[1] ?? ''))) ?? '';
            $company = preg_replace('/\s+/', ' ', trim((string) ($match[2] ?? ''))) ?? '';
            $location = preg_replace('/\s+/', ' ', trim((string) ($match[3] ?? ''))) ?? '';
            $dates = preg_replace('/\s+/', ' ', trim((string) ($match[4] ?? ''))) ?? '';
            $label = trim($role . ($company !== '' ? ' at ' . $company : '') . ($location !== '' ? ' in ' . $location : '') . ($dates !== '' ? ' (' . $dates . ')' : ''));
            if ($label !== '') {
                $roles[] = $label;
            }
        }
    }

    return array_values(array_unique($roles));
}

function cv_quality_report(string $text, array $screen = [], array $candidateSkills = [], array $requiredSkills = []): array
{
    $warnings = [];
    $score = 100;
    $readableLength = strlen(trim(preg_replace('/\s+/', ' ', $text) ?? ''));
    $contactSignals = normalize_text_list($screen['contact_signals'] ?? detect_contact_signals($text));
    $educationLabels = education_entry_labels(normalize_education_entries($screen['education'] ?? []));
    $detectedSkills = normalize_skill_list(array_merge(
        $candidateSkills,
        normalize_text_list($screen['skills'] ?? []),
        normalize_text_list($screen['tools'] ?? [])
    ));
    $candidateYears = $screen['years'] ?? detect_experience_years($text);
    $hasDates = $candidateYears !== null || preg_match('/\b(?:19|20)\d{2}\b|present|current|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec/i', $text);

    if ($readableLength < 180 || !cv_text_is_usable($text)) {
        $score -= 25;
        $warnings[] = 'Readable CV text is weak or missing. PDF may be scanned/image-based.';
    }
    if (!$contactSignals) {
        $score -= 20;
        $warnings[] = 'Missing contact info in CV text.';
    }
    if (!$educationLabels) {
        $score -= 20;
        $warnings[] = 'Missing education details.';
    }
    if (!$hasDates) {
        $score -= 15;
        $warnings[] = 'Missing clear dates for education or work experience.';
    }
    if (!$detectedSkills) {
        $score -= 20;
        $warnings[] = 'Missing recognizable skills.';
    } elseif ($requiredSkills) {
        $lookup = array_flip(array_map('strtolower', $detectedSkills));
        $matchedRequired = array_values(array_filter($requiredSkills, static fn(string $skill): bool => isset($lookup[strtolower($skill)])));
        if (count($matchedRequired) < min(2, count($requiredSkills))) {
            $score -= 10;
            $warnings[] = 'Few key job skills are visible in the CV.';
        }
    }

    $score = clamp_percent($score);
    $label = $score >= 80 ? 'Good CV' : ($score >= 55 ? 'Needs Review' : 'Poor CV Quality');

    return [
        'score' => $score,
        'label' => $label,
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function cv_screening_snapshot(string $text, string $manualSkills = ''): array
{
    $lowerText = strtolower($text);
    $detected = selected_skills($manualSkills);
    $experienceYears = detect_experience_years($text);

    foreach (skill_options() as $skill) {
        if ($lowerText !== '' && str_contains($lowerText, strtolower($skill))) {
            $detected[] = $skill;
        }
    }

    $detected = normalize_skill_list($detected);
    $languages = detect_languages($text);
    $educationEntries = detect_education_entries($text);
    $education = $educationEntries ?: normalize_education_entries(detect_education_levels($text));
    $educationLabels = education_entry_labels($education);
    $contactSignals = detect_contact_signals($text);
    $recentRoles = detect_recent_roles($text);
    $strengths = [];
    $warnings = [];

    if (count($detected) >= 4) {
        $strengths[] = 'Multiple relevant skills detected';
    } elseif ($detected) {
        $strengths[] = 'At least one relevant skill detected';
    } else {
        $warnings[] = 'No known platform skills detected';
    }

    if ($experienceYears !== null) {
        $strengths[] = $experienceYears . '+ years of experience mentioned';
    } else {
        $warnings[] = 'Years of experience not clearly stated';
    }

    if ($languages) {
        $strengths[] = 'Languages found: ' . implode(', ', $languages);
    }

    if ($educationLabels) {
        $strengths[] = 'Education found: ' . implode('; ', array_slice($educationLabels, 0, 2));
    } else {
        $warnings[] = 'Education level not clearly detected';
    }
    if ($recentRoles) {
        $strengths[] = 'Recent role found: ' . $recentRoles[0];
    }

    if ($contactSignals) {
        $strengths[] = 'Contact details found: ' . implode(', ', $contactSignals);
    } else {
        $warnings[] = 'No clear contact details found in CV text';
    }

    if (strlen(trim($text)) < 180) {
        $warnings[] = 'CV text looks short or difficult to extract from PDF';
    }
    if (!cv_text_is_usable($text)) {
        $hasOcr = configured_binary((string) (defined('OCRMYPDF_BIN') ? OCRMYPDF_BIN : ''), ['ocrmypdf']) !== ''
            || configured_binary((string) (defined('TESSERACT_BIN') ? TESSERACT_BIN : ''), ['tesseract']) !== '';
        $warnings[] = !$hasOcr
            ? 'CV may be scanned or image-based. Install/configure Tesseract OCR for better extraction.'
            : 'CV may be scanned or image-based. OCR was attempted but did not return enough readable text.';
    }

    $summary = $detected
        ? 'Detected skills: ' . implode(', ', array_slice($detected, 0, 8)) . '.'
        : 'CV uploaded, but no known skill keywords were detected. Add skills to the profile for better matching.';

    $signalParts = [];
    if ($experienceYears !== null) {
        $signalParts[] = $experienceYears . '+ years experience mentioned';
    }
    if ($languages) {
        $signalParts[] = 'Languages: ' . implode(', ', array_slice($languages, 0, 3));
    }
    if ($educationLabels) {
        $signalParts[] = 'Education: ' . implode('; ', array_slice($educationLabels, 0, 2));
    }
    if ($recentRoles) {
        $signalParts[] = 'Recent role: ' . $recentRoles[0];
    }
    if ($contactSignals) {
        $signalParts[] = 'Contact signals: ' . implode(', ', array_slice($contactSignals, 0, 3));
    }
    if ($signalParts) {
        $summary .= ' ' . implode('; ', $signalParts) . '.';
    }
    if ($warnings) {
        $summary .= ' Watch-outs: ' . implode('; ', array_slice($warnings, 0, 2)) . '.';
    }
    $quality = cv_quality_report($text, [
        'skills' => $detected,
        'tools' => [],
        'years' => $experienceYears,
        'education' => $education,
        'contact_signals' => $contactSignals,
    ], $detected);

    return [
        'skills' => $detected,
        'tools' => [],
        'roles' => $recentRoles,
        'industries' => [],
        'years' => $experienceYears,
        'languages' => $languages,
        'education' => $education,
        'certifications' => [],
        'achievements' => [],
        'contact_signals' => $contactSignals,
        'strengths' => $strengths,
        'warnings' => $warnings,
        'quality' => $quality,
        'summary' => $summary,
    ];
}

function screen_cv(?string $relativePath, string $manualSkills = ''): array
{
    $text = extract_pdf_text($relativePath);
    $snapshot = openai_cv_screening($text, $manualSkills);
    if (!$snapshot) {
        $snapshot = cv_screening_snapshot($text, $manualSkills);
    }
    $detectedSkills = normalize_skill_list(array_merge(
        $snapshot['skills'] ?? [],
        $snapshot['tools'] ?? []
    ));

    return [
        'text' => $text,
        'skills' => implode(', ', $detectedSkills),
        'years' => $snapshot['years'],
        'summary' => $snapshot['summary'],
        'json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function job_required_years(array $application): ?int
{
    return detect_experience_years(strip_tags(implode(' ', [
        $application['job_title'] ?? '',
        $application['job_requirements'] ?? '',
        $application['job_description'] ?? '',
    ])));
}

function job_match_signals(array $application): array
{
    $jobTags = selected_skills($application['job_tags'] ?? '');
    $jobText = strtolower(strip_tags(implode(' ', [
        $application['job_title'] ?? '',
        $application['job_requirements'] ?? '',
        $application['job_description'] ?? '',
    ])));

    $signals = $jobTags;
    foreach (skill_options() as $skill) {
        if ($jobText !== '' && str_contains($jobText, strtolower($skill))) {
            $signals[] = $skill;
        }
    }

    return normalize_skill_list($signals);
}

function clamp_percent(int $score): int
{
    return max(0, min(100, $score));
}

function cached_ai_job_match(array $application): ?array
{
    $stored = decode_cv_ai_json((string) ($application['ai_match_json'] ?? ''));
    if (!$stored || !isset($stored['score']) || !is_numeric($stored['score'])) {
        return null;
    }

    $currentMode = ai_matching_mode();
    if (($stored['matching_mode'] ?? '') !== $currentMode) {
        return null;
    }

    $stored['score'] = clamp_percent((int) $stored['score']);
    $stored['matches'] = normalize_skill_list($stored['matched_requirements'] ?? ($stored['matches'] ?? []));
    $stored['missing'] = normalize_skill_list($stored['gaps'] ?? ($stored['missing'] ?? []));
    $stored['strengths'] = array_values(array_filter(array_map('trim', $stored['strengths'] ?? [])));
    $stored['risks'] = array_values(array_filter(array_map('trim', $stored['risks'] ?? [])));
    $stored['summary'] = trim((string) ($stored['summary'] ?? ''));
    $stored['fit_label'] = trim((string) ($stored['fit_label'] ?? ''));
    $stored['confidence'] = trim((string) ($stored['confidence'] ?? ''));

    return $stored;
}

function openai_cv_job_match(array $application, array $candidateScreen, array $candidateSkills, array $signals): ?array
{
    $candidateText = trim((string) (($application['cv_text'] ?? '') ?: ($application['candidate_cv_text'] ?? '')));
    $jobText = trim(strip_tags(implode("\n", [
        'Title: ' . (string) ($application['job_title'] ?? ''),
        'Role applied for: ' . (string) ($application['role'] ?? ''),
        'Company: ' . (string) ($application['company'] ?? ''),
        'Tags: ' . (string) ($application['job_tags'] ?? ''),
        'Description: ' . (string) ($application['job_description'] ?? ''),
        'Requirements: ' . (string) ($application['job_requirements'] ?? ''),
    ])));

    if (!ai_screening_enabled() || ($candidateText === '' && !$candidateSkills) || $jobText === '') {
        return null;
    }

    $matchingMode = ai_matching_mode();
    $modeInstruction = match ($matchingMode) {
        'strict' => 'Use strict matching: require direct evidence for core requirements, penalize missing must-have skills, seniority, education, and experience gaps strongly, and avoid high scores for adjacent experience.',
        'flexible' => 'Use flexible matching: reward transferable skills, adjacent domain experience, growth potential, and partial matches while still naming important gaps.',
        default => 'Use balanced matching: weigh direct requirement evidence most, but give fair credit for related skills and transferable experience.',
    };

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['score', 'fit_label', 'matched_requirements', 'gaps', 'strengths', 'risks', 'summary', 'recommended_next_step', 'confidence'],
        'properties' => [
            'score' => ['type' => 'integer'],
            'fit_label' => ['type' => 'string'],
            'matched_requirements' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'gaps' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'strengths' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'risks' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'summary' => ['type' => 'string'],
            'recommended_next_step' => ['type' => 'string'],
            'confidence' => ['type' => 'string'],
        ],
    ];

    $candidateFacts = [
        'profile_skills' => $candidateSkills,
        'cv_skills' => $candidateScreen['skills'] ?? [],
        'tools' => $candidateScreen['tools'] ?? [],
        'roles' => $candidateScreen['roles'] ?? [],
        'industries' => $candidateScreen['industries'] ?? [],
        'years' => $candidateScreen['years'] ?? null,
        'languages' => $candidateScreen['languages'] ?? [],
        'education' => $candidateScreen['education'] ?? [],
        'certifications' => $candidateScreen['certifications'] ?? [],
        'achievements' => $candidateScreen['achievements'] ?? [],
        'summary' => $candidateScreen['summary'] ?? '',
    ];

    $model = openai_cv_model();
    $request = [
        'model' => $model,
        'instructions' => 'You are a senior technical recruiter. Match the candidate CV to the job requirements using all evidence: skills, tools, responsibilities, seniority, industry context, education, languages, certifications, achievements, and missing signals. Score 0-100. Be evidence-based and do not invent facts. ' . $modeInstruction,
        'input' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => "Return JSON only.\n\nMatching mode: {$matchingMode}\n\nJob signals detected by the platform: " . implode(', ', $signals) . "\n\nJob:\n" . substr($jobText, 0, 9000) . "\n\nCandidate structured facts:\n" . json_encode($candidateFacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\nCandidate CV text:\n" . substr($candidateText, 0, 16000),
            ]],
        ]],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'cv_job_match',
                'strict' => true,
                'schema' => $schema,
            ],
        ],
        'max_output_tokens' => 1400,
    ];

    $decoded = openai_structured_json($request, 40);
    if (!is_array($decoded) || !isset($decoded['score'])) {
        return null;
    }

    return [
        'score' => clamp_percent((int) $decoded['score']),
        'fit_label' => trim((string) ($decoded['fit_label'] ?? '')),
        'matched_requirements' => normalize_skill_list($decoded['matched_requirements'] ?? []),
        'gaps' => normalize_skill_list($decoded['gaps'] ?? []),
        'strengths' => array_values(array_filter(array_map('trim', $decoded['strengths'] ?? []))),
        'risks' => array_values(array_filter(array_map('trim', $decoded['risks'] ?? []))),
        'summary' => trim((string) ($decoded['summary'] ?? '')),
        'recommended_next_step' => trim((string) ($decoded['recommended_next_step'] ?? '')),
        'confidence' => trim((string) ($decoded['confidence'] ?? '')),
        'matching_mode' => $matchingMode,
        'provider' => 'openai',
        'model' => $model,
    ];
}

function save_ai_job_match(array $application, array $match): void
{
    global $pdo;

    $applicationId = (int) ($application['id'] ?? 0);
    if ($applicationId <= 0 || !($pdo instanceof PDO)) {
        return;
    }

    $payload = json_encode($match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE applications
         SET ai_match_score = :score,
             ai_match_fit = :fit,
             ai_match_summary = :summary,
             ai_match_json = :json,
             ai_match_updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        ':score' => clamp_percent((int) ($match['score'] ?? 0)),
        ':fit' => substr((string) ($match['fit_label'] ?? ''), 0, 40),
        ':summary' => (string) ($match['summary'] ?? ''),
        ':json' => $payload,
        ':id' => $applicationId,
    ]);
}

function save_application_cv_text(array $application, string $text): void
{
    global $pdo;

    $applicationId = (int) ($application['id'] ?? 0);
    if ($applicationId <= 0 || trim($text) === '' || !($pdo instanceof PDO)) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE applications SET cv_text = :text WHERE id = :id AND (cv_text IS NULL OR cv_text = "")');
    $stmt->execute([
        ':text' => $text,
        ':id' => $applicationId,
    ]);
}

function application_screen_data(array $application): array
{
    $screen = decode_cv_ai_json((string) (($application['cv_ai_json'] ?? '') ?: ($application['candidate_cv_ai_json'] ?? '')));
    $candidateText = (string) (($application['cv_text'] ?? '') ?: ($application['candidate_cv_text'] ?? ''));
    if (trim($candidateText) === '' && !empty($application['cv_file'])) {
        $candidateText = extract_pdf_text((string) $application['cv_file']);
        save_application_cv_text($application, $candidateText);
    }

    if (!$screen && trim($candidateText) !== '') {
        $screen = cv_screening_snapshot($candidateText, implode(', ', normalize_skill_list(array_merge(
            selected_skills($application['candidate_skills'] ?? ''),
            selected_skills($application['candidate_cv_ai_skills'] ?? ''),
            selected_skills($application['cv_ai_skills'] ?? '')
        ))));
    }

    if ($screen) {
        $screen['education'] = normalize_education_entries($screen['education'] ?? []);
    }

    return array_merge([
        'skills' => [],
        'tools' => [],
        'roles' => [],
        'industries' => [],
        'years' => null,
        'languages' => [],
        'education' => [],
        'certifications' => [],
        'achievements' => [],
        'contact_signals' => [],
        'strengths' => [],
        'warnings' => [],
        'quality' => null,
        'summary' => '',
    ], $screen);
}

function candidate_match_score(array $application): array
{
    $candidateText = (string) (($application['cv_text'] ?? '') ?: ($application['candidate_cv_text'] ?? ''));
    if (trim($candidateText) === '' && !empty($application['cv_file'])) {
        $candidateText = extract_pdf_text((string) $application['cv_file']);
        save_application_cv_text($application, $candidateText);
    }
    $savedScreen = application_screen_data($application);
    $candidateSkills = normalize_skill_list(array_merge(
        selected_skills($application['candidate_skills'] ?? ''),
        selected_skills($application['candidate_cv_ai_skills'] ?? ''),
        selected_skills($application['cv_ai_skills'] ?? '')
    ));
    $jobText = strip_tags(implode(' ', [
        $application['job_title'] ?? '',
        $application['job_requirements'] ?? '',
        $application['job_description'] ?? '',
    ]));
    $signals = job_match_signals($application);
    $candidateScreen = $savedScreen ?: cv_screening_snapshot($candidateText, implode(', ', $candidateSkills));
    $candidateSkills = normalize_skill_list(array_merge(
        $candidateSkills,
        normalize_text_list($candidateScreen['skills'] ?? []),
        normalize_text_list($candidateScreen['tools'] ?? [])
    ));
    $cvQuality = cv_quality_report($candidateText, $candidateScreen, $candidateSkills, $signals);
    $jobLanguages = detect_languages($jobText);
    $jobEducation = detect_education_levels($jobText);
    $requiredYears = job_required_years($application);
    $candidateYears = $candidateScreen['years'] ?? null;
    foreach ([
        $application['cv_ai_years'] ?? null,
        $application['candidate_cv_ai_years'] ?? null,
        detect_experience_years((string) ($application['cv_text'] ?? '')),
        detect_experience_years((string) ($application['candidate_cv_text'] ?? '')),
        detect_experience_years((string) ($application['cv_ai_summary'] ?? '')),
        detect_experience_years((string) ($application['candidate_cv_ai_summary'] ?? '')),
    ] as $years) {
        if ($years !== null && (int) $years > 0) {
            $candidateYears = max((int) ($candidateYears ?? 0), (int) $years);
        }
    }

    $candidateLookup = array_flip(array_map('strtolower', $candidateSkills));
    $matches = array_values(array_filter($signals, static fn(string $signal): bool => isset($candidateLookup[strtolower($signal)])));
    $missing = array_values(array_filter($signals, static fn(string $signal): bool => !isset($candidateLookup[strtolower($signal)])));
    $screenLanguages = $candidateScreen['languages'] ?? [];
    $screenEducation = normalize_education_entries($candidateScreen['education'] ?? []);
    $screenEducationLabels = education_entry_labels($screenEducation);
    $languageMatches = array_values(array_intersect($jobLanguages, $screenLanguages));
    $languageMissing = array_values(array_diff($jobLanguages, $screenLanguages));
    $educationHaystack = strtolower(implode(' ', $screenEducationLabels));
    $educationMatches = array_values(array_filter($jobEducation, static fn(string $level): bool => $educationHaystack !== '' && str_contains($educationHaystack, strtolower($level))));
    $educationMissing = array_values(array_diff($jobEducation, $educationMatches));
    $skillScore = ($candidateSkills && $signals) ? (int) round((count($matches) / max(1, count($signals))) * 100) : 0;
    $score = $skillScore;
    $matchingMode = ai_matching_mode();
    $reasons = [];
    if (!$candidateSkills || !$signals) {
        $reasons[] = 'Add candidate skills, job tags, or readable CV screening data to calculate a stronger local fallback match.';
    } else {
        $reasons[] = $matches ? 'Matched: ' . implode(', ', array_slice($matches, 0, 6)) : 'No required job skills matched yet.';
    }
    if ($missing) {
        $reasons[] = 'Missing/not detected: ' . implode(', ', array_slice($missing, 0, 5));
    }
    if ($jobLanguages) {
        if ($languageMatches) {
            $score = min(100, $score + min(10, count($languageMatches) * 4));
            $reasons[] = 'Language match: ' . implode(', ', $languageMatches) . '.';
        }
        if ($languageMissing) {
            $score = max(0, $score - min(15, count($languageMissing) * 6));
            $reasons[] = 'Missing job language signals: ' . implode(', ', $languageMissing) . '.';
        }
    }
    if ($jobEducation) {
        if ($educationMatches) {
            $score = min(100, $score + 8);
            $reasons[] = 'Education match: ' . implode(', ', $educationMatches) . '.';
        } elseif ($educationMissing) {
            $score = max(0, $score - 10);
            $reasons[] = 'Required education not clearly detected: ' . implode(', ', $educationMissing) . '.';
        }
    }
    if ($requiredYears !== null) {
        if ($candidateYears === null) {
            $score = min($score, 70);
            $reasons[] = 'Experience check: job asks ' . $requiredYears . '+ years, but the CV did not show a clear number of years.';
        } elseif ($candidateYears < $requiredYears) {
            $gap = $requiredYears - $candidateYears;
            $score = min($score, max(0, $skillScore - min(45, 20 + ($gap * 10))));
            $reasons[] = 'Experience gap: job asks ' . $requiredYears . '+ years, CV shows about ' . $candidateYears . ' year' . ($candidateYears === 1 ? '' : 's') . '. Not a strong match.';
        } else {
            $reasons[] = 'Experience check passed: job asks ' . $requiredYears . '+ years, CV shows about ' . $candidateYears . '+ years.';
        }
    }
    $summary = trim((string) (($application['cv_ai_summary'] ?? '') ?: ($application['candidate_cv_ai_summary'] ?? '')));
    if ($summary !== '') {
        $reasons[] = $summary;
    }
    if ($matchingMode === 'strict') {
        $score = (int) round($score * 0.9);
        if ($missing) {
            $score = max(0, $score - min(40, count($missing) * 10));
            $reasons[] = 'Strict mode: missing core requirements reduce the score more strongly.';
        }
        if ($requiredYears !== null && ($candidateYears === null || $candidateYears < $requiredYears)) {
            $score = min($score, 50);
        }
    } elseif ($matchingMode === 'flexible') {
        $transferableSignals = array_values(array_intersect(
            array_map('strtolower', $candidateSkills),
            array_map('strtolower', ['Data Analysis', 'Data Analytics', 'Communication', 'Problem Solving', 'Operations', 'Project Management', 'Account Management'])
        ));
        if ($matches || $transferableSignals) {
            $score = min(100, $score + 18);
            $reasons[] = 'Flexible mode: transferable and adjacent skills receive extra credit.';
        }
        if ($missing) {
            $score = min(100, $score + min(12, count($missing) * 3));
        }
    } else {
        $reasons[] = 'Balanced mode: direct evidence and related experience are weighted evenly.';
    }

    $result = [
        'score' => clamp_percent($score),
        'matches' => $matches,
        'missing' => $missing,
        'language_matches' => $languageMatches,
        'language_missing' => $languageMissing,
        'education_matches' => $educationMatches,
        'education_missing' => $educationMissing,
        'screen_strengths' => $candidateScreen['strengths'] ?? [],
        'screen_warnings' => $candidateScreen['warnings'] ?? [],
        'screen_languages' => $screenLanguages,
        'screen_education' => $screenEducationLabels,
        'screen_education_entries' => $screenEducation,
        'screen_contact_signals' => $candidateScreen['contact_signals'] ?? [],
        'cv_quality' => $cvQuality,
        'total' => count($signals),
        'required_years' => $requiredYears,
        'candidate_years' => $candidateYears,
        'matching_mode' => $matchingMode,
        'reasons' => $reasons,
    ];

    $aiMatch = cached_ai_job_match($application);
    if (!$aiMatch) {
        $aiMatch = openai_cv_job_match($application, $candidateScreen, $candidateSkills, $signals);
        if ($aiMatch) {
            save_ai_job_match($application, $aiMatch);
        }
    }

    if ($aiMatch) {
        $aiReasons = [];
        if (!empty($aiMatch['summary'])) {
            $aiReasons[] = (string) $aiMatch['summary'];
        }
        if (!empty($aiMatch['strengths'])) {
            $aiReasons[] = 'AI strengths: ' . implode('; ', array_slice($aiMatch['strengths'], 0, 3));
        }
        if (!empty($aiMatch['risks'])) {
            $aiReasons[] = 'AI gaps/risks: ' . implode('; ', array_slice($aiMatch['risks'], 0, 3));
        }
        if (!empty($aiMatch['recommended_next_step'])) {
            $aiReasons[] = 'Next step: ' . (string) $aiMatch['recommended_next_step'];
        }

        $result['score'] = (int) $aiMatch['score'];
        $result['fit_label'] = $aiMatch['fit_label'] !== '' ? $aiMatch['fit_label'] : null;
        $result['matches'] = $aiMatch['matches'] ?: ($aiMatch['matched_requirements'] ?? $matches);
        $result['missing'] = $aiMatch['missing'] ?: ($aiMatch['gaps'] ?? $missing);
        $result['reasons'] = $aiReasons ?: $reasons;
        $result['ai_provider'] = $aiMatch['provider'] ?? 'openai';
        $result['ai_model'] = $aiMatch['model'] ?? openai_cv_model();
        $result['ai_confidence'] = $aiMatch['confidence'] ?? '';
    }

    return $result;
}

function candidate_match_html(array $application): string
{
    $match = candidate_match_score($application);
    $score = (int) $match['score'];
    $class = $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low');
    $fitLabel = !empty($match['fit_label']) ? (string) $match['fit_label'] : ($score >= 80 ? 'Strong Fit' : ($score >= 55 ? 'Possible Fit' : 'Weak Fit'));
    $matches = $match['matches'] ? implode(', ', array_slice($match['matches'], 0, 6)) : 'No matched requirements yet';
    $reasons = implode(' ', array_slice($match['reasons'] ?? [], 0, 3));
    $cvQuality = is_array($match['cv_quality'] ?? null) ? $match['cv_quality'] : null;
    $cvQualityHtml = $cvQuality
        ? '<span class="tiny muted cv-quality-line"><strong>CV Quality: ' . h((string) ($cvQuality['score'] ?? 0)) . '% - ' . h((string) ($cvQuality['label'] ?? 'Review')) . '</strong></span>'
        : '';
    $insightParts = [];
    if (!empty($match['ai_model'])) {
        $modelLine = (string) $match['ai_model'];
        if (!empty($match['ai_confidence'])) {
            $modelLine .= ' · Confidence: ' . (string) $match['ai_confidence'];
        }
        $insightParts[] = '<p><strong>AI Model:</strong> ' . h($modelLine) . '</p>';
    }
    if (!empty($match['matching_mode'])) {
        $insightParts[] = '<p><strong>Mode:</strong> ' . h(ucfirst((string) $match['matching_mode'])) . '</p>';
    }
    if (!empty($match['screen_languages'])) {
        $insightParts[] = '<p><strong>Languages:</strong> ' . h(implode(', ', $match['screen_languages'])) . '</p>';
    }
    if (!empty($match['screen_education'])) {
        $insightParts[] = '<p><strong>Education:</strong> ' . h(implode(', ', $match['screen_education'])) . '</p>';
    }
    if (!empty($match['screen_contact_signals'])) {
        $insightParts[] = '<p><strong>Contact Signals:</strong> ' . h(implode(', ', $match['screen_contact_signals'])) . '</p>';
    }
    if (!empty($match['cv_quality']) && is_array($match['cv_quality'])) {
        $qualityWarnings = $match['cv_quality']['warnings'] ?? [];
        $qualityLine = (string) ($match['cv_quality']['score'] ?? 0) . '% - ' . (string) ($match['cv_quality']['label'] ?? 'CV Quality');
        if ($qualityWarnings) {
            $qualityLine .= '. ' . implode('; ', array_slice($qualityWarnings, 0, 4));
        }
        $insightParts[] = '<p><strong>CV Quality:</strong> ' . h($qualityLine) . '</p>';
    }
    if (!empty($match['screen_strengths'])) {
        $insightParts[] = '<p><strong>Strengths:</strong> ' . h(implode('; ', array_slice($match['screen_strengths'], 0, 3))) . '</p>';
    }
    if (!empty($match['screen_warnings'])) {
        $insightParts[] = '<p><strong>Watch-outs:</strong> ' . h(implode('; ', array_slice($match['screen_warnings'], 0, 3))) . '</p>';
    }
    return '<div class="match-score ' . h($class) . '">'
        . '<span class="tiny muted">AI CV Match</span>'
        . '<strong>' . h((string) $score) . '%</strong>'
        . '<span class="tiny muted">' . h($fitLabel) . ' · ' . h(ai_matching_mode_label((string) ($match['matching_mode'] ?? ai_matching_mode()))) . ' mode</span>'
        . '<div class="score-bar"><span style="width:' . h((string) $score) . '%"></span></div>'
        . '<span class="tiny muted">' . h($matches) . '</span>'
        . $cvQualityHtml
        . ($reasons !== '' || $insightParts ? '<details class="match-details"><summary>Why?</summary><p>' . h($reasons) . '</p>' . implode('', $insightParts) . '</details>' : '')
        . '</div>';
}

function cv_upload_field(string $name, bool $required = false, string $note = 'PDF only. Maximum size: 2 MB.'): string
{
    return '<div class="cv-upload">'
        . '<span class="cv-upload-icon" aria-hidden="true">'
        . '<svg viewBox="0 0 48 48" role="img" focusable="false"><path d="M14 4h15l9 9v31H14z"/><path d="M29 4v10h9"/><path d="M24 34V18"/><path d="M18 24l6-6 6 6"/><path d="M17 38h14"/></svg>'
        . '</span>'
        . '<span class="cv-upload-copy"><strong>Upload CV</strong><span>' . h($note) . '</span>'
        . '<input class="cv-upload-input" type="file" name="' . h($name) . '" accept="application/pdf"' . ($required ? ' required' : '') . ' data-file-name-target>'
        . '<span class="cv-upload-file" data-file-name>Choose a PDF file</span></span>'
        . '</div>';
}

function application_edit_form(array $application, string $redirectTab = 'applications', string $redirectPage = '', array $extra = []): string
{
    $redirectPage = $redirectPage !== '' ? $redirectPage : ((string) ($_GET['page'] ?? 'admin'));
    ob_start();
    ?>
    <form method="post" class="application-edit-form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="edit_application">
        <input type="hidden" name="redirect_page" value="<?= h($redirectPage) ?>">
        <input type="hidden" name="redirect_tab" value="<?= h($redirectTab) ?>">
        <input type="hidden" name="application_id" value="<?= h((string) ($application['id'] ?? '')) ?>">
        <?php foreach ($extra as $extraKey => $extraValue): ?>
            <input type="hidden" name="<?= h((string) $extraKey) ?>" value="<?= h((string) $extraValue) ?>">
        <?php endforeach; ?>
        <label class="label">Applicant Name<input class="input" name="applicant_name" value="<?= h($application['applicant_name'] ?? '') ?>" required></label>
        <label class="label">Applicant Email<input class="input" type="email" name="applicant_email" value="<?= h($application['applicant_email'] ?? '') ?>" required></label>
        <label class="label">Phone<input class="input" name="applicant_phone" value="<?= h($application['applicant_phone'] ?? '') ?>"></label>
        <label class="label">Applied Role / Title<input class="input" name="role" value="<?= h($application['role'] ?? '') ?>" required></label>
        <label class="label">Status
            <select class="select" name="status" required>
                <?php foreach (['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'] as $statusOption): ?>
                    <option value="<?= h($statusOption) ?>" <?= ($application['status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= h($statusOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="label edit-cover">Cover Note<textarea class="textarea" name="cover_note" rows="5"><?= h($application['cover_note'] ?? '') ?></textarea></label>
        <button class="btn">Save Application Changes</button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function rescreen_application_form(array $application, string $redirectPage, string $redirectTab = 'applications', array $extra = []): string
{
    if (!is_admin_role() && current_role() !== 'company') {
        return '';
    }

    ob_start();
    ?>
    <form method="post" data-confirm="Refresh AI screening for this application?">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="rescreen_application_ai">
        <input type="hidden" name="redirect_page" value="<?= h($redirectPage) ?>">
        <input type="hidden" name="redirect_tab" value="<?= h($redirectTab) ?>">
        <input type="hidden" name="application_id" value="<?= h((string) ($application['id'] ?? '')) ?>">
        <?php foreach ($extra as $extraKey => $extraValue): ?>
            <input type="hidden" name="<?= h((string) $extraKey) ?>" value="<?= h((string) $extraValue) ?>">
        <?php endforeach; ?>
        <button class="btn outline" type="submit">Re-screen with AI</button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function applications_table(array $applications, string $page, string $applicationSearch): void
{
    ?>
    <form class="search" method="get" style="margin:0 0 20px">
        <input type="hidden" name="page" value="<?= h($page) ?>">
        <input type="hidden" name="tab" value="manage">
        <div class="search-inner"><span>🔍</span><input name="app_q" value="<?= h($applicationSearch) ?>" placeholder="Search applicant, email, job, company, or status"></div>
        <button class="btn">Search</button>
        <?php if ($applicationSearch !== ''): ?><a class="btn outline" href="<?= h(app_url($page, ['tab' => 'manage'])) ?>">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Applicant</th><th>Job</th><th>Match</th><th>CV</th><th>Status</th><th>Progress</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (!$applications): ?>
                    <tr><td colspan="7"><strong>No applications found.</strong></td></tr>
                <?php endif; ?>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="applicant-cell"><div class="table-primary"><a href="<?= h(application_page_url($a, $page, 'manage')) ?>"><?= h($a['applicant_name']) ?></a></div><div class="table-secondary"><?= h($a['applicant_email']) ?></div></td>
                        <td><?= h($a['job_title']) ?><br><span class="tiny muted"><?= h($a['company']) ?><?= !empty($a['recruiter_name']) ? ' · Recruiter: ' . h($a['recruiter_name']) : '' ?></span></td>
                        <td class="match-cell"><?= candidate_match_html($a) ?></td>
                        <td class="cv-cell"><?= cv_link_html($a['cv_file'] ?? null, 'View CV') ?></td>
                        <td class="status-cell"><span class="status-pill <?= h(status_class($a['status'])) ?>"><?= h($a['status']) ?></span></td>
                        <td class="compact-progress"><?= current_progress_html($a['status']) ?></td>
                        <td class="actions-cell">
                            <a class="btn outline table-open-btn" href="<?= h(application_page_url($a, $page, 'manage')) ?>">Open</a>
                            <?= rescreen_application_form($a, $page, 'manage') ?>
                            <form class="table-actions" method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="application_status">
                                <input type="hidden" name="redirect_tab" value="manage">
                                <input type="hidden" name="application_id" value="<?= h((string) $a['id']) ?>">
                                <select class="select" name="status" data-auto-submit>
                                    <?php foreach (['New', 'Reviewed', 'Shortlisted', 'Accepted', 'Rejected'] as $statusOption): ?>
                                        <option value="<?= h($statusOption) ?>" <?= $a['status'] === $statusOption ? 'selected' : '' ?>><?= h($statusOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function applications_hiring_table(array $applications, string $page, string $redirectTab = 'applications', array $allApplications = []): void
{
    $allApplications = $allApplications ?: $applications;
    ?>
    <div class="table-wrap friendly-app-table">
        <table class="data-table">
            <thead>
                <tr><th>Candidate</th><th>Role</th><th>AI Match</th><th>Status</th><th>Progress</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (!$applications): ?>
                    <tr><td colspan="6"><strong>No applications found.</strong></td></tr>
                <?php endif; ?>
                <?php foreach ($applications as $a): ?>
                    <?php $duplicateReasons = duplicate_application_reasons($a, $allApplications); ?>
                    <tr>
                        <td class="applicant-cell">
                            <div class="candidate-stack">
                                <div class="candidate-avatar"><?= h(strtoupper(substr((string) ($a['applicant_name'] ?? 'A'), 0, 1))) ?></div>
                                <div>
                                    <div class="table-primary"><a href="<?= h(application_page_url($a, $page, $redirectTab)) ?>"><?= h($a['applicant_name']) ?></a></div>
                                    <div class="table-secondary"><?= h($a['applicant_email']) ?></div>
                                    <?php if ($duplicateReasons): ?>
                                        <div class="table-tertiary"><span class="status-pill rejected">Duplicate: <?= h(implode(', ', $duplicateReasons)) ?></span></div>
                                    <?php endif; ?>
                                    <div class="table-tertiary">
                                        <?= cv_link_html($a['cv_file'] ?? null, 'View CV') ?>
                                        <?php if (!empty($a['applicant_phone'])): ?> · <?= h($a['applicant_phone']) ?><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="job-cell">
                            <div class="table-primary"><?= h($a['job_title']) ?></div>
                            <div class="table-secondary"><?= h($a['company']) ?></div>
                            <div class="table-tertiary">
                                <?= h($a['role'] ?? ($a['job_title'] ?? '')) ?>
                                <?php if (!empty($a['recruiter_name'])): ?> · Recruiter: <?= h($a['recruiter_name']) ?><?php endif; ?>
                            </div>
                        </td>
                        <td class="match-cell">
                            <div class="table-fit-wrap"><?= candidate_match_html($a) ?></div>
                        </td>
                        <td class="status-cell">
                            <div class="table-status-wrap">
                                <span class="status-pill <?= h(status_class($a['status'])) ?>"><?= h($a['status']) ?></span>
                                <span class="table-score-note"><?= h(status_note_for($a['status'])) ?></span>
                            </div>
                        </td>
                        <td class="compact-progress">
                            <?= current_progress_html($a['status']) ?>
                        </td>
                        <td class="actions-cell">
                            <div class="table-inline-actions">
                                <a class="btn outline table-open-btn" href="<?= h(application_page_url($a, $page, $redirectTab)) ?>">Open</a>
                                <?= rescreen_application_form($a, $page, $redirectTab) ?>
                                <form class="table-mini-form" method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="application_status">
                                    <input type="hidden" name="redirect_tab" value="<?= h($redirectTab) ?>">
                                    <input type="hidden" name="application_id" value="<?= h((string) $a['id']) ?>">
                                    <select class="select" name="status">
                                        <?php foreach (['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'] as $statusOption): ?>
                                            <option value="<?= h($statusOption) ?>" <?= $a['status'] === $statusOption ? 'selected' : '' ?>><?= h($statusOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn">Update</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function jobs_table(array $jobs, string $page, string $jobSearch, string $tab = 'manage'): void
{
    $clearUrl = app_url($page, ['tab' => $tab]);
    if ($tab === 'manage' && $jobSearch !== '') {
        $clearUrl = app_url($page, ['tab' => $tab]);
    }
    ?>
    <form class="search" method="get" style="margin:0 0 20px">
        <input type="hidden" name="page" value="<?= h($page) ?>">
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <div class="search-inner"><span>🔍</span><input name="job_q" value="<?= h($jobSearch) ?>" placeholder="Search title, company, location, type, status, or tags"></div>
        <button class="btn">Search</button>
        <?php if ($jobSearch !== ''): ?><a class="btn outline" href="<?= h($clearUrl) ?>">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Title</th><th>Company</th><th>Details</th><th>Status</th><th>Deadline</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (!$jobs): ?>
                    <tr><td colspan="6"><strong>No job posts found.</strong></td></tr>
                <?php endif; ?>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td class="job-cell">
                            <div class="table-primary"><?= h($job['title'] ?? '') ?></div>
                            <div class="table-secondary"><?= h($job['location'] ?? '') ?></div>
                            <?php if (!empty($job['tags'])): ?><div class="table-tertiary"><?= h((string) $job['tags']) ?></div><?php endif; ?>
                        </td>
                        <td class="applicant-cell">
                            <div class="table-primary"><?= h($job['company'] ?? '') ?></div>
                            <?php if (!empty($job['recruiter_name'])): ?><div class="table-secondary">Recruiter: <?= h($job['recruiter_name']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <div class="table-primary"><?= h($job['type'] ?? '') ?></div>
                            <div class="table-secondary"><?= h($job['salary'] ?? '') ?></div>
                        </td>
                        <td class="status-cell"><span class="status-pill <?= (($job['status'] ?? '') === 'active') ? 'applied' : 'rejected' ?>"><?= h(ucfirst((string) ($job['status'] ?? 'active'))) ?></span></td>
                        <td><?= !empty($job['expires_at']) ? h(date('M j, Y', strtotime((string) $job['expires_at']))) : '<span class="tiny muted">No deadline</span>' ?></td>
                        <td class="actions-cell">
                            <a class="btn outline table-open-btn" href="<?= h(app_url('jobs', ['job' => $job['id']])) ?>">View</a>
                            <a class="btn outline" href="<?= h(app_url($page, array_filter([
                                'tab' => $tab,
                                'job_q' => $jobSearch !== '' ? $jobSearch : null,
                                'edit_job' => (int) $job['id'],
                            ], static fn($value): bool => $value !== null))) ?>#job-edit-panel">Edit</a>
                            <form class="table-actions" method="post" data-confirm="Delete this job?">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete_job">
                                <input type="hidden" name="job_id" value="<?= h((string) $job['id']) ?>">
                                <button class="btn red">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KDXJobs</title>
    <link rel="icon" type="image/svg+xml" href="<?= h(asset_url('assets/favicon.svg?v=3')) ?>">
    <link rel="shortcut icon" type="image/svg+xml" href="<?= h(asset_url('assets/favicon.svg?v=3')) ?>">
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:linear-gradient(#f0f9ff,#fff 42%,#fff);color:#0f172a}button,input,select,textarea{font:inherit}a{text-decoration:none;color:inherit}.wrap{max-width:1280px;margin:0 auto;padding-left:24px;padding-right:24px}.header{position:sticky;top:0;z-index:50;border-bottom:1px solid #e0f2fe;background:rgba(255,255,255,.86);backdrop-filter:blur(18px)}.nav{display:flex;align-items:center;justify-content:space-between;padding:16px 0;gap:18px}.brand{display:flex;align-items:center;gap:12px;border:0;background:transparent;cursor:pointer}.brand-icon{display:flex;width:44px;height:44px;align-items:center;justify-content:center;border-radius:16px;background:#0ea5e9;color:white;box-shadow:0 10px 22px #bae6fd}.brand-title{font-size:20px;font-weight:900;letter-spacing:-.02em}.brand-sub{font-size:12px;font-weight:700;color:#0284c7}.nav-links{display:flex;align-items:center;gap:4px}.nav-link{border:0;border-radius:16px;padding:9px 15px;background:transparent;color:#475569;font-size:14px;font-weight:800;cursor:pointer}.nav-link.active,.nav-link:hover{background:#f0f9ff;color:#0369a1}.nav-actions{display:flex;align-items:center;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:16px;padding:12px 20px;background:#0ea5e9;color:#fff;font-weight:900;cursor:pointer;transition:.2s}.btn:hover{background:#0284c7}.btn.dark{background:#0f172a}.btn.dark:hover{background:#1e293b}.btn.outline{border:1px solid #bae6fd;background:#fff;color:#0369a1}.btn.outline:hover{background:#f0f9ff}.btn.green{background:#10b981}.btn.red{border:1px solid #fee2e2;background:#fff;color:#dc2626}.card{border:1px solid #e0f2fe;border-radius:24px;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.06)}.hero{position:relative;overflow:hidden;padding:80px 0 56px}.orb{position:absolute;left:50%;top:0;z-index:-1;width:384px;height:384px;transform:translateX(-50%);border-radius:999px;background:rgba(186,230,253,.45);filter:blur(55px)}.hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}.pill{display:inline-flex;align-items:center;gap:8px;margin-bottom:20px;border:1px solid #e0f2fe;border-radius:999px;background:#fff;padding:9px 16px;color:#0369a1;font-size:14px;font-weight:900;box-shadow:0 1px 8px rgba(14,165,233,.08)}h1{margin:0;font-size:clamp(40px,5vw,64px);line-height:1.06;font-weight:950;letter-spacing:-.035em}h2{margin:0;font-size:clamp(30px,4vw,40px);line-height:1.15;font-weight:950;letter-spacing:-.025em}h3{margin:0;font-size:20px;font-weight:950}.lead{margin-top:24px;max-width:580px;color:#475569;font-size:18px;line-height:1.75}.search{margin-top:32px;display:flex;gap:12px;border:1px solid #e0f2fe;border-radius:24px;background:#fff;padding:12px;box-shadow:0 18px 40px rgba(14,165,233,.12)}.search-inner{display:flex;flex:1;align-items:center;gap:12px;padding:0 12px}.search input{width:100%;border:0;outline:0;color:#334155}.hero-actions{margin-top:24px;display:flex;flex-wrap:wrap;gap:12px}.hero-panel{border-radius:32px;background:rgba(255,255,255,.82);padding:12px;box-shadow:0 24px 70px rgba(2,132,199,.18)}.panel-inner{border-radius:24px;background:linear-gradient(135deg,#f0f9ff,#fff);padding:24px}.grid{display:grid;gap:24px}.grid3{grid-template-columns:repeat(3,minmax(0,1fr))}.grid4{grid-template-columns:repeat(4,minmax(0,1fr))}.stat{display:flex;align-items:center;gap:16px;padding:20px}.icon{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:16px;background:#f0f9ff;color:#0284c7;font-size:20px}.icon.small{width:32px;height:32px;font-size:14px}.muted{color:#64748b}.tiny{font-size:14px}.stat-value{font-size:28px;font-weight:950}.applicant{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px;border-radius:16px;background:#f8fafc;padding:12px}.badge{display:inline-flex;border-radius:999px;background:#e0f2fe;padding:5px 12px;color:#0369a1;font-size:12px;font-weight:900}.section{padding:64px 0}.section-title{max-width:760px;margin:0 auto 40px;text-align:center}.eyebrow{margin:0 0 8px;color:#0ea5e9;font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.25em}.section-title p:last-child{color:#475569}.job-card{height:100%;transition:.2s}.job-card:hover{transform:translateY(-4px);box-shadow:0 18px 44px rgba(2,132,199,.14)}.card-pad{padding:24px}.job-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px}.job-meta{margin-top:16px;display:grid;gap:8px;color:#64748b;font-size:14px}.tags{margin-top:20px;display:flex;flex-wrap:wrap;gap:8px}.tag{border-radius:999px;background:#f1f5f9;padding:5px 12px;color:#475569;font-size:12px;font-weight:800}.auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}.form{display:grid;gap:16px}.label{display:grid;gap:8px;color:#334155;font-size:14px;font-weight:900}.input,.select,.textarea{width:100%;border:1px solid #e0f2fe;border-radius:16px;background:#fff;padding:13px 16px;color:#334155;outline:none}.input:focus,.select:focus,.textarea:focus{border-color:#7dd3fc;box-shadow:0 0 0 4px #e0f2fe}.role-tabs{margin-bottom:24px;display:grid;grid-template-columns:1fr 1fr;gap:12px;border-radius:20px;background:#f0f9ff;padding:8px}.role-tabs button{border:0;border-radius:14px;background:transparent;padding:12px;font-weight:950;color:#64748b}.role-tabs button.active{background:#fff;color:#0369a1;box-shadow:0 2px 8px rgba(15,23,42,.08)}.upload{border:1px dashed #bae6fd;border-radius:18px;background:rgba(240,249,255,.7);padding:20px;text-align:center}.dash-hero{margin-bottom:32px;border-radius:32px;background:linear-gradient(90deg,#0ea5e9,#3b82f6);padding:32px;color:white;box-shadow:0 18px 45px rgba(14,165,233,.22)}.dash-hero p{color:#eff6ff}.dash-layout{display:grid;grid-template-columns:1fr 3fr;gap:24px}.side{padding:20px}.side-user{display:flex;gap:12px;align-items:center;margin-bottom:24px}.side-btn{display:flex;width:100%;align-items:center;gap:12px;margin-bottom:8px;border:0;border-radius:16px;background:#fff;padding:12px 16px;color:#475569;text-align:left;font-weight:800}.side-btn.active,.side-btn:hover{background:#f0f9ff;color:#0369a1}.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.profile-box{border-radius:16px;background:rgba(240,249,255,.72);padding:16px}.detail-grid{display:grid;grid-template-columns:1fr 2fr;gap:32px}.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.info{border-radius:16px;background:#f0f9ff;padding:16px}.alert{max-width:1180px;margin:24px auto 0;border-radius:16px;padding:14px 18px;font-weight:800}.ok{border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d}.bad{border:1px solid #fecaca;background:#fef2f2;color:#b91c1c}.footer{margin-top:64px;border-top:1px solid #e0f2fe;background:#fff;padding:40px 0}.footer-row{display:flex;justify-content:space-between;gap:16px;align-items:center}.mobile-menu{display:none}.hidden{display:none}@media(max-width:1024px){.nav-links,.nav-actions{display:none}.mobile-menu{display:block}.hero-grid,.auth-grid,.dash-layout,.detail-grid{grid-template-columns:1fr}.grid3,.grid4,.info-grid,.profile-grid{grid-template-columns:1fr}.search{flex-direction:column}.footer-row{flex-direction:column;align-items:flex-start}}
        .application-card{display:block;border:1px solid #e0f2fe;background:#fff;padding:18px}.application-row{display:grid;grid-template-columns:1fr auto;align-items:start;gap:18px}.application-title{display:grid;gap:6px}.application-title strong{font-size:18px}.application-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px}.application-actions form{display:flex;flex-wrap:wrap;gap:10px}.application-actions .btn{min-width:108px}.application-toolbar{display:grid;grid-template-columns:minmax(0,1fr) 220px auto auto auto;gap:12px;align-items:center;margin-bottom:18px;padding:12px;border:1px solid #e0f2fe;border-radius:20px;background:#f8fbff}.application-toolbar .search-inner{border:1px solid #e0f2fe;border-radius:16px;background:#fff;min-height:50px}.toolbar-meta{justify-self:end;color:#64748b;font-size:14px;font-weight:800}.pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px}.pagination-meta{color:#64748b;font-size:14px;font-weight:800}.btn.is-disabled{pointer-events:none;opacity:.45}.status-pill{display:inline-flex;width:max-content;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:950}.status-pill.new{background:#e0f2fe;color:#0369a1}.status-pill.reviewed{background:#eef2ff;color:#4338ca}.status-pill.shortlisted{background:#fef3c7;color:#92400e}.status-pill.accepted{background:#dcfce7;color:#166534}.status-pill.rejected{background:#fee2e2;color:#991b1b}.progress-track{display:grid;grid-template-columns:repeat(5,minmax(110px,1fr));gap:8px;margin-top:16px;overflow-x:auto;padding-bottom:2px}.progress-step{position:relative;border-radius:999px;background:#e2e8f0;color:#64748b;padding:8px 10px;text-align:center;font-size:12px;font-weight:900;white-space:nowrap}.progress-step i{display:inline-block;width:8px;height:8px;margin-right:6px;border-radius:999px;background:#94a3b8}.progress-step.done,.progress-step.current{background:#dbeafe;color:#1d4ed8}.progress-step.done i,.progress-step.current i{background:#2563eb}.progress-step.current{box-shadow:0 0 0 3px #eff6ff inset}.status-note{margin:10px 0 0;font-size:13px;font-weight:900}.status-note.reviewed{color:#4338ca}.status-note.shortlisted{color:#92400e}.status-note.accepted{color:#166534}.status-note.rejected{color:#991b1b}.application-panels{display:grid;gap:10px;margin-top:16px}.app-panel{border:1px solid #e0f2fe;border-radius:16px;background:#fff;overflow:hidden}.app-panel summary{display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:none;cursor:pointer;padding:14px 16px;font-weight:950;color:#334155}.app-panel summary::-webkit-details-marker{display:none}.app-panel summary:after{content:"+";display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:#f0f9ff;color:#0369a1}.app-panel[open] summary{border-bottom:1px solid #e0f2fe;background:#f8fafc}.app-panel[open] summary:after{content:"-"}.app-panel-body{padding:16px}.interview-form,.application-edit-form{display:grid;grid-template-columns:1fr 1fr;gap:14px}.interview-form .label:nth-of-type(3),.application-edit-form .edit-cover{grid-column:1/-1}.interview-form .btn,.application-edit-form .btn{grid-column:1/-1}.notification-card{padding:18px}.notification-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px}.notification-item{border:1px solid #e0f2fe;border-radius:16px;background:#fff;padding:14px}.notification-item p{line-height:1.45}.dash-layout{grid-template-columns:260px minmax(0,1fr);align-items:start}.dash-layout>main{min-width:0}.side{position:sticky;top:108px;align-self:start;max-height:calc(100vh - 132px);overflow:auto;padding:22px;border-radius:24px}.side-user{display:grid;grid-template-columns:54px minmax(0,1fr);gap:12px;margin-bottom:22px;padding:12px;border-radius:20px;background:linear-gradient(135deg,#f8fbff,#fff)}.side-user strong{display:block;line-height:1.15;word-break:break-word}.side-user .icon{width:54px;height:54px}.side-btn{display:grid;grid-template-columns:22px minmax(0,1fr);align-items:center;min-height:58px;margin-bottom:8px;padding:12px 18px;border:1px solid transparent;line-height:1.18;white-space:normal;word-break:normal;overflow-wrap:break-word}.side-btn.active{border-color:#dbeafe;box-shadow:0 10px 24px rgba(14,165,233,.08)}@media(max-width:1180px){.dash-layout{grid-template-columns:240px minmax(0,1fr)}}@media(max-width:800px){.application-row{grid-template-columns:1fr}.application-actions,.application-actions form{justify-content:stretch;width:100%}.application-actions .btn{width:100%;min-width:0}.application-toolbar{grid-template-columns:1fr}.toolbar-meta{justify-self:start}.pagination-bar{align-items:stretch;flex-direction:column}.pagination-bar .btn{width:100%}.progress-track{grid-template-columns:repeat(5,130px)}.interview-form,.application-edit-form{grid-template-columns:1fr}.side{position:static;max-height:none;overflow:visible}}
        .dash-layout{grid-template-columns:1fr 3fr;align-items:initial}.dash-layout>main{min-width:0}.side{position:static;max-height:none;overflow:visible;padding:20px}.side-user{display:flex;gap:12px;align-items:center;margin-bottom:24px;padding:0;background:transparent}.side-user .icon{width:48px;height:48px}.side-btn{display:flex;align-items:center;min-height:auto;margin-bottom:8px;padding:12px 16px;border:0;line-height:1.25;white-space:normal;overflow-wrap:anywhere}.side-btn.active{box-shadow:none}
        .application-toolbar .search-inner span{display:inline-flex;align-items:center;justify-content:center;width:18px;font-size:0;line-height:1}
        .application-toolbar .search-inner span::before{content:"\1F50D";font-size:16px;line-height:1;color:#64748b}
        .application-toolbar .search-inner input{min-width:0;border:0;outline:none;box-shadow:none;background:transparent}
        .skill-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.skill-option{display:flex;align-items:center;gap:8px;border:1px solid #e0f2fe;border-radius:14px;background:#f8fafc;padding:10px 12px;font-weight:900;color:#334155}.skill-option input{width:auto}.score-bar{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:10px}.score-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#0ea5e9,#22c55e)}.file-link{color:#0369a1;font-weight:950}@media(max-width:800px){.skill-grid,.skills-dropdown-menu{grid-template-columns:1fr}}
        .skills-dropdown{position:relative}.skills-dropdown summary{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:52px;border:1px solid #e0f2fe;border-radius:16px;background:#fff;padding:13px 16px;color:#334155;cursor:pointer;list-style:none}.skills-dropdown summary::-webkit-details-marker{display:none}.skills-dropdown summary:after{content:"";width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:.2s}.skills-dropdown[open] summary{border-color:#7dd3fc;box-shadow:0 0 0 4px #e0f2fe}.skills-dropdown[open] summary:after{transform:rotate(225deg)}.skills-dropdown-menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;border:1px solid #e0f2fe;border-radius:16px;background:#fff;padding:12px;box-shadow:0 16px 36px rgba(15,23,42,.08)}
        .cv-upload{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;border:1px dashed #7dd3fc;border-radius:18px;background:linear-gradient(135deg,#f0f9ff,#fff);padding:16px 18px;transition:.2s}.cv-upload:hover{border-color:#0ea5e9;background:#e0f2fe;box-shadow:0 12px 26px rgba(14,165,233,.12)}.cv-upload-icon{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;border-radius:16px;background:#0ea5e9;color:#fff;box-shadow:0 12px 22px rgba(14,165,233,.2)}.cv-upload-icon svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}.cv-upload-copy{display:grid;gap:7px;min-width:0}.cv-upload-copy strong{font-size:16px;color:#0f172a}.cv-upload-copy span{color:#64748b;font-size:13px;font-weight:800}.cv-upload-input{width:100%;max-width:360px;border:1px solid #bae6fd;border-radius:12px;background:#fff;padding:10px;color:#334155;cursor:pointer}.cv-upload-file{display:inline-flex;width:max-content;max-width:100%;border-radius:999px;background:#e0f2fe;padding:5px 10px;color:#0369a1!important;font-size:12px!important;font-weight:950!important}
        .match-score{display:grid;gap:6px;min-width:150px}.match-score strong{font-size:20px}.match-score.high strong{color:#15803d}.match-score.medium strong{color:#b45309}.match-score.low strong{color:#b91c1c}.match-score .score-bar{margin-top:0}.match-score .cv-quality-line{display:block;font-size:10px;line-height:1.2;font-weight:950;white-space:nowrap}.match-score .cv-quality-line strong{font-size:10px;font-weight:950}.match-score.high .score-bar span{background:linear-gradient(90deg,#22c55e,#16a34a)}.match-score.medium .score-bar span{background:linear-gradient(90deg,#f59e0b,#d97706)}.match-score.low .score-bar span{background:linear-gradient(90deg,#ef4444,#dc2626)}.match-details{margin-top:2px}.match-details summary{width:max-content;cursor:pointer;color:#0369a1;font-size:12px;font-weight:950}.match-details p{max-width:360px;margin:6px 0 0;color:#475569;font-size:12px;line-height:1.55}
        .table-wrap{overflow:auto;border:1px solid #e0f2fe;border-radius:18px;background:#fff}.data-table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;font-size:12px;line-height:1.35}.data-table th,.data-table td{padding:10px 12px;border-bottom:1px solid #eaf6ff;text-align:left;vertical-align:top}.data-table th{position:sticky;top:0;z-index:1;background:#f0f9ff;color:#334155;font-size:11px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}.data-table tbody tr:hover td{background:#f8fbff}.data-table td .tiny{font-size:10px}.data-table tr:last-child td{border-bottom:0}.data-table a{color:#0f172a;text-decoration:none}.data-table a:hover{color:#0369a1}.table-primary{font-size:12px;font-weight:900;color:#0f172a;line-height:1.35}.table-secondary{margin-top:4px;color:#64748b;font-size:10px;font-weight:700;line-height:1.4}.table-tertiary{margin-top:3px;color:#94a3b8;font-size:10px;font-weight:700;line-height:1.35}.applicant-cell,.job-cell{min-width:190px}.match-cell{min-width:190px}.cv-cell{min-width:90px;white-space:nowrap}.status-cell{min-width:100px}.actions-cell{min-width:170px}.table-open-btn{display:inline-flex;margin-bottom:8px}.table-actions{display:flex;flex-direction:column;align-items:flex-start;gap:6px}.table-actions .select{min-width:130px;padding:8px 10px;border-radius:10px;font-size:12px}.table-actions .btn{padding:8px 10px;font-size:12px}.compact-progress{min-width:150px}
        .friendly-app-table .candidate-stack{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:start}.friendly-app-table .candidate-avatar{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:14px;background:linear-gradient(135deg,#0ea5e9,#3b82f6);color:#fff;font-size:14px;font-weight:950;box-shadow:0 12px 24px rgba(14,165,233,.18)}.friendly-app-table .table-fit-wrap .match-score{min-width:135px}.friendly-app-table .table-fit-wrap .match-details p{max-width:240px}.friendly-app-table .table-status-wrap{display:grid;gap:8px}.friendly-app-table .table-score-note{color:#64748b;font-size:11px;font-weight:700;line-height:1.4}.friendly-app-table .table-inline-actions{display:grid;gap:10px}.friendly-app-table .table-mini-form{display:grid;gap:8px}.friendly-app-table .table-mini-form .select{min-width:150px;padding:9px 10px;border-radius:12px;font-size:12px}.friendly-app-table .table-mini-form .btn{padding:9px 12px;font-size:12px}.friendly-app-table tbody tr:hover td{background:linear-gradient(180deg,#f8fbff,#ffffff)}
        .manage-overview{display:grid;gap:18px;margin-bottom:24px}.manage-copy{display:grid;gap:8px;padding:22px}.manage-copy p{margin:0;color:#64748b;line-height:1.7}.manage-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.manage-summary .profile-box{background:linear-gradient(135deg,#f8fbff,#fff)}.manage-summary strong{display:block;font-size:24px;line-height:1;color:#0f172a}.manage-summary span{display:block;margin-top:8px;color:#64748b;font-size:13px;font-weight:800}.manage-edit-stack{display:grid;gap:14px;margin-top:18px}.manage-edit-stack .app-panel summary{background:#f8fbff}
        .jobs-explorer{display:grid;grid-template-columns:330px 1fr;gap:32px;align-items:start}.filter-panel{position:sticky;top:96px;padding:20px;background:#fff}.filter-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px}.filter-head h3{font-size:22px;line-height:1.2;font-weight:900;color:#0f172a}.filter-count{border-radius:999px;background:#e0f2fe;color:#0369a1;padding:5px 10px;font-size:12px;font-weight:900}.filter-block{overflow:hidden;border:1px solid #e0f2fe;border-radius:14px;background:#f8fafc;margin-bottom:10px}.filter-block summary{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:56px;padding:0 18px;color:#334155;font-size:16px;font-weight:800;cursor:pointer;list-style:none}.filter-block summary::-webkit-details-marker{display:none}.filter-block summary::after{content:"";width:8px;height:8px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg);transition:.2s}.filter-block[open] summary::after{transform:rotate(225deg)}.filter-body{padding:10px 18px 18px}.filter-range{display:grid;grid-template-columns:1fr 1fr;gap:10px}.check-list{display:grid;gap:0;max-height:320px;overflow:auto}.check-item{position:relative;display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:46px;padding:0 26px 0 8px;color:#475569;font-size:15px;font-weight:500;cursor:pointer}.check-item input{position:absolute;opacity:0;pointer-events:none}.check-item input:checked + span::after{content:"\2713";position:absolute;right:0;top:50%;transform:translateY(-50%);font-size:18px;font-weight:900;color:#0369a1}.jobs-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.jobs-toolbar .search{margin:0;flex:1;box-shadow:none}.sort-row{display:flex;align-items:center;gap:10px}.jobs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.empty-state{padding:36px;text-align:center}.filter-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.job-detail-layout{display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:28px;align-items:start}.job-detail-main{padding:32px}.job-detail-title{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:24px}.job-rich-text{color:#475569;line-height:1.8}.job-rich-text p{margin:0 0 14px}.job-rich-text ul,.job-rich-text ol{margin:0 0 16px 22px;padding:0}.job-rich-text blockquote{margin:16px 0;border-left:4px solid #bae6fd;padding-left:16px;color:#334155}.job-rich-text h3,.job-rich-text h4{margin:20px 0 10px;color:#0f172a}.editor-wrap{overflow:hidden;border:1px solid #e0f2fe;border-radius:16px;background:#fff}.editor-toolbar{display:flex;flex-wrap:wrap;gap:6px;border-bottom:1px solid #e0f2fe;background:#f8fafc;padding:8px}.editor-toolbar button{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;border:1px solid transparent;border-radius:10px;background:#fff;color:#334155;font-weight:950;cursor:pointer}.editor-toolbar button:hover{border-color:#bae6fd;background:#f0f9ff;color:#0369a1}.rich-editor{min-height:190px;padding:14px 16px;color:#334155;line-height:1.65;outline:none}.rich-editor:focus{box-shadow:0 0 0 4px #e0f2fe inset}.rich-editor:empty:before{content:attr(data-placeholder);color:#94a3b8}.rich-editor ul,.rich-editor ol{margin:8px 0 8px 22px;padding:0}.rich-editor-source{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}.quill-editor-wrap{padding:0}.quill-editor-wrap .ql-toolbar.ql-snow{border:0;border-bottom:1px solid #e0f2fe;background:#f8fafc}.quill-editor-wrap .ql-container.ql-snow{border:0;font-family:inherit}.quill-editor-wrap .ql-editor{min-height:260px;color:#334155;line-height:1.75}.quill-editor-wrap .ql-editor.ql-blank::before{color:#94a3b8;font-style:normal}.quill-editor-wrap .ql-editor h2,.quill-editor-wrap .ql-editor h3{color:#0f172a}.quill-editor-wrap .ql-editor blockquote{border-left:4px solid #bae6fd;color:#334155}.quill-editor-wrap.is-invalid{box-shadow:0 0 0 4px #fee2e2 inset}@media(max-width:1024px){.jobs-explorer,.job-detail-layout{grid-template-columns:1fr}.filter-panel{position:static}.jobs-toolbar{align-items:stretch;flex-direction:column}.jobs-grid{grid-template-columns:1fr}.job-detail-title{flex-direction:column}.job-detail-main{padding:24px}}
        .ck-editor-host{display:grid;gap:0}
        .ck-editor__editable_inline{min-height:190px;color:#334155}
        .ck.ck-editor__main>.ck-editor__editable:not(.ck-focused){border-color:#e0f2fe}
        .ck.ck-editor__editable.ck-focused:not(.ck-editor__nested-editable){border-color:#7dd3fc;box-shadow:0 0 0 4px #e0f2fe}
        .ck.ck-toolbar{border-color:#e0f2fe;background:#f8fafc}
        .ck.ck-toolbar .ck-button.ck-on{background:#e0f2fe;color:#0369a1}
        .brand{gap:14px}.brand-icon{width:58px;height:58px;border:1px solid #dbeafe;background:#fff;box-shadow:0 12px 28px rgba(8,23,255,.10);font-size:0;overflow:hidden}.brand-icon img{display:block;width:48px;height:48px;object-fit:contain}.brand-logo{display:block;width:48px;height:48px}
        .timeline-list{display:grid;gap:10px;border-left:3px solid #bae6fd;padding-left:14px}.timeline-item{position:relative;display:grid;gap:4px;border-radius:14px;background:#f8fafc;padding:12px}.timeline-item:before{content:"";position:absolute;left:-22px;top:18px;width:12px;height:12px;border-radius:999px;background:#0ea5e9;box-shadow:0 0 0 4px #e0f2fe}.timeline-item.interview{background:#f0fdf4}.timeline-item.interview:before{background:#22c55e}.analytics-bar{height:10px;border-radius:999px;background:#e0f2fe;overflow:hidden;margin-top:8px}.analytics-bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#0ea5e9,#2563eb)}
        .service-chat{margin-top:16px;border:1px solid #e0f2fe;border-radius:18px;background:#fff;padding:14px}.service-chat-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.service-chat-body{display:grid;gap:10px;max-height:260px;overflow:auto;padding-right:4px}.chat-bubble{max-width:82%;border-radius:16px;padding:12px}.chat-bubble p{margin:6px 0;line-height:1.55}.chat-bubble.candidate{justify-self:start;background:#f0f9ff}.chat-bubble.support{justify-self:end;background:#ecfdf5}.service-chat-form{display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:12px;align-items:end}
        .theme-toggle{width:44px;height:44px;padding:0;border-radius:999px;font-size:18px;line-height:1}
        .nav-menu-toggle{display:none;width:44px;height:44px;padding:0;border-radius:999px;font-size:22px;line-height:1}.nav-mobile-actions{display:none}
        .theme-mobile{display:none}
        .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}
        .hero-pipeline-row{margin-top:34px}.recruit-animation{position:relative;overflow:hidden;border:1px solid #dbeafe;border-radius:22px;background:linear-gradient(135deg,#fff,#eff6ff);padding:24px}.recruit-animation-head{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:22px}.recruit-animation-head strong{font-size:18px}.recruit-animation-head span{border-radius:999px;background:#dcfce7;color:#166534;padding:6px 10px;font-size:12px;font-weight:950}.pipeline-track{position:relative;height:150px}.pipeline-line{position:absolute;left:34px;right:34px;top:58px;height:4px;border-radius:999px;background:#bae6fd}.pipeline-stage{position:absolute;top:20px;display:grid;gap:8px;justify-items:center;z-index:2}.pipeline-stage:nth-child(2){left:0}.pipeline-stage:nth-child(3){left:25%;transform:translateX(-50%)}.pipeline-stage:nth-child(4){left:50%;transform:translateX(-50%)}.pipeline-stage:nth-child(5){left:75%;transform:translateX(-50%)}.pipeline-stage:nth-child(6){right:0}.pipeline-node{display:flex;align-items:center;justify-content:center;width:72px;height:72px;border:3px solid #e0f2fe;border-radius:22px;background:#fff;color:#0369a1;font-size:28px;box-shadow:0 14px 30px rgba(14,165,233,.13)}.pipeline-stage small{color:#475569;font-weight:900}.candidate-dot{position:absolute;top:48px;left:32px;z-index:3;width:24px;height:24px;border:4px solid #fff;border-radius:999px;background:#0ea5e9;box-shadow:0 10px 22px rgba(14,165,233,.35);animation:candidateMove 5s ease-in-out infinite}.candidate-dot.two{animation-delay:1.55s;background:#22c55e}.candidate-dot.three{animation-delay:3.1s;background:#f59e0b}.offer-card{position:absolute;right:22px;bottom:0;display:flex;align-items:center;gap:10px;border:1px solid #bbf7d0;border-radius:16px;background:#f0fdf4;padding:10px 12px;color:#166534;font-size:13px;font-weight:950;animation:offerPulse 2.5s ease-in-out infinite}.offer-card i{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:#22c55e;color:#fff;font-style:normal}@keyframes candidateMove{0%{left:32px;transform:scale(.8);opacity:0}12%{opacity:1}26%{left:25%;transform:scale(1)}46%{left:50%;transform:scale(1)}66%{left:75%;transform:scale(1)}86%{left:calc(100% - 56px);transform:scale(.92);opacity:1}100%{left:calc(100% - 56px);transform:scale(.7);opacity:0}}@keyframes offerPulse{0%,100%{transform:translateY(0);box-shadow:none}50%{transform:translateY(-4px);box-shadow:0 12px 22px rgba(34,197,94,.18)}}
        .career-potential{padding-top:34px}.idea-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.idea-card{min-height:420px;border-radius:32px;padding:34px;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden}.idea-card h3{font-size:24px}.idea-card p{margin:14px 0 0;color:#1f2937;line-height:1.7}.idea-card.yellow{background:#fef0a8}.idea-card.pink{background:#f8ced2}.idea-card.violet{background:#a9a1ff}.idea-art{height:220px;display:flex;align-items:center;justify-content:center}.idea-art svg{width:190px;height:190px;stroke:#020617;stroke-width:8;fill:none;stroke-linecap:round;stroke-linejoin:round}.idea-art .fill{fill:#020617;stroke:none}.journey-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.journey-card{min-height:184px;border-radius:8px;background:#f3f4f6;padding:28px}.journey-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:999px;background:#0ea5e9;color:#fff;font-weight:950;margin-bottom:26px}.journey-card h3{font-size:16px}.journey-card p{font-size:13px;line-height:1.7;color:#374151}.innovation-band{border-top:1px solid #e0f2fe;border-bottom:1px solid #e0f2fe;background:#f8fafc}.innovation-grid{display:grid;grid-template-columns:1.1fr repeat(3,1fr);gap:18px;align-items:stretch}.innovation-lead{padding:8px 0}.innovation-lead h2{font-size:34px}.innovation-card{border:1px solid #e0f2fe;border-radius:18px;background:#fff;padding:22px}.innovation-card .tiny{display:inline-flex;margin-bottom:14px;border-radius:999px;background:#e0f2fe;padding:5px 10px;color:#0369a1;font-weight:950}.footer-brand{font-size:clamp(56px,9vw,118px);line-height:.9;font-weight:950;letter-spacing:-.07em;color:#0ea5e9}.footer-top{display:grid;grid-template-columns:1.2fr repeat(3,1fr);gap:38px;align-items:start}.footer-links{display:grid;gap:10px;color:#64748b;font-size:14px}.footer-bottom{margin-top:44px;border:1px solid #cbd5e1;border-radius:12px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:18px}.social-row{display:flex;align-items:center;gap:10px}.social-dot{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:#082f49;color:#fff;font-size:12px;font-weight:950}.language-pill{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#082f49;color:#fff;padding:7px 12px;font-size:12px;font-weight:950}
        .career-potential{padding-top:18px}.idea-grid{grid-template-columns:1.15fr .85fr .85fr;align-items:stretch}.idea-card{min-height:360px;border:1px solid #dbeafe;border-radius:22px;background:#fff!important;box-shadow:0 18px 42px rgba(14,165,233,.08)}.idea-card:first-child{background:linear-gradient(135deg,#e0f2fe,#fff)!important}.idea-card:nth-child(2){background:linear-gradient(135deg,#f0fdf4,#fff)!important}.idea-card:nth-child(3){background:linear-gradient(135deg,#fff7ed,#fff)!important}.idea-art{height:150px;justify-content:flex-start}.idea-art svg{width:126px;height:126px;stroke:#0284c7;stroke-width:7}.journey-grid{grid-template-columns:repeat(4,minmax(0,1fr));counter-reset:journey}.journey-card{position:relative;border:1px solid #e0f2fe;border-radius:18px;background:#fff;padding:26px;box-shadow:0 1px 3px rgba(15,23,42,.05)}.journey-icon{width:34px;height:34px;margin-bottom:18px;background:#0f172a}.journey-card:after{content:"";position:absolute;left:26px;right:26px;bottom:0;height:4px;border-radius:999px 999px 0 0;background:#0ea5e9}.about-story{display:grid;grid-template-columns:1.1fr .9fr;gap:30px;align-items:start}.about-panel{border:1px solid #dbeafe;border-radius:28px;background:linear-gradient(135deg,#ffffff,#f4faff);padding:32px;box-shadow:0 20px 44px rgba(14,165,233,.10)}.about-panel p{color:#475569;line-height:1.85}.about-chip-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}.about-chip{display:inline-flex;align-items:center;border:1px solid #dbeafe;border-radius:999px;background:#fff;padding:8px 14px;color:#0369a1;font-size:13px;font-weight:900}.about-metric-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.about-metric{border:1px solid #dbeafe;border-radius:22px;background:#fff;padding:20px;box-shadow:0 10px 24px rgba(15,23,42,.05)}.about-metric strong{display:block;font-size:28px;line-height:1;color:#0f172a}.about-metric span{display:block;margin-top:8px;color:#64748b;font-weight:800}.about-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:24px}.about-value{border:1px solid #e0f2fe;border-radius:20px;background:#fff;padding:22px;box-shadow:0 1px 3px rgba(15,23,42,.05)}.about-value h3{margin-bottom:10px}.about-value p{margin:0;color:#475569;line-height:1.75}.tracking-story{overflow:hidden;border:1px solid #dbeafe;border-radius:28px;background:linear-gradient(135deg,#f8fdff,#eef7ff 55%,#fff);padding:32px;box-shadow:0 24px 54px rgba(14,165,233,.10)}.tracking-story-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:28px;align-items:center}.tracking-story-copy p{color:#475569;line-height:1.8}.tracking-badges{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 0}.tracking-badge{display:inline-flex;align-items:center;gap:8px;border:1px solid #dbeafe;border-radius:999px;background:#fff;padding:9px 14px;color:#0369a1;font-size:13px;font-weight:900}.tracking-visual{position:relative;min-height:360px;border-radius:24px;background:linear-gradient(180deg,#ffffff,#eff6ff);padding:24px;border:1px solid #dbeafe;overflow:hidden}.tracking-glow{position:absolute;right:-20px;top:-20px;width:180px;height:180px;border-radius:999px;background:rgba(14,165,233,.14);filter:blur(18px)}.tracking-line{position:absolute;left:42px;top:50px;bottom:44px;width:4px;border-radius:999px;background:linear-gradient(180deg,#7dd3fc,#bfdbfe)}.tracking-step{position:relative;z-index:1;display:grid;grid-template-columns:64px 1fr;gap:14px;align-items:center;margin-bottom:18px}.tracking-node{display:flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:20px;background:#fff;border:1px solid #dbeafe;box-shadow:0 14px 28px rgba(14,165,233,.10);font-size:24px}.tracking-card{border:1px solid #dbeafe;border-radius:18px;background:rgba(255,255,255,.95);padding:14px 16px;box-shadow:0 10px 24px rgba(15,23,42,.05)}.tracking-card strong{display:block;font-size:15px}.tracking-card span{display:block;margin-top:4px;color:#64748b;font-size:13px;line-height:1.55}.tracking-card.support{background:linear-gradient(135deg,#f0fdf4,#ffffff)}.tracking-card.care{background:linear-gradient(135deg,#fff7ed,#ffffff)}.tracking-pulse{position:absolute;left:34px;top:44px;width:18px;height:18px;border-radius:999px;background:#0ea5e9;box-shadow:0 0 0 0 rgba(14,165,233,.45);animation:trackingPulse 2.6s ease-in-out infinite}.tracking-ribbon{display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;border-radius:999px;background:#0f172a;color:#fff;padding:8px 14px;font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.innovation-band{background:linear-gradient(135deg,#f8fafc,#eff6ff)}.innovation-card{border-radius:8px}.footer{padding:48px 0 28px}.footer-brand{font-size:clamp(38px,7vw,82px);letter-spacing:-.05em}.footer-top{grid-template-columns:1.4fr repeat(3,minmax(150px,1fr));border-top:1px solid #e0f2fe;padding-top:28px}.footer-bottom{border-radius:18px;background:#f8fafc}.social-dot,.language-pill{background:#0ea5e9}@keyframes trackingPulse{0%,100%{transform:scale(.92);box-shadow:0 0 0 0 rgba(14,165,233,.42)}50%{transform:scale(1.06);box-shadow:0 0 0 14px rgba(14,165,233,0)}}
        body.theme-dark{background:linear-gradient(#08111f,#0f172a 42%,#111827);color:#e5e7eb}
        body.theme-dark .header{border-color:#1e3a5f;background:rgba(15,23,42,.88)}
        body.theme-dark .brand-sub,body.theme-dark .nav-link.active,body.theme-dark .nav-link:hover,body.theme-dark .file-link{color:#7dd3fc}
        body.theme-dark .nav-link,body.theme-dark .muted,body.theme-dark .job-meta,body.theme-dark .job-rich-text{color:#cbd5e1}
        body.theme-dark .card,body.theme-dark .search,body.theme-dark .hero-panel,body.theme-dark .footer,body.theme-dark .input,body.theme-dark .select,body.theme-dark .textarea,body.theme-dark .data-table,body.theme-dark .editor-wrap{border-color:#1e3a5f;background:#111827;color:#e5e7eb}
        body.theme-dark .btn.outline,body.theme-dark .side-btn,body.theme-dark .pill,body.theme-dark .editor-toolbar button{border-color:#1e3a5f;background:#0f172a;color:#bae6fd}
        body.theme-dark .btn.outline:hover,body.theme-dark .nav-link.active,body.theme-dark .nav-link:hover,body.theme-dark .side-btn.active,body.theme-dark .side-btn:hover{background:#10233d}
        body.theme-dark .panel-inner,body.theme-dark .profile-box,body.theme-dark .info,body.theme-dark .filter-block,body.theme-dark .filter-panel,body.theme-dark .applicant,body.theme-dark .tag,body.theme-dark .role-tabs,body.theme-dark .upload,body.theme-dark .editor-toolbar,body.theme-dark .table-wrap{border-color:#1e3a5f;background:#0f172a;color:#e5e7eb}
        body.theme-dark .skills-dropdown summary,body.theme-dark .skills-dropdown-menu,body.theme-dark .skill-option{border-color:#1e3a5f;background:#111827;color:#e5e7eb}
        body.theme-dark .cv-upload{border-color:#1e3a5f;background:linear-gradient(135deg,#0f172a,#111827)}body.theme-dark .cv-upload:hover{border-color:#38bdf8;background:#10233d}body.theme-dark .cv-upload-copy strong{color:#e5e7eb}body.theme-dark .cv-upload-copy span{color:#cbd5e1}
        body.theme-dark .lead,body.theme-dark .section-title p:last-child,body.theme-dark .label,body.theme-dark .filter-head h3,body.theme-dark .filter-block summary,body.theme-dark .job-rich-text h3,body.theme-dark .job-rich-text h4,body.theme-dark .data-table th,body.theme-dark .data-table td,body.theme-dark .search input{color:#e5e7eb}
        body.theme-dark .search input,body.theme-dark .role-tabs button{background:transparent;color:#e5e7eb}
        body.theme-dark .role-tabs button.active,body.theme-dark .data-table th{background:#10233d;color:#bae6fd}
        body.theme-dark .data-table td{border-color:#1e293b}
        body.theme-dark .brand-icon{border-color:#1e3a5f;background:#fff;color:#0817ff;box-shadow:0 10px 22px rgba(14,165,233,.22)}
        body.theme-dark .ck.ck-toolbar,body.theme-dark .ck.ck-editor__main>.ck-editor__editable{border-color:#1e3a5f;background:#111827;color:#e5e7eb}
        body.theme-dark .ck.ck-button,body.theme-dark .ck.ck-button:not(.ck-disabled):hover{color:#e5e7eb;background:#0f172a}
        body.theme-dark .journey-card,body.theme-dark .innovation-band,body.theme-dark .innovation-card{border-color:#1e3a5f;background:#111827;color:#e5e7eb}
        body.theme-dark .recruit-animation,body.theme-dark .pipeline-node{border-color:#1e3a5f;background:#111827;color:#bae6fd}body.theme-dark .pipeline-line{background:#1e3a5f}body.theme-dark .pipeline-stage small{color:#cbd5e1}body.theme-dark .offer-card{border-color:#14532d;background:#052e16;color:#bbf7d0}
        body.theme-dark .idea-card p,body.theme-dark .journey-card p,body.theme-dark .footer-links{color:#cbd5e1}
        body.theme-dark .idea-card.yellow{background:#3d3414}body.theme-dark .idea-card.pink{background:#3f2228}body.theme-dark .idea-card.violet{background:#312e63}
        body.theme-dark .idea-art svg{stroke:#f8fafc}body.theme-dark .idea-art .fill{fill:#f8fafc}
        body.theme-dark .footer-bottom{border-color:#1e3a5f}
        @media(max-width:1024px){.theme-mobile{display:inline-flex}.nav-actions .theme-toggle{display:none}}
        @media(max-width:1024px){.idea-grid,.journey-grid,.innovation-grid,.footer-top{grid-template-columns:1fr 1fr}.idea-card{min-height:360px}.footer-brand{font-size:64px}}
        @media(max-width:720px){.idea-grid,.journey-grid,.innovation-grid,.footer-top{grid-template-columns:1fr}.footer-bottom{align-items:flex-start;flex-direction:column}.idea-art{height:170px}.idea-art svg{width:150px;height:150px}.recruit-animation{padding:18px}.pipeline-track{height:170px}.pipeline-node{width:54px;height:54px;border-radius:18px;font-size:22px}.pipeline-stage small{font-size:11px}.pipeline-line{left:24px;right:24px;top:48px}.candidate-dot{top:39px}.offer-card{left:18px;right:auto;bottom:4px}}
        @media(max-width:1180px){.wrap{padding-left:20px;padding-right:20px}.hero-grid{gap:30px}.jobs-explorer{grid-template-columns:280px 1fr;gap:22px}.grid3,.jobs-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.grid4{grid-template-columns:repeat(2,minmax(0,1fr))}.journey-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:1024px){.header{position:relative}.nav{display:grid;grid-template-columns:1fr auto;align-items:center}.brand{min-width:0}.nav-links{grid-column:1/-1;order:3;display:flex;width:100%;overflow-x:auto;padding:8px 0 2px;scrollbar-width:thin}.nav-link{flex:0 0 auto}.nav-actions{display:flex;justify-content:flex-end;margin-left:auto}.nav-actions>a,.nav-actions>form,.nav-actions>.tiny{display:none}.theme-mobile,.mobile-menu{display:none}.hero{padding:48px 0}.hero-grid{grid-template-columns:1fr}.hero-panel{max-width:720px}.auth-grid,.dash-layout,.detail-grid,.jobs-explorer,.job-detail-layout{grid-template-columns:1fr}.filter-panel{position:static}.profile-grid,.info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.section{padding:48px 0}.footer-top{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:760px){.wrap{padding-left:16px;padding-right:16px}.nav{position:relative;display:grid;grid-template-columns:1fr auto auto;gap:10px}.brand{gap:10px}.brand-icon{width:50px;height:50px;border-radius:16px}.brand-icon img{width:42px;height:42px}.brand-title{font-size:18px}.brand-sub{font-size:11px}.nav-menu-toggle{display:inline-flex}.nav-links{position:absolute;top:calc(100% + 10px);right:16px;left:16px;z-index:80;display:none;width:auto;overflow:visible;border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:10px;box-shadow:0 22px 50px rgba(15,23,42,.16)}.nav.nav-open .nav-links{display:grid;grid-template-columns:1fr;gap:6px}.nav-mobile-actions{display:grid;gap:6px;border-top:1px solid #e0f2fe;margin-top:6px;padding-top:8px}.nav-mobile-actions form{margin:0}.nav-action-button{width:100%;border:0;background:transparent;color:#475569;font:inherit;font-weight:800;cursor:pointer}.nav-link{display:flex;align-items:center;justify-content:flex-start;min-height:44px;padding:10px 12px;text-align:left;white-space:normal}.nav-link::before{content:"›";display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;margin-right:10px;border-radius:999px;background:#f0f9ff;color:#0369a1;font-weight:950}.nav-actions{align-self:start}.theme-toggle{width:42px;height:42px}body.theme-dark .nav-links{border-color:#1e3a5f;background:#111827}body.theme-dark .nav-mobile-actions{border-color:#1e3a5f}body.theme-dark .nav-action-button{color:#cbd5e1}body.theme-dark .nav-link::before{background:#10233d;color:#bae6fd}h1{font-size:42px;letter-spacing:0}h2{font-size:30px;letter-spacing:0}.lead{font-size:16px;line-height:1.65}.search,.hero-actions,.sort-row,.jobs-toolbar{align-items:stretch;flex-direction:column}.hero-actions .btn,.search .btn,.sort-row .select{width:100%}.hero-panel{border-radius:22px}.panel-inner,.card-pad,.job-detail-main,.side{padding:18px}.grid3,.grid4,.jobs-grid,.journey-grid,.idea-grid,.innovation-grid,.footer-top,.profile-grid,.info-grid,.skill-grid{grid-template-columns:1fr}.idea-card{min-height:auto}.applicant,.application-row,.footer-bottom{align-items:flex-start;flex-direction:column}.table-actions,.application-row form{width:100%}.table-actions .select,.table-actions .btn,.application-row .btn{width:100%;min-width:0}.pipeline-track{min-width:0;height:138px}.pipeline-line{left:24px;right:24px;top:50px}.pipeline-node{width:56px;height:56px;border-radius:18px;font-size:22px}.pipeline-stage{top:18px}.pipeline-stage small{max-width:72px;font-size:11px;line-height:1.2;text-align:center;white-space:normal}.candidate-dot{top:41px;left:20px}.offer-card{right:18px;bottom:0;font-size:12px}.footer-brand{font-size:48px}.footer-bottom{gap:14px}.social-row{flex-wrap:wrap}.progress-track{grid-template-columns:1fr 1fr}.data-table{min-width:720px}}
        @media(max-width:520px){.recruit-animation{padding:16px}.recruit-animation-head{align-items:flex-start;flex-direction:column;margin-bottom:16px}.pipeline-track{height:auto;min-height:390px}.pipeline-line{left:27px;right:auto;top:36px;bottom:54px;width:4px;height:auto}.pipeline-stage{left:0!important;right:auto!important;transform:none!important;display:grid;grid-template-columns:56px 1fr;align-items:center;justify-items:start;width:100%;gap:12px}.pipeline-stage:nth-child(2){top:0}.pipeline-stage:nth-child(3){top:72px}.pipeline-stage:nth-child(4){top:144px}.pipeline-stage:nth-child(5){top:216px}.pipeline-stage:nth-child(6){top:288px}.pipeline-stage small{max-width:none;font-size:13px;text-align:left}.candidate-dot{left:17px;top:16px;animation:candidateMoveMobile 5s ease-in-out infinite}.candidate-dot.two{animation-delay:1.55s}.candidate-dot.three{animation-delay:3.1s}.offer-card{left:68px;right:auto;bottom:0}.pipeline-node{width:56px;height:56px}@keyframes candidateMoveMobile{0%{top:16px;transform:scale(.8);opacity:0}12%{opacity:1}26%{top:88px;transform:scale(1)}46%{top:160px;transform:scale(1)}66%{top:232px;transform:scale(1)}86%{top:304px;transform:scale(.92);opacity:1}100%{top:304px;transform:scale(.7);opacity:0}}}
        @media(max-width:420px){.wrap{padding-left:12px;padding-right:12px}.nav{gap:10px}.nav-link{padding:8px 10px;font-size:13px}.theme-toggle{width:40px;height:40px}.pill{max-width:100%;white-space:normal;border-radius:16px}h1{font-size:36px}.hero{padding-top:34px}.section{padding:38px 0}.btn{width:100%;padding:11px 14px}.nav-actions .theme-toggle{width:40px}.search-inner{padding:0 6px}.footer-brand{font-size:40px}.role-tabs{grid-template-columns:1fr}.progress-track{grid-template-columns:1fr}}
    </style>
</head>
<body>
<header class="header">
    <div class="wrap nav">
        <a class="brand" href="<?= h(app_url('home')) ?>">
            <span class="brand-icon"><img src="<?= h(asset_url('assets/kdx-logo.svg')) ?>" alt=""></span>
            <span><span class="brand-title">KDXJobs</span><br><span class="brand-sub">Tech Hiring Platform</span></span>
        </a>
        <button class="btn outline nav-menu-toggle" type="button" data-nav-toggle aria-label="Open navigation menu" aria-expanded="false">&#9776;</button>
        <nav class="nav-links">
            <?php
            $navItems = [['home','Home'],['jobs','Jobs'],['companies','Companies'],['blog','Blog']];
            if (!$user) {
                $navItems[] = ['login', 'Login'];
                $navItems[] = ['register', 'Register'];
            } elseif (is_admin_role($user['role'])) {
                $navItems[] = ['admin', 'Admin Panel'];
            } elseif ($user['role'] === 'company') {
                $navItems[] = ['company', 'Company Dashboard'];
            } else {
                $navItems[] = ['user', 'Job Seeker Dashboard'];
            }
            foreach ($navItems as [$id,$label]):
            ?>
                <a class="nav-link <?= $page === $id ? 'active' : '' ?>" href="<?= h(app_url($id)) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
            <div class="nav-mobile-actions">
                <?php if ($user): ?>
                    <span class="tiny muted"><?= h($user['full_name'] ?: ($user['company_name'] ?: $user['email'])) ?></span>
                    <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button class="nav-link nav-action-button" type="submit">Logout</button></form>
                <?php else: ?>
                    <a class="nav-link" href="<?= h(app_url('login')) ?>">Login</a>
                    <a class="nav-link" href="<?= h(app_url('register')) ?>">Register</a>
                <?php endif; ?>
            </div>
        </nav>
        <div class="nav-actions">
            <button class="btn outline theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme"><span data-theme-icon aria-hidden="true">&#9790;</span><span class="sr-only" data-theme-label>Dark mode</span></button>
            <?php if ($user): ?>
                <span class="tiny muted"><?= h($user['full_name'] ?: ($user['company_name'] ?: $user['email'])) ?></span>
                <form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="logout"><button class="btn outline">Logout</button></form>
            <?php else: ?>
                <a class="btn outline" href="<?= h(app_url('login')) ?>">Login</a>
                <a class="btn" href="<?= h(app_url('register')) ?>">Register</a>
            <?php endif; ?>
        </div>
        <button class="btn outline theme-toggle theme-mobile" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch theme"><span data-theme-icon aria-hidden="true">&#9790;</span><span class="sr-only" data-theme-label>Dark mode</span></button>
        <a class="btn outline mobile-menu" href="<?= h(app_url('jobs')) ?>">Menu</a>
    </div>
</header>

<?php if ($message): ?><div class="alert ok"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert bad"><?= h($error) ?></div><?php endif; ?>

<?php if ($page === 'home'): ?>
<section class="hero">
    <div class="orb"></div>
    <div class="wrap hero-grid">
        <div>
            <span class="pill">🛡️ Smart Recruitment Platform</span>
            <h1>Find the right job. Hire the right talent.</h1>
            <p class="lead">A clean recruitment website for job seekers, companies, and admins, built with modern profiles, dashboards, applications, and job management.</p>
            <form class="search" method="get">
	            <input type="hidden" name="page" value="jobs">
                <div class="search-inner"><span>🔍</span><input name="q" value="<?= h($search) ?>" placeholder="Search job title, company, or skill"></div>
                <button class="btn">Find Jobs</button>
            </form>
            <div class="hero-actions">
                <a class="btn dark" href="<?= h(app_url('jobs')) ?>">Find Jobs</a>
                <a class="btn outline" href="<?= h(app_url('company')) ?>">Post a Job</a>
            </div>
        </div>
        <div class="card hero-panel">
            <div class="panel-inner">
                <div class="grid">
                    <div class="card stat"><span class="icon">💼</span><div><div class="tiny muted">Open Jobs</div><div class="stat-value"><?= h($stats['openJobs']) ?></div></div></div>
                    <div class="card stat"><span class="icon">🏢</span><div><div class="tiny muted">Companies</div><div class="stat-value"><?= h($stats['companies']) ?></div></div></div>
                    <div class="card stat"><span class="icon">👥</span><div><div class="tiny muted">Job Seekers</div><div class="stat-value"><?= h($stats['jobSeekers']) ?></div></div></div>
                </div>
                <div class="card card-pad" style="margin-top:24px">
                    <h3 style="margin-bottom:16px">Latest Applicants</h3>
                    <?php foreach (array_slice($applicants, 0, 3) as $a): ?>
                        <div class="applicant">
                            <div><strong><?= h($a['applicant_name']) ?></strong><br><span class="tiny muted"><?= h($a['role']) ?></span></div>
                            <span class="badge"><?= h($a['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="wrap hero-pipeline-row">
        <div class="recruit-animation" aria-label="Animated recruitment pipeline">
            <div class="recruit-animation-head">
                <strong>Hiring pipeline live</strong>
                <span>Offer ready</span>
            </div>
            <div class="pipeline-track" aria-hidden="true">
                <div class="pipeline-line"></div>
                <div class="pipeline-stage"><div class="pipeline-node">CV</div><small>Profile</small></div>
                <div class="pipeline-stage"><div class="pipeline-node">✓</div><small>Review</small></div>
                <div class="pipeline-stage"><div class="pipeline-node">★</div><small>Shortlist</small></div>
                <div class="pipeline-stage"><div class="pipeline-node">Q</div><small>Interview</small></div>
                <div class="pipeline-stage"><div class="pipeline-node">↗</div><small>Offer</small></div>
                <span class="candidate-dot"></span>
                <span class="candidate-dot two"></span>
                <span class="candidate-dot three"></span>
                <div class="offer-card"><i>✓</i> Candidate matched</div>
            </div>
        </div>
    </div>
</section>
<section class="section career-potential">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow">Why KDXJOBS</p><h2>A career platform built for local momentum</h2><p>Not just listings. KDXJOBS helps people understand where they fit, what to improve, and how to move forward.</p></div>
        <div class="idea-grid">
            <article class="idea-card yellow">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <path d="M58 110l36-58 48 96"></path><path d="M85 88h36"></path><path d="M56 126c20 24 70 24 90 0"></path><path d="M42 56l-16-10"></path><path d="M158 56l16-10"></path><path d="M40 88H20"></path><path d="M160 88h20"></path><circle cx="58" cy="150" r="8"></circle><circle cx="142" cy="150" r="8"></circle>
                    </svg>
                </div>
                <div><h3>Launch-ready profiles</h3><p>Help candidates turn skills, CVs, and project experience into profiles recruiters can read quickly.</p></div>
            </article>
            <article class="idea-card pink">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <circle cx="72" cy="64" r="42"></circle><path d="M58 62c12 14 28 14 42 0"></path><path d="M52 92c20 20 44 20 64 0"></path><path d="M126 44l34-24 16 18-34 24"></path><path d="M122 80l42 44"></path><circle cx="134" cy="136" r="28"></circle><path d="M124 136c8 8 18 8 26 0"></path>
                    </svg>
                </div>
                <div><h3>Better matches</h3><p>Surface roles by skills, salary, location, and work style so applicants waste less time guessing.</p></div>
            </article>
            <article class="idea-card violet">
                <div class="idea-art" aria-hidden="true">
                    <svg viewBox="0 0 200 200">
                        <path d="M36 118l82-82 34 34-82 82z"></path><circle cx="130" cy="58" r="36"></circle><path d="M120 58c8 10 22 10 30 0"></path><path d="M34 152h112"></path><path d="M146 152l-22 18"></path><path d="M146 152l-22-18"></path><path d="M166 58h18"></path>
                    </svg>
                </div>
                <div><h3>Status clarity</h3><p>Keep applications visible from submitted to reviewed, shortlisted, accepted, or rejected.</p></div>
            </article>
        </div>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow">How It Works</p><h2>From first search to final update</h2><p>A calmer hiring flow for job seekers, companies, and recruiter admins.</p></div>
        <div class="journey-grid">
            <article class="journey-card"><span class="journey-icon">01</span><h3>Map your direction</h3><p>Choose the roles, cities, industries, and work styles that match where you want to grow next.</p></article>
            <article class="journey-card"><span class="journey-icon">02</span><h3>Show real strengths</h3><p>Highlight practical skills, languages, projects, and CV details recruiters can understand quickly.</p></article>
            <article class="journey-card"><span class="journey-icon">03</span><h3>Move faster on good roles</h3><p>Save time with ready profile details, clean job filters, and direct applications from one place.</p></article>
            <article class="journey-card"><span class="journey-icon">04</span><h3>Stay informed after applying</h3><p>Follow each application status and know when a recruiter has reviewed or shortlisted you.</p></article>
        </div>
    </div>
</section>
<section class="section" id="application-tracking">
    <div class="wrap">
        <div class="tracking-story">
            <div class="tracking-story-grid">
                <div class="tracking-story-copy">
                    <span class="tracking-ribbon">Application Tracking</span>
                    <h2>We do more than collect applications. We stay with people through every detail.</h2>
                    <p>Most recruiting services stop after the application is sent. KDXJobs is built to keep candidates informed, supported, and moving with confidence. We show real progress, highlight what happens next, and make every update feel human instead of silent.</p>
                    <div class="tracking-badges">
                        <span class="tracking-badge">Live status updates</span>
                        <span class="tracking-badge">Interview reminders</span>
                        <span class="tracking-badge">Human support at every stage</span>
                    </div>
                </div>
                <div class="tracking-visual" aria-label="Animated application tracking story">
                    <div class="tracking-glow"></div>
                    <div class="tracking-line"></div>
                    <div class="tracking-pulse"></div>
                    <div class="tracking-step">
                        <div class="tracking-node">✉</div>
                        <div class="tracking-card">
                            <strong>Applied with clarity</strong>
                            <span>Your application is received and instantly visible inside your journey.</span>
                        </div>
                    </div>
                    <div class="tracking-step">
                        <div class="tracking-node">👀</div>
                        <div class="tracking-card">
                            <strong>Reviewed with transparency</strong>
                            <span>You can see when your application is reviewed instead of wondering in silence.</span>
                        </div>
                    </div>
                    <div class="tracking-step">
                        <div class="tracking-node">💬</div>
                        <div class="tracking-card support">
                            <strong>Guided with real support</strong>
                            <span>Interview notes, follow-ups, and service chat keep you informed in every detail.</span>
                        </div>
                    </div>
                    <div class="tracking-step" style="margin-bottom:0">
                        <div class="tracking-node">♥</div>
                        <div class="tracking-card care">
                            <strong>Different because we care</strong>
                            <span>We are not just a recruiting board. We stay beside you until the next step becomes clear.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section innovation-band">
    <div class="wrap innovation-grid">
        <div class="innovation-lead">
            <p class="eyebrow">New Ideas</p>
            <h2>Next features worth building</h2>
            <p class="lead">A few product ideas that would make KDXJOBS more useful than a standard job board.</p>
        </div>
        <article class="innovation-card"><span class="tiny">Candidate</span><h3>CV health check</h3><p class="muted">Flag missing phone numbers, weak summaries, or skills that match common job filters.</p></article>
        <article class="innovation-card"><span class="tiny">Company</span><h3>Hiring pipeline board</h3><p class="muted">Give employers a simple view of new, reviewed, shortlisted, and final-stage applicants.</p></article>
        <article class="innovation-card"><span class="tiny">Admin</span><h3>Market insights</h3><p class="muted">Show which skills, cities, and salaries are trending across posted jobs.</p></article>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow">Featured Jobs</p><h2>Fresh opportunities for talented people</h2><p>Simple job cards with clear information and fast application flow.</p></div>
        <div class="grid grid3">
            <?php foreach (array_slice($jobs, 0, 3) as $job): ?>
                <?php include __DIR__ . '/partials_job_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'jobs'): ?>
<section class="section">
	    <div class="wrap">
	        <div class="section-title"><p class="eyebrow">Jobs</p><h2>Explore modern job listings</h2><p>Browse open jobs from companies and recruiters.</p></div>
        <?php if (isset($_GET['job']) && $selectedJob): ?>
        <div class="job-detail-layout">
            <aside class="card card-pad">
                <a class="btn outline" style="width:100%;margin-bottom:18px" href="<?= h(app_url('jobs')) ?>">Back to Jobs</a>
                <div class="job-top">
                    <span class="icon">ðŸ’¼</span>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end">
                        <span class="badge"><?= h($selectedJob['type']) ?></span>
                        <?php if (($user['role'] ?? '') === 'jobseeker'): ?>
                            <?php $selectedJobSaved = $selectedJobSaved ?? in_array((int) $selectedJob['id'], $savedJobIds, true); ?>
                            <form method="post" style="margin:0">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="toggle_saved_job">
                                <input type="hidden" name="job_id" value="<?= h((string) $selectedJob['id']) ?>">
                                <input type="hidden" name="save_state" value="<?= $selectedJobSaved ? 'saved' : 'new' ?>">
                                <input type="hidden" name="redirect_page" value="jobs">
                                <button class="btn <?= $selectedJobSaved ? 'outline' : 'dark' ?>"><?= $selectedJobSaved ? 'Saved' : 'Save Job' ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <h3><?= h($selectedJob['title']) ?></h3>
                <p style="margin:.35rem 0 0;font-weight:800;color:#475569"><?= h($selectedJob['company']) ?></p>
                <div class="job-meta">
                    <div>ðŸ“ <?= h($selectedJob['location']) ?></div>
                    <div>ðŸ’° <?= h($selectedJob['salary']) ?></div>
                </div>
                <div class="tags">
                    <?php foreach (tags($selectedJob) as $tag): ?>
                        <span class="tag"><?= h($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (($user['role'] ?? '') === 'jobseeker'): ?>
                    <?php $selectedJobSaved = in_array((int) $selectedJob['id'], $savedJobIds, true); ?>
                    <form method="post" style="margin-top:20px">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="toggle_saved_job">
                        <input type="hidden" name="job_id" value="<?= h((string) $selectedJob['id']) ?>">
                        <input type="hidden" name="save_state" value="<?= $selectedJobSaved ? 'saved' : 'new' ?>">
                        <input type="hidden" name="redirect_page" value="jobs">
                        <button class="btn <?= $selectedJobSaved ? 'outline' : 'dark' ?>" style="width:100%"><?= $selectedJobSaved ? 'Saved ✓' : '☆ Save Job' ?></button>
                    </form>
                <?php endif; ?>
            </aside>
            <main class="card job-detail-main">
                <div class="job-detail-title">
                    <div>
                        <p class="eyebrow" style="margin-bottom:8px">Job Details</p>
                        <h2><?= h($selectedJob['title']) ?></h2>
                        <p class="lead" style="margin-top:10px"><?= h($selectedJob['company']) ?></p>
                    </div>
                    <span class="badge"><?= h($selectedJob['type']) ?></span>
                </div>
                <div class="info-grid" style="margin-bottom:28px">
                    <div class="info"><div class="tiny muted">Location</div><strong><?= h($selectedJob['location']) ?></strong></div>
                    <div class="info"><div class="tiny muted">Salary</div><strong><?= h($selectedJob['salary']) ?></strong></div>
                    <div class="info"><div class="tiny muted">Company</div><strong><?= h($selectedJob['company']) ?></strong></div>
                    <?php if (!empty($selectedJob['expires_at'])): ?><div class="info"><div class="tiny muted">Deadline</div><strong><?= h(date('M j, Y', strtotime((string) $selectedJob['expires_at']))) ?></strong></div><?php endif; ?>
                </div>
                <h3 style="margin-bottom:12px">Description</h3>
                <div class="job-rich-text"><?= rich_text_html($selectedJob['description'] ?? '') ?></div>
                <?php if (!empty($selectedJob['requirements'])): ?>
                    <h3 style="margin-top:28px;margin-bottom:12px">Requirements</h3>
                    <div class="job-rich-text"><?= rich_text_html($selectedJob['requirements'] ?? '') ?></div>
                <?php endif; ?>
                <div class="card card-pad" style="margin-top:32px;background:#f8fafc">
                    <h3 style="margin-bottom:16px">Apply Now</h3>
                    <?php if (!$user): ?>
                        <div class="profile-box">
                            <strong>Login required</strong>
                            <p class="tiny muted" style="margin:10px 0 18px">You need an account before you can apply for this job.</p>
                            <div style="display:flex;gap:12px;flex-wrap:wrap">
                                <a class="btn" href="<?= h(app_url('login')) ?>">Login</a>
                                <a class="btn outline" href="<?= h(app_url('register')) ?>">Register</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form class="form" method="post" enctype="multipart/form-data">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="apply">
                            <input type="hidden" name="job_id" value="<?= h((string) $selectedJob['id']) ?>">
                            <div class="profile-grid">
                                <label class="label">Full Name<input class="input" required name="applicant_name" value="<?= h($user['full_name'] ?? '') ?>"></label>
                                <label class="label">Email<input class="input" required type="email" name="applicant_email" value="<?= h($user['email'] ?? '') ?>"></label>
                                <label class="label">Phone<input class="input" name="applicant_phone" value="<?= h($user['phone'] ?? '') ?>"></label>
                                <label class="label">Role<input class="input" required name="role" value="<?= h($selectedJob['title']) ?>"></label>
                            </div>
                            <?php $savedCv = trim((string) ($user['cv_file'] ?? '')); ?>
                            <label class="label">CV Version
                                <select class="select" name="cv_option" data-cv-option>
                                    <?php if ($savedCv !== ''): ?>
                                        <option value="saved">Use saved CV: <?= h(uploaded_file_label($savedCv)) ?></option>
                                    <?php endif; ?>
                                    <option value="new" <?= $savedCv === '' ? 'selected' : '' ?>>Upload new CV</option>
                                </select>
                            </label>
                            <?php if ($savedCv !== ''): ?>
                                <div class="profile-box" data-cv-saved-note>
                                    <strong>Saved CV ready</strong>
                                    <p class="tiny muted" style="margin:8px 0 0"><?= cv_link_html($savedCv, 'View saved CV') ?></p>
                                </div>
                            <?php endif; ?>
                            <label class="label" data-cv-upload <?= $savedCv !== '' ? 'style="display:none"' : '' ?>>Upload New CV
                                <?= cv_upload_field('application_cv', $savedCv === '') ?>
                            </label>
                            <label class="label">Cover Note<textarea class="textarea" name="cover_note" rows="4"></textarea></label>
                            <button class="btn">Submit Application</button>
                        </form>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        <?php else: ?>
        <div class="jobs-explorer">
            <form class="card filter-panel" method="get">
                <input type="hidden" name="page" value="jobs">
                <div class="filter-head">
                    <h3>Filters</h3>
                    <?php if ($activeJobFilters > 0): ?><span class="filter-count"><?= h((string) $activeJobFilters) ?></span><?php endif; ?>
                </div>
                <details class="filter-block" <?= ($minSalary > 0 || $maxSalary > 0) ? 'open' : '' ?>>
                    <summary>Monthly Range</summary>
                    <div class="filter-body filter-range">
                        <label class="label">Min<input class="input" type="number" min="0" name="min_salary" value="<?= $minSalary > 0 ? h((string) $minSalary) : '' ?>" placeholder="400000"></label>
                        <label class="label">Max<input class="input" type="number" min="0" name="max_salary" value="<?= $maxSalary > 0 ? h((string) $maxSalary) : '' ?>" placeholder="2000000"></label>
                    </div>
                </details>
                <details class="filter-block" open>
                    <summary>Job Types</summary>
                    <div class="filter-body check-list">
                        <label class="check-item"><input type="checkbox" value="" <?= empty($filterTypes) ? 'checked' : '' ?>><span>All job types [<?= h((string) count($allJobs)) ?>]</span></label>
                        <?php foreach ($jobTypes as $type): ?>
                            <label class="check-item"><input type="checkbox" name="type[]" value="<?= h($type) ?>" <?= in_array($type, $filterTypes, true) ? 'checked' : '' ?>><span><?= h($type) ?> [<?= h((string) ($jobTypeCounts[$type] ?? 0)) ?>]</span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <details class="filter-block" <?= $filterLocations ? 'open' : '' ?>>
                    <summary>Location</summary>
                    <div class="filter-body check-list">
                        <?php foreach ($jobLocations as $location): ?>
                            <label class="check-item"><input type="checkbox" name="location[]" value="<?= h($location) ?>" <?= in_array($location, $filterLocations, true) ? 'checked' : '' ?>><span><?= h($location) ?> [<?= h((string) ($jobLocationCounts[$location] ?? 0)) ?>]</span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <details class="filter-block" <?= $filterIndustries ? 'open' : '' ?>>
                    <summary>Industries</summary>
                    <div class="filter-body check-list">
                        <?php foreach ($jobIndustries as $industry): ?>
                            <label class="check-item"><input type="checkbox" name="industry[]" value="<?= h($industry) ?>" <?= in_array($industry, $filterIndustries, true) ? 'checked' : '' ?>><span><?= h($industry) ?> [<?= h((string) ($jobIndustryCounts[$industry] ?? 0)) ?>]</span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <details class="filter-block" <?= $filterTags ? 'open' : '' ?>>
                    <summary>Tags</summary>
                    <div class="filter-body check-list">
                        <?php foreach ($jobTags as $tag): ?>
                            <label class="check-item"><input type="checkbox" name="tag[]" value="<?= h($tag) ?>" <?= in_array($tag, $filterTags, true) ? 'checked' : '' ?>><span><?= h($tag) ?> [<?= h((string) ($jobTagCounts[$tag] ?? 0)) ?>]</span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <div class="filter-actions">
                    <button class="btn">Apply</button>
                    <a class="btn outline" href="<?= h(app_url('jobs')) ?>">Clear</a>
                </div>
            </form>
            <main>
        <form class="search" method="get" style="margin:0 0 28px">
            <input type="hidden" name="page" value="jobs">
            <?php foreach ($filterTypes as $value): ?><input type="hidden" name="type[]" value="<?= h($value) ?>"><?php endforeach; ?>
            <?php foreach ($filterLocations as $value): ?><input type="hidden" name="location[]" value="<?= h($value) ?>"><?php endforeach; ?>
            <?php foreach ($filterIndustries as $value): ?><input type="hidden" name="industry[]" value="<?= h($value) ?>"><?php endforeach; ?>
            <?php foreach ($filterTags as $value): ?><input type="hidden" name="tag[]" value="<?= h($value) ?>"><?php endforeach; ?>
            <?php if ($minSalary > 0): ?><input type="hidden" name="min_salary" value="<?= h((string) $minSalary) ?>"><?php endif; ?>
            <?php if ($maxSalary > 0): ?><input type="hidden" name="max_salary" value="<?= h((string) $maxSalary) ?>"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= h($jobSort) ?>">
            <div class="search-inner"><span>🔍</span><input name="q" value="<?= h($search) ?>" placeholder="Search job title, company, or skill"></div>
            <button class="btn">Search</button>
            <?php if ($search !== ''): ?><a class="btn outline" href="<?= h(app_url('jobs')) ?>">Clear</a><?php endif; ?>
        </form>
        <div class="jobs-toolbar" style="margin-bottom:20px">
            <p class="muted" style="margin:0">Showing <?= h((string) count($jobs)) ?> out of <?= h((string) count($allJobs)) ?> jobs.</p>
            <div class="sort-row">
                <?php if (($user['role'] ?? '') === 'jobseeker'): ?>
                    <form method="post" style="margin:0">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="save_search">
                        <input type="hidden" name="search_query" value="<?= h($search) ?>">
                        <?php foreach ($filterTypes as $value): ?><input type="hidden" name="search_type[]" value="<?= h($value) ?>"><?php endforeach; ?>
                        <?php foreach ($filterLocations as $value): ?><input type="hidden" name="search_location[]" value="<?= h($value) ?>"><?php endforeach; ?>
                        <?php foreach ($filterIndustries as $value): ?><input type="hidden" name="search_industry[]" value="<?= h($value) ?>"><?php endforeach; ?>
                        <?php foreach ($filterTags as $value): ?><input type="hidden" name="search_tag[]" value="<?= h($value) ?>"><?php endforeach; ?>
                        <input type="hidden" name="search_min_salary" value="<?= h((string) $minSalary) ?>">
                        <input type="hidden" name="search_max_salary" value="<?= h((string) $maxSalary) ?>">
                        <button class="btn outline" type="submit">Save Search</button>
                    </form>
                <?php endif; ?>
                <form method="get" class="sort-row">
                    <input type="hidden" name="page" value="jobs">
                    <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= h($search) ?>"><?php endif; ?>
                    <?php foreach ($filterTypes as $value): ?><input type="hidden" name="type[]" value="<?= h($value) ?>"><?php endforeach; ?>
                    <?php foreach ($filterLocations as $value): ?><input type="hidden" name="location[]" value="<?= h($value) ?>"><?php endforeach; ?>
                    <?php foreach ($filterIndustries as $value): ?><input type="hidden" name="industry[]" value="<?= h($value) ?>"><?php endforeach; ?>
                    <?php foreach ($filterTags as $value): ?><input type="hidden" name="tag[]" value="<?= h($value) ?>"><?php endforeach; ?>
                    <?php if ($minSalary > 0): ?><input type="hidden" name="min_salary" value="<?= h((string) $minSalary) ?>"><?php endif; ?>
                    <?php if ($maxSalary > 0): ?><input type="hidden" name="max_salary" value="<?= h((string) $maxSalary) ?>"><?php endif; ?>
                    <span class="tiny muted">Sort by</span>
                    <select class="select" name="sort" data-auto-submit>
                        <option value="newest" <?= $jobSort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $jobSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="salary_high" <?= $jobSort === 'salary_high' ? 'selected' : '' ?>>Salary high</option>
                        <option value="salary_low" <?= $jobSort === 'salary_low' ? 'selected' : '' ?>>Salary low</option>
                    </select>
                </form>
            </div>
        </div>
	        <?php if ($search !== ''): ?><p class="muted" style="margin-top:-12px;margin-bottom:24px">Showing <?= h((string) count($jobs)) ?> result(s) for <strong><?= h($search) ?></strong>.</p><?php endif; ?>
        <?php if (!$jobs): ?>
            <div class="card empty-state"><h3>No jobs match these filters</h3><p class="muted">Try clearing a filter or searching a different title, skill, or company.</p></div>
        <?php else: ?>
	        <div class="grid jobs-grid">
                <?php foreach ($jobs as $job): ?>
                    <?php include __DIR__ . '/partials_job_card.php'; ?>
                <?php endforeach; ?>
	        </div>
        <?php endif; ?>
            </main>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'companies'): ?>
<section class="section">
    <div class="wrap">
        <div class="section-title"><p class="eyebrow">Companies</p><h2>Top companies hiring now</h2><p>Each company has its own profile, logo, industry, location, and active jobs.</p></div>
        <div class="grid grid3">
            <?php foreach ($companies as $c): ?>
            <div class="card job-card card-pad"><span class="icon">🏢</span><h3 style="margin-top:20px"><?= h($c['name']) ?></h3><p class="muted"><?= h($c['industry']) ?></p><p class="badge" style="border-radius:16px;padding:12px 16px"><?= h((string)$c['jobs']) ?> active jobs</p><a class="btn" style="width:100%;margin-top:20px" href="<?= h(app_url('jobs')) ?>">View Company</a></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'about'): ?>
<section class="section">
    <div class="wrap">
        <div class="section-title">
            <p class="eyebrow">About KDXJobs</p>
            <h2>Powered by vision, built for transformation.</h2>
            <p>KdxJobs is a forward-thinking recruitment platform powered by KdxLabs, created to connect ambitious talent with meaningful opportunity in smarter and more adaptive ways.</p>
        </div>
        <div class="about-story">
            <div class="about-panel">
                <h3>Who we are</h3>
                <p>KdxJobs is a forward-thinking recruitment platform powered by KdxLabs, founded in Erbil (Hawler) by a group of ambitious young professionals driven to reshape the future of work.</p>
                <p>We are built on a simple belief: growth comes through transformation and adaptation. In a rapidly changing world, traditional hiring methods are no longer enough. KdxJobs exists to bridge the gap between talent and opportunity by creating smarter, faster, and more efficient ways for people and companies to connect.</p>
                <p>Our mission is to empower job seekers to discover meaningful careers while helping businesses find the right talent that drives real impact. Through innovation, data, and a deep understanding of the local and global job market, we aim to modernize recruitment and elevate workplace standards across the region.</p>
                <p>At KdxJobs, we do not just match people with jobs. We contribute to building a more dynamic, adaptable, and future-ready workforce.</p>
                <div class="about-chip-row">
                    <span class="about-chip">Founded in Erbil (Hawler)</span>
                    <span class="about-chip">Powered by KdxLabs</span>
                    <span class="about-chip">Built for a future-ready workforce</span>
                </div>
            </div>
            <div class="about-panel">
                <h3>What makes KDXJobs different</h3>
                    <div class="about-metric-grid">
                        <div class="about-metric">
                        <strong>Start</strong>
                        <span>From the moment your journey begins, we stay with you instead of leaving you to figure it out alone.</span>
                    </div>
                    <div class="about-metric">
                        <strong>Discover</strong>
                        <span>You are notified about the right opportunities at the right time, based on where you want to grow.</span>
                    </div>
                    <div class="about-metric">
                        <strong>Track</strong>
                        <span>You receive updates on your applications and progress, so every important step stays visible.</span>
                    </div>
                    <div class="about-metric">
                        <strong>Arrive</strong>
                        <span>We keep guiding you until you are successfully hired and onboarded, because your success is our success.</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="about-values">
            <article class="about-value">
                <h3>Our mission</h3>
                <p>To empower job seekers to discover meaningful careers while helping businesses find the right talent that creates real impact.</p>
            </article>
            <article class="about-value">
                <h3>Our difference</h3>
                <p>We do not just connect people to jobs. We provide smart notifications, guidance, and support from the first step of the search until the final hiring outcome.</p>
            </article>
            <article class="about-value">
                <h3>Our vision</h3>
                <p>To help build a more dynamic, adaptable, and future-ready workforce across the region through innovation and better recruitment standards.</p>
            </article>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'faq'): ?>
<section class="section">
    <div class="wrap">
        <div class="section-title">
            <p class="eyebrow">FAQ</p>
            <h2>Answers for job seekers and companies</h2>
            <p>Search the most common KDXJobs questions about applications, saved searches, notifications, and hiring workflows.</p>
        </div>
        <form class="search" method="get" style="margin:0 auto 28px;max-width:860px">
            <input type="hidden" name="page" value="faq">
            <div class="search-inner"><span>&#128269;</span><input name="faq_q" value="<?= h($faqQuery) ?>" placeholder="Search a question or topic"></div>
            <button class="btn">Search</button>
            <?php if ($faqQuery !== ''): ?><a class="btn outline" href="<?= h(app_url('faq')) ?>">Clear</a><?php endif; ?>
        </form>
        <div class="grid" style="max-width:920px;margin:0 auto">
            <?php if (!$filteredFaqItems): ?>
                <div class="profile-box"><strong>No FAQ results found.</strong><p class="tiny muted">Try a simpler keyword or browse all questions.</p></div>
            <?php endif; ?>
            <?php foreach ($filteredFaqItems as $item): ?>
                <details class="app-panel" open>
                    <summary><?= h($item['question']) ?></summary>
                    <div class="app-panel-body"><p class="muted" style="margin:0;line-height:1.8"><?= h($item['answer']) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'contact'): ?>
<section class="section">
    <div class="wrap auth-grid" style="align-items:start">
        <div>
            <p class="eyebrow">Contact Us</p>
            <h2>Talk to the KDXJobs team</h2>
            <p class="lead">Send us your question, partnership request, support issue, or feedback. Your message goes directly into the admin inbox so we can follow up properly.</p>
            <div class="profile-box">
                <strong>What you can contact us about</strong>
                <p class="tiny muted" style="margin:10px 0 0">Account support, hiring questions, company onboarding, platform feedback, partnerships, and technical issues.</p>
            </div>
        </div>
        <form class="form card card-pad" method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="contact_message">
            <label class="label">Full Name<input class="input" required name="full_name" value="<?= h($user['full_name'] ?? '') ?>"></label>
            <label class="label">Email<input class="input" required type="email" name="email" value="<?= h($user['email'] ?? '') ?>"></label>
            <label class="label">Subject<input class="input" required name="subject" placeholder="How can we help?"></label>
            <label class="label">Message<textarea class="textarea" required name="message" rows="7" placeholder="Write your message here"></textarea></label>
            <button class="btn">Send Message</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'policy'): ?>
<section class="section">
    <div class="wrap" style="max-width:980px">
        <div class="section-title">
            <p class="eyebrow">Privacy Policy</p>
            <h2>How KDXJobs handles your information</h2>
            <p>We use candidate and company information only to support recruitment workflows, communication, and platform improvement.</p>
        </div>
        <div class="grid">
            <div class="profile-box"><strong>Information we collect</strong><p class="tiny muted">Account details, profile information, CVs, application records, company data, and contact form submissions.</p></div>
            <div class="profile-box"><strong>Why we collect it</strong><p class="tiny muted">To help job seekers apply, help companies recruit, deliver notifications, improve matching, and provide support.</p></div>
            <div class="profile-box"><strong>How we protect it</strong><p class="tiny muted">We limit access to sensitive data, use role-based dashboards, and avoid exposing personal information unnecessarily.</p></div>
            <div class="profile-box"><strong>Your control</strong><p class="tiny muted">You can update your profile details, change passwords, and contact us if you want help with your account information.</p></div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'terms'): ?>
<section class="section">
    <div class="wrap" style="max-width:980px">
        <div class="section-title">
            <p class="eyebrow">Terms</p>
            <h2>Simple rules for using KDXJobs responsibly</h2>
            <p>KDXJobs is designed for real candidates, real companies, and professional recruitment activity.</p>
        </div>
        <div class="grid">
            <div class="profile-box"><strong>Use the platform honestly</strong><p class="tiny muted">Provide accurate profile, company, and job information. Do not impersonate people or publish misleading opportunities.</p></div>
            <div class="profile-box"><strong>Respect communication</strong><p class="tiny muted">Use service messages, applications, and contact forms respectfully. Harassment, abuse, and spam are not allowed.</p></div>
            <div class="profile-box"><strong>Company responsibility</strong><p class="tiny muted">Companies and admins should manage candidate data professionally and use recruitment decisions responsibly.</p></div>
            <div class="profile-box"><strong>Platform changes</strong><p class="tiny muted">KDXJobs may improve features, workflows, and policies over time to keep the recruitment experience modern and effective.</p></div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'blog'): ?>
<?php
$publishedPosts = array_values(array_filter($blogPosts, static fn(array $post): bool => ($post['status'] ?? 'published') === 'published'));
$selectedPostId = (int) ($_GET['post'] ?? 0);
$selectedPost = null;
foreach ($publishedPosts as $post) {
    if ((int) $post['id'] === $selectedPostId) {
        $selectedPost = $post;
        break;
    }
}
?>
<section class="section">
    <div class="wrap">
        <?php if ($selectedPost): ?>
            <div class="job-detail-layout">
                <aside class="card card-pad">
                    <a class="btn outline" style="width:100%;margin-bottom:18px" href="<?= h(app_url('blog')) ?>">Back to Blog</a>
                    <span class="badge"><?= h($selectedPost['category'] ?: 'Career Advice') ?></span>
                    <h3 style="margin-top:18px"><?= h($selectedPost['title']) ?></h3>
                    <p class="tiny muted" style="line-height:1.7">By <?= h($selectedPost['author_name'] ?: 'KDXJOBS Team') ?><br><?= h(date('M j, Y', strtotime((string) $selectedPost['created_at']))) ?></p>
                </aside>
                <main class="card job-detail-main">
                    <p class="eyebrow" style="margin-bottom:8px"><?= h($selectedPost['category'] ?: 'Career Advice') ?></p>
                    <h2><?= h($selectedPost['title']) ?></h2>
                    <?php if (!empty($selectedPost['excerpt'])): ?><p class="lead" style="margin-top:16px"><?= h($selectedPost['excerpt']) ?></p><?php endif; ?>
                    <div class="job-rich-text" style="margin-top:28px"><?= rich_text_html($selectedPost['content'] ?? '') ?></div>
                </main>
            </div>
        <?php else: ?>
            <div class="section-title"><p class="eyebrow">Blog</p><h2>Career notes from KDXJOBS</h2><p>Practical advice for job seekers, employers, and recruiters building stronger hiring habits.</p></div>
            <div class="grid grid3">
                <?php if (!$publishedPosts): ?>
                    <div class="card empty-state" style="grid-column:1/-1"><h3>No blog posts yet</h3><p class="muted">Admins can publish the first post from the admin dashboard.</p></div>
                <?php endif; ?>
                <?php foreach ($publishedPosts as $post): ?>
                    <article class="card job-card card-pad">
                        <span class="badge"><?= h($post['category'] ?: 'Career Advice') ?></span>
                        <h3 style="margin-top:18px"><?= h($post['title']) ?></h3>
                        <p class="muted" style="line-height:1.75"><?= h($post['excerpt'] ?: substr(strip_tags((string) $post['content']), 0, 150) . '...') ?></p>
                        <p class="tiny muted">By <?= h($post['author_name'] ?: 'KDXJOBS Team') ?> - <?= h(date('M j, Y', strtotime((string) $post['created_at']))) ?></p>
                        <a class="btn outline" style="width:100%;margin-top:16px" href="<?= h(app_url('blog', ['post' => $post['id']])) ?>">Read Article</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'login' || $page === 'register'): ?>
<?php $isRegister = $page === 'register'; ?>
<section class="section">
    <div class="wrap auth-grid">
        <div><p class="eyebrow"><?= $isRegister ? 'Create Account' : 'Welcome Back' ?></p><h2><?= $isRegister ? 'Join the recruitment platform' : 'Login to your dashboard' ?></h2><p class="lead">Secure access for job seekers, companies, and admins with a modern and simple form experience.</p><p class="muted">Demo password is <strong>password</strong>.</p></div>
        <div class="card card-pad">
            <?php if ($isRegister): ?>
            <form class="form" method="post" enctype="multipart/form-data">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="register">
                <div class="role-tabs"><button class="active" type="button">Job Seeker</button><button type="button" data-register-toggle="company">Company</button></div>
                <input type="hidden" name="role" value="jobseeker">
                <label class="label">Full Name<input class="input" required name="full_name" placeholder="Your full name"></label>
                <label class="label">Email<input class="input" required type="email" name="email" placeholder="example@email.com"></label>
                <label class="label">Phone<input class="input" name="phone" placeholder="+964 750 000 0000"></label>
                <div class="label">Skills
                    <?= skills_checkboxes() ?>
                </div>
                <label class="label">Password<input class="input" required type="password" name="password" placeholder="password"></label>
                <label class="label">Upload CV
                    <?= cv_upload_field('cv_file', true) ?>
                </label>
                <button class="btn" style="width:100%">Create Account</button>
            </form>
            <form id="company-form" class="form hidden" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="register"><input type="hidden" name="role" value="company">
                <div class="role-tabs"><button type="button" data-register-toggle="jobseeker">Job Seeker</button><button class="active" type="button">Company</button></div>
                <label class="label">Company Name<input class="input" required name="company_name" placeholder="Company name"></label>
                <label class="label">Email<input class="input" required type="email" name="email" placeholder="example@email.com"></label>
                <label class="label">Phone<input class="input" name="phone" placeholder="+964 750 000 0000"></label>
                <label class="label">Industry<input class="input" required name="industry" placeholder="Technology, FMCG, Finance..."></label>
                <label class="label">Location<input class="input" required name="location" placeholder="Erbil, Baghdad, Remote..."></label>
                <label class="label">Password<input class="input" required type="password" name="password" placeholder="password"></label>
                <div class="upload"><div style="font-size:28px">⬆️</div><strong>Upload Company Logo</strong><br><span class="tiny muted">PNG or JPG placeholder</span></div>
                <button class="btn" style="width:100%">Create Account</button>
            </form>
            <?php else: ?>
            <form class="form" method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="login">
                <label class="label">Email<input class="input" required type="email" name="email" placeholder="example@email.com"></label>
                <label class="label">Password<input class="input" required type="password" name="password" placeholder="password"></label>
                <p style="text-align:right;color:#0284c7;font-weight:800">Forgot password?</p>
                <button class="btn" style="width:100%">Login</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($page === 'application' && $selectedApplication): ?>
<?php
$backPage = (string) ($_GET['back_page'] ?? (is_admin_role($user['role'] ?? '') ? 'admin' : (($user['role'] ?? '') === 'company' ? 'company' : 'user')));
$backTab = (string) ($_GET['back_tab'] ?? 'applications');
$isApplicationOwner = !is_admin_role($user['role'] ?? '') && ($user['role'] ?? '') !== 'company';
?>
<section class="section">
    <div class="wrap" style="max-width:1100px">
        <div class="section-head">
            <div>
                <span class="eyebrow">Application Details</span>
                <h2><?= h($selectedApplication['applicant_name'] ?? 'Application') ?></h2>
                <p class="lead"><?= h($selectedApplication['job_title'] ?? '') ?> at <?= h($selectedApplication['company'] ?? '') ?></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a class="btn outline" href="<?= h(app_url($backPage, ['tab' => $backTab])) ?>">Back</a>
                <?php if (!empty($selectedApplication['cv_file'])): ?>
                    <a class="btn" href="<?= h(download_url((string) $selectedApplication['cv_file'])) ?>" target="_blank" rel="noopener">Open CV</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card card-pad">
                <h3>Overview</h3>
                <div class="profile-grid">
                    <?php foreach ([
                        'Applicant' => $selectedApplication['applicant_name'] ?? '',
                        'Email' => $selectedApplication['applicant_email'] ?? '',
                        'Phone' => $selectedApplication['applicant_phone'] ?? 'Not provided',
                        'Role Applied' => $selectedApplication['role'] ?? '',
                        'Status' => $selectedApplication['status'] ?? '',
                        'Recruiter' => $selectedApplication['recruiter_name'] ?? 'Not assigned',
                    ] as $label => $value): ?>
                        <div class="profile-box"><span class="tiny" style="color:#0369a1;font-weight:900"><?= h($label) ?></span><br><strong><?= h((string) $value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:18px">
                    <?= candidate_match_html($selectedApplication) ?>
                </div>
                <div class="job-rich-text" style="margin-top:18px">
                    <h3>Cover Note</h3>
                    <p><?= nl2br(h((string) ($selectedApplication['cover_note'] ?? 'No cover note submitted.'))) ?></p>
                </div>
            </div>

            <div class="card card-pad">
                <h3>Job Snapshot</h3>
                <?php
                $selectedScreen = application_screen_data($selectedApplication);
                $selectedEducation = education_entry_labels($selectedScreen['education'] ?? []);
                $selectedRoles = normalize_text_list($selectedScreen['roles'] ?? []);
                $selectedTools = normalize_text_list($selectedScreen['tools'] ?? []);
                $selectedSkills = normalize_skill_list(array_merge(
                    selected_skills($selectedApplication['candidate_cv_ai_skills'] ?? ''),
                    selected_skills($selectedApplication['cv_ai_skills'] ?? ''),
                    normalize_text_list($selectedScreen['skills'] ?? []),
                    $selectedTools
                ));
                ?>
                <div class="profile-grid">
                    <?php foreach ([
                        'Job Title' => $selectedApplication['job_title'] ?? '',
                        'Company' => $selectedApplication['company'] ?? '',
                        'Required Skills' => $selectedApplication['job_tags'] ?? 'Not tagged',
                        'Detected Skills' => $selectedSkills ? implode(', ', array_slice($selectedSkills, 0, 12)) : 'Not detected',
                        'Detected Tools' => $selectedTools ? implode(', ', array_slice($selectedTools, 0, 8)) : 'Not detected',
                        'Education Details' => $selectedEducation ? implode(' | ', array_slice($selectedEducation, 0, 3)) : 'Not detected',
                        'Recent Roles' => $selectedRoles ? implode(' | ', array_slice($selectedRoles, 0, 3)) : 'Not detected',
                        'Detected Experience' => (($selectedApplication['candidate_cv_ai_years'] ?? $selectedApplication['cv_ai_years'] ?? null) ? ((string) ($selectedApplication['candidate_cv_ai_years'] ?? $selectedApplication['cv_ai_years']) . ' years') : 'Not detected'),
                    ] as $label => $value): ?>
                        <div class="profile-box"><span class="tiny" style="color:#0369a1;font-weight:900"><?= h($label) ?></span><br><strong><?= h((string) $value) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($selectedApplication['job_description'])): ?>
                    <div class="job-rich-text" style="margin-top:18px">
                        <h3>Job Description</h3>
                        <?= rich_text_html((string) $selectedApplication['job_description']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($selectedApplication['job_requirements'])): ?>
                    <div class="job-rich-text" style="margin-top:18px">
                        <h3>Requirements</h3>
                        <?= rich_text_html((string) $selectedApplication['job_requirements']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="application-panels" style="margin-top:22px">
            <?php if (is_admin_role($user['role'] ?? '') || ($user['role'] ?? '') === 'company'): ?>
                <details class="app-panel" open>
                    <summary>Update Status</summary>
                    <div class="app-panel-body">
                        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="application_status">
                            <input type="hidden" name="redirect_page" value="application">
                            <input type="hidden" name="application_id" value="<?= h((string) $selectedApplication['id']) ?>">
                            <input type="hidden" name="back_page" value="<?= h($backPage) ?>">
                            <input type="hidden" name="back_tab" value="<?= h($backTab) ?>">
                            <?php foreach (['Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'] as $statusAction): ?>
                                <button name="status" value="<?= h($statusAction) ?>" class="btn<?= in_array($statusAction, ['Accepted'], true) ? ' green' : (in_array($statusAction, ['Rejected'], true) ? ' red' : ' outline') ?>"><?= h($statusAction) ?></button>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </details>
                <details class="app-panel">
                    <summary>Edit Application Details</summary>
                    <div class="app-panel-body"><?= application_edit_form($selectedApplication, 'applications', 'application', ['application' => (int) $selectedApplication['id'], 'back_page' => $backPage, 'back_tab' => $backTab]) ?></div>
                </details>
                <details class="app-panel">
                    <summary>AI Screening</summary>
                    <div class="app-panel-body">
                        <p class="tiny muted" style="margin-top:0">Refresh this application after changing the CV, job requirements, or AI matching mode.</p>
                        <?= rescreen_application_form($selectedApplication, 'application', 'applications', ['application' => (int) $selectedApplication['id'], 'back_page' => $backPage, 'back_tab' => $backTab]) ?>
                    </div>
                </details>
                <details class="app-panel">
                    <summary>Schedule Interview</summary>
                    <div class="app-panel-body">
                        <form method="post" class="interview-form">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="schedule_interview">
                            <input type="hidden" name="redirect_page" value="application">
                            <input type="hidden" name="application_id" value="<?= h((string) $selectedApplication['id']) ?>">
                            <input type="hidden" name="back_page" value="<?= h($backPage) ?>">
                            <input type="hidden" name="back_tab" value="<?= h($backTab) ?>">
                            <label class="label">Interview Date<input class="input" type="datetime-local" name="scheduled_at" required></label>
                            <label class="label">Place or Link<input class="input" name="interview_location" placeholder="Office, Google Meet, Zoom"></label>
                            <label class="label">Note<input class="input" name="interview_note" placeholder="What should the candidate prepare?"></label>
                            <button class="btn">Schedule Interview</button>
                        </form>
                    </div>
                </details>
            <?php elseif ($isApplicationOwner): ?>
                <details class="app-panel">
                    <summary>Manage Application</summary>
                    <div class="app-panel-body">
                        <form method="post" data-confirm="Withdraw this application?">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="withdraw_application">
                            <input type="hidden" name="redirect_page" value="application">
                            <input type="hidden" name="application_id" value="<?= h((string) $selectedApplication['id']) ?>">
                            <button class="btn red">Withdraw Application</button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>
            <details class="app-panel" open><summary>Application Timeline</summary><div class="app-panel-body"><?= timeline_html((int) $selectedApplication['id'], $applicationEventsByApplication, $interviewsByApplication) ?></div></details>
            <details class="app-panel" open><summary>Service Center Chat</summary><div class="app-panel-body"><?= service_chat_html($selectedApplication, $serviceMessagesByApplication, is_admin_role($user['role'] ?? '')) ?></div></details>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (in_array($page, ['user','company','admin'], true)): ?>
<?php
$isUser = $page === 'user';
$isCompany = $page === 'company';
$title = $isUser ? 'Job Seeker Dashboard' : ($isCompany ? 'Company Dashboard' : (is_super_admin() ? 'Super Admin Dashboard' : 'Admin Dashboard'));
$subtitle = $isUser ? 'Manage your profile, CV, saved jobs, and applications.' : ($isCompany ? 'Manage your company profile, job posts, and applicants.' : (is_super_admin() ? 'Create admins and control users, companies, jobs, applications, and settings.' : 'Manage recruitment users, companies, job posts, approvals, and statistics.'));
$company = $companies[0] ?? null;
if ($isCompany && $user) {
    foreach ($companies as $candidateCompany) {
        if ((int) ($candidateCompany['user_id'] ?? 0) === (int) ($user['id'] ?? 0)) {
            $company = $candidateCompany;
            break;
        }
    }
}
$userEmail = (string) ($user['email'] ?? '');
$userApplications = $userEmail !== '' ? array_values(array_filter($applicants, static fn(array $a): bool => $a['applicant_email'] === $userEmail)) : [];
$companyName = (string) ($company['name'] ?? '');
$companyId = (int) ($company['id'] ?? 0);
$companyJobs = $companyId > 0 ? array_values(array_filter($jobs, static fn(array $job): bool => (int) ($job['company_id'] ?? 0) === $companyId)) : [];
$companyApplications = $companyId > 0 ? array_values(array_filter($applicants, static fn(array $a): bool => (int) ($a['company_id'] ?? 0) === $companyId)) : [];
$recruiterJobs = is_admin_role() && !is_super_admin()
    ? array_values(array_filter($jobs, static fn(array $job): bool => (int) ($job['recruiter_id'] ?? 0) === (int) ($user['id'] ?? 0)))
    : $jobs;
$recruiterApplications = is_admin_role() && !is_super_admin()
    ? array_values(array_filter($applicants, static fn(array $a): bool => (int) ($a['recruiter_id'] ?? 0) === (int) ($user['id'] ?? 0)))
    : $applicants;
$visibleJobs = $isCompany ? $companyJobs : $recruiterJobs;
$visibleApplications = $isCompany ? $companyApplications : $recruiterApplications;
if ($applicationSearch !== '') {
    $visibleApplications = array_values(array_filter($visibleApplications, static function (array $a) use ($applicationSearch): bool {
        $haystack = strtolower(implode(' ', [
            $a['applicant_name'] ?? '',
            $a['applicant_email'] ?? '',
            $a['job_title'] ?? '',
            $a['company'] ?? '',
            $a['status'] ?? '',
            $a['recruiter_name'] ?? '',
        ]));
        return str_contains($haystack, strtolower($applicationSearch));
    }));
}
if ($jobManageSearch !== '') {
    $visibleJobs = array_values(array_filter($visibleJobs, static function (array $job) use ($jobManageSearch): bool {
        $haystack = strtolower(implode(' ', [
            $job['title'] ?? '',
            $job['company'] ?? '',
            $job['location'] ?? '',
            $job['type'] ?? '',
            $job['salary'] ?? '',
            $job['status'] ?? '',
            $job['tags'] ?? '',
            $job['recruiter_name'] ?? '',
        ]));
        return str_contains($haystack, strtolower($jobManageSearch));
    }));
}
$selectedManageJob = null;
foreach ($visibleJobs as $visibleJob) {
    if ((int) ($visibleJob['id'] ?? 0) === $manageEditJobId) {
        $selectedManageJob = $visibleJob;
        break;
    }
}
$applicationsStatusOptions = ['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'];
$dashboardApplications = $isUser ? $userApplications : ($isCompany ? $companyApplications : $recruiterApplications);
$filteredDashboardApplications = filter_applications_list($dashboardApplications, $applicationsQuery, $applicationsStatus, $dashboardApplications);
$applicationsPerPage = 5;
$applicationsTotal = count($filteredDashboardApplications);
$applicationsTotalPages = max(1, (int) ceil($applicationsTotal / $applicationsPerPage));
$applicationsPage = min($applicationsPage, $applicationsTotalPages);
$pagedDashboardApplications = array_slice($filteredDashboardApplications, ($applicationsPage - 1) * $applicationsPerPage, $applicationsPerPage);
$dashboardMenu = $isUser
    ? ['profile' => 'Profile', 'applications' => 'My Applications', 'saved' => 'Saved Jobs', 'saved_searches' => 'Saved Searches', 'settings' => 'Settings']
        : ($isCompany
        ? ['profile' => 'Profile', 'applications' => 'Applicants', 'post_job' => 'Post a Job', 'manage' => 'Manage Jobs', 'statistics' => 'Statistics', 'settings' => 'Settings']
        : (is_super_admin()
            ? ['profile' => 'Profile', 'applications' => 'Applications', 'post_job' => 'Post a Job', 'blog' => 'Blog', 'inbox' => 'Inbox', 'manage' => 'Manage', 'admins' => 'Admins', 'statistics' => 'Statistics', 'settings' => 'Settings']
            : ['profile' => 'Profile', 'applications' => 'Applications', 'post_job' => 'Post a Job', 'blog' => 'Blog', 'inbox' => 'Inbox', 'manage' => 'Manage', 'statistics' => 'Statistics', 'settings' => 'Settings']));
if (!isset($dashboardMenu[$tab])) {
    $tab = array_key_first($dashboardMenu);
}
$tabTitle = $dashboardMenu[$tab] ?? ucfirst(str_replace('_', ' ', $tab));
$availableSkills = skill_options();
$chosenSkills = selected_skills($user['skills'] ?? '');
$profileScore = $user ? profile_score($user) : 0;
?>
<section class="section">
    <div class="wrap">
        <div class="dash-hero"><h2><?= h($title) ?></h2><p><?= h($subtitle) ?></p></div>
        <div class="dash-layout">
            <aside class="card side">
                <div class="side-user"><span class="icon"><?= $isUser ? '👤' : ($isCompany ? '🏢' : '🛡️') ?></span><div><strong><?= h($isUser ? ($user['full_name'] ?? 'Zagros Baban') : ($isCompany ? ($user['company_name'] ?? 'BlueTech') : ($user['full_name'] ?? 'Admin'))) ?></strong><br><span class="tiny muted"><?= $isUser ? 'Data Analyst' : ($isCompany ? 'Tech Company' : (is_super_admin() ? 'Super Admin' : 'Recruiter Admin')) ?></span></div></div>
                <?php foreach ($dashboardMenu as $key => $item): ?>
                    <a class="side-btn <?= $tab === $key ? 'active' : '' ?>" href="<?= h(app_url($page, ['tab' => $key])) ?>">⚙️ <?= h($item) ?></a>
                <?php endforeach; ?>
            </aside>
            <main class="grid">
                <?php if ($user): ?>
                    <div id="notification-watch" data-latest-notification-id="<?= h((string) (int) ($notifications[0]['id'] ?? 0)) ?>" hidden></div>
                <?php endif; ?>
                <?php if ($notifications): ?>
                    <div class="card notification-card">
                        <h3 style="margin-bottom:14px">Notifications</h3>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item">
                                    <strong><?= h($notification['title']) ?></strong>
                                    <p class="tiny muted" style="margin:6px 0 0"><?= h($notification['body']) ?></p>
                                    <span class="tiny muted"><?= h(date('M j, Y g:i A', strtotime((string) $notification['created_at']))) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="grid grid3">
                    <div class="card stat"><span class="icon">💼</span><div><span class="tiny muted"><?= $isUser ? 'Applied Jobs' : ($isCompany ? 'Active Jobs' : 'Assigned Jobs') ?></span><div class="stat-value"><?= $isUser ? h((string) count($userApplications)) : ($isCompany ? h((string) count($companyJobs)) : h((string) count($recruiterJobs))) ?></div></div></div>
                    <div class="card stat"><span class="icon"><?= $isUser ? '⭐' : ($isCompany ? '👥' : '🏢') ?></span><div><span class="tiny muted"><?= $isUser ? 'Saved Jobs' : ($isCompany ? 'Applicants' : 'My Applications') ?></span><div class="stat-value"><?= $isUser ? h((string) count($savedJobs)) : ($isCompany ? h((string) count($companyApplications)) : h((string) count($recruiterApplications))) ?></div></div></div>
                    <div class="card stat"><span class="icon">📊</span><div><span class="tiny muted"><?= $isUser ? 'Profile Score' : ($isCompany ? 'Profile Score' : 'Open Jobs') ?></span><div class="stat-value"><?= ($isUser || $isCompany) ? h((string) $profileScore) . '%' : h($stats['openJobs']) ?></div><?php if ($isUser || $isCompany): ?><div class="score-bar"><span style="width:<?= h((string) $profileScore) ?>%"></span></div><?php endif; ?></div></div>
                </div>
                <div class="card card-pad">
                    <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:20px">
                        <h3><?= h($tabTitle) ?></h3>
                        <?php if ($tab === 'manage' && !$isUser): ?><a class="btn" href="<?= h(app_url($page, ['tab' => 'settings'])) ?>">Edit</a><?php endif; ?>
                    </div>
                    <?php if ($tab === 'profile' && $isUser): ?>
                        <div class="profile-grid">
                            <?php
                            $cvDisplay = !empty($user['cv_file'])
                                ? cv_link_html($user['cv_file'], 'View uploaded CV')
                                : 'No CV uploaded';
                            $profileItems = ['Full Name'=>$user['full_name'] ?? 'Zagros Baban','Email'=>$user['email'] ?? 'zagros@example.com','Phone'=>$user['phone'] ?? '+964 750 000 0000','Location'=>$user['location'] ?? 'Not added','Skills'=>$user['skills'] ?? 'No skills selected','CV'=>$cvDisplay];
                            if (!empty($user['cv_ai_summary'])) {
                                $profileItems['AI CV Screening'] = $user['cv_ai_summary'];
                            }
                            foreach ($profileItems as $k=>$v):
                            ?>
                            <div class="profile-box"><span class="tiny" style="color:#0369a1;font-weight:900"><?= h($k) ?></span><br><strong><?= $k === 'CV' ? $v : h($v) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'profile' && $isCompany): ?>
                        <div class="profile-grid">
                            <?php foreach (['Company'=>$user['company_name'] ?? 'BlueTech','Email'=>$user['email'] ?? 'company@example.com','Phone'=>$user['phone'] ?? '+964 750 111 1111','Industry'=>$user['industry'] ?? 'Data & AI','Location'=>$user['location'] ?? 'Erbil, Iraq'] as $k=>$v): ?>
                            <div class="profile-box"><span class="tiny" style="color:#0369a1;font-weight:900"><?= h($k) ?></span><br><strong><?= h($v) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'profile'): ?>
                        <div class="profile-grid">
                            <?php foreach (['Name'=>$user['full_name'] ?? 'Admin','Email'=>$user['email'] ?? 'admin@example.com','Role'=>is_super_admin() ? 'Super Admin' : 'Recruiter Admin','Status'=>$user['status'] ?? 'active'] as $k=>$v): ?>
                            <div class="profile-box"><span class="tiny" style="color:#0369a1;font-weight:900"><?= h($k) ?></span><br><strong><?= h($v) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'applications' && $isUser): ?>
                        <?php applications_browser_controls('user', 'applications', $applicationsQuery, $applicationsStatus, $applicationsStatusOptions, $applicationsTotal); ?>
                        <div class="grid">
                            <?php if (!$pagedDashboardApplications): ?>
                                <div class="profile-box"><strong>No applications yet</strong><p class="tiny muted">Apply for a job from the Jobs page and it will appear here.</p></div>
                            <?php endif; ?>
                            <?php foreach ($pagedDashboardApplications as $a): ?>
                            <div class="applicant application-card">
                                <div class="application-row">
                                    <div class="application-title">
                                        <strong><a href="<?= h(application_page_url($a, 'user', 'applications')) ?>"><?= h($a['job_title']) ?></a></strong><br>
                                        <span class="tiny muted"><?= h($a['company']) ?></span>
                                        <span class="status-pill <?= h(status_class($a['status'])) ?>"><?= h($a['status']) ?></span>
                                    </div>
                                    <div class="application-actions">
                                        <a class="btn outline" href="<?= h(application_page_url($a, 'user', 'applications')) ?>">Open</a>
                                        <form method="post" data-confirm="Withdraw this application?">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="withdraw_application">
                                            <input type="hidden" name="application_id" value="<?= h((string) $a['id']) ?>">
                                            <button class="btn red">Withdraw</button>
                                        </form>
                                    </div>
                                </div>
                                <?= progress_html($a['status']) ?>
                                <div class="application-panels">
                                    <details class="app-panel"><summary>Application Timeline</summary><div class="app-panel-body"><?= timeline_html((int) $a['id'], $applicationEventsByApplication, $interviewsByApplication) ?></div></details>
                                    <details class="app-panel"><summary>Service Center Chat</summary><div class="app-panel-body"><?= service_chat_html($a, $serviceMessagesByApplication, true) ?></div></details>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php applications_pagination('user', 'applications', $applicationsQuery, $applicationsStatus, $applicationsPage, $applicationsTotalPages); ?>
                    <?php elseif ($tab === 'applications'): ?>
                        <?php applications_browser_controls($page, 'applications', $applicationsQuery, $applicationsStatus, $applicationsStatusOptions, $applicationsTotal, is_admin_role() ? $savedAiApplicationSearches : []); ?>
                        <?php applications_hiring_table($pagedDashboardApplications, $page, 'applications', $dashboardApplications); ?>
                        <?php applications_pagination($page, 'applications', $applicationsQuery, $applicationsStatus, $applicationsPage, $applicationsTotalPages); ?>
                    <?php elseif ($tab === 'saved' && $isUser): ?>
                        <div class="grid">
                            <?php if (!$savedJobs): ?>
                                <div class="profile-box"><strong>No saved jobs yet</strong><p class="tiny muted">Press Save on any job card and it will appear here.</p></div>
                            <?php endif; ?>
                            <?php foreach ($savedJobs as $job): ?>
                                <div class="applicant">
                                    <div><strong><?= h($job['title']) ?></strong><br><span class="tiny muted"><?= h($job['company']) ?> · <?= h($job['location']) ?></span></div>
                                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                                        <a class="btn outline" href="<?= h(app_url('jobs', ['job' => $job['id']])) ?>">View</a>
                                        <form method="post">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="toggle_saved_job">
                                            <input type="hidden" name="job_id" value="<?= h((string) $job['id']) ?>">
                                            <input type="hidden" name="save_state" value="saved">
                                            <input type="hidden" name="redirect_page" value="user">
                                            <input type="hidden" name="redirect_tab" value="saved">
                                            <button class="btn red">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'saved_searches' && $isUser): ?>
                        <div class="grid">
                            <?php if (!$savedSearches): ?>
                                <div class="profile-box"><strong>No saved searches yet</strong><p class="tiny muted">Use Save Search on the Jobs page to store filters and receive alerts for matching roles.</p></div>
                            <?php endif; ?>
                            <?php foreach ($savedSearches as $savedSearch): ?>
                                <div class="applicant">
                                    <div>
                                        <strong><?= h($savedSearch['query_text'] ?: 'Saved search') ?></strong><br>
                                        <span class="tiny muted">
                                            <?= h($savedSearch['types'] ?: 'All types') ?> ·
                                            <?= h($savedSearch['locations'] ?: 'All locations') ?> ·
                                            <?= h($savedSearch['industries'] ?: 'All industries') ?>
                                        </span><br>
                                        <span class="tiny muted">Saved <?= h(date('M j, Y', strtotime((string) $savedSearch['created_at']))) ?></span>
                                    </div>
                                    <form method="post" data-confirm="Remove this saved search?">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_saved_search">
                                        <input type="hidden" name="saved_search_id" value="<?= h((string) $savedSearch['id']) ?>">
                                        <button class="btn red">Remove</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'post_job' && ($isCompany || is_admin_role())): ?>
                        <form class="form card-pad" method="post" style="background:#f8fafc;border-radius:20px;max-width:760px">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="post_job">
                            <h3><?= $isCompany ? 'Post a Company Job' : 'Post Job as Recruiter' ?></h3>
                            <?php if ($isCompany): ?>
                                <input type="hidden" name="company_id" value="<?= h((string) $companyId) ?>">
                                <div class="profile-box"><strong>Posting as <?= h($company['name'] ?? 'Your Company') ?></strong><p class="tiny muted">This job will be related only to your company.</p></div>
                            <?php else: ?>
                                <label class="label">Company<select class="select" name="company_id"><?php foreach ($companies as $c): ?><option value="<?= h((string)$c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
                                <div class="profile-box"><strong>Assigned to <?= h($user['full_name'] ?? 'this recruiter') ?></strong><p class="tiny muted">Applications for this job will appear only in this recruiter's dashboard. Super admin can see all.</p></div>
                            <?php endif; ?>
                            <label class="label">Job Title<select class="select" required name="title"><?= select_options(job_title_options(), 'Select job title') ?></select></label>
                            <label class="label">Location<select class="select" required name="location"><?= select_options(job_location_options(), 'Select location') ?></select></label>
                            <label class="label">Salary<select class="select" required name="salary"><?= select_options(salary_range_options(), 'Select salary range') ?></select></label>
                            <label class="label">Type<select class="select" name="type"><option>Full-time</option><option>Remote</option><option>Hybrid</option><option>Contract</option></select></label>
                            <label class="label">Description
                                <div class="editor-wrap" data-editor>
                                    <div class="editor-toolbar" aria-label="Description editor tools">
                                        <button type="button" data-command="formatBlock" data-value="h3" title="Heading">H</button>
                                        <button type="button" data-command="bold" title="Bold">B</button>
                                        <button type="button" data-command="italic" title="Italic">I</button>
                                        <button type="button" data-command="underline" title="Underline">U</button>
                                        <button type="button" data-command="insertUnorderedList" title="Bullet list">•</button>
                                        <button type="button" data-command="insertOrderedList" title="Numbered list">1.</button>
                                        <button type="button" data-command="formatBlock" data-value="p" title="Paragraph">P</button>
                                    </div>
                                    <div class="rich-editor" contenteditable="true" data-placeholder="Describe the role, responsibilities, benefits, and working style."></div>
                                    <textarea class="rich-editor-source" name="description"></textarea>
                                </div>
                            </label>
                            <input type="hidden" name="requirements" value="">
                            <label class="label">Application Deadline<input class="input" type="date" name="expires_at"></label>
                            <label class="label">Tags<input class="input" name="tags" placeholder="React, API, SQL"></label>
                            <?php if ($error): ?><div class="alert bad" style="margin:0"><?= h($error) ?></div><?php endif; ?>
                            <button class="btn"><?= $isCompany ? 'Post Company Job' : 'Post Assigned Job' ?></button>
                        </form>
                    <?php elseif ($tab === 'manage' && $isCompany): ?>
                        <div class="manage-overview">
                            <div class="card manage-copy">
                                <h3>Manage Job Posts</h3>
                                <p>This section is only for your company job posts. Use <strong>Applications</strong> to review candidates, and use <strong>Manage</strong> to search, edit, and remove jobs.</p>
                            </div>
                            <div class="manage-summary">
                                <div class="profile-box">
                                    <strong><?= h((string) count($visibleJobs)) ?></strong>
                                    <span>Visible job posts</span>
                                </div>
                                <div class="profile-box">
                                    <strong><?= h((string) count(array_filter($visibleJobs, static fn(array $job): bool => ($job['status'] ?? 'active') === 'active'))) ?></strong>
                                    <span>Active jobs</span>
                                </div>
                                <div class="profile-box">
                                    <strong><?= h((string) count(array_filter($visibleJobs, static fn(array $job): bool => !empty($job['expires_at']) && strtotime((string) $job['expires_at']) < time()))) ?></strong>
                                    <span>Expired deadlines</span>
                                </div>
                            </div>
                        </div>
                        <h3 style="margin-top:0;margin-bottom:16px">Job Posts</h3>
                        <?php jobs_table($visibleJobs, 'company', $jobManageSearch, 'manage'); ?>
                        <?php if ($selectedManageJob): ?>
                        <div class="manage-edit-stack" id="job-edit-panel">
                            <details class="app-panel" open>
                                <summary>Edit <?= h($selectedManageJob['title'] ?? 'Job') ?></summary>
                                <div class="app-panel-body">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                                        <span class="tiny muted">The inputs below are loaded for the selected job post.</span>
                                        <a class="btn outline" href="<?= h(app_url('company', array_filter([
                                            'tab' => 'manage',
                                            'job_q' => $jobManageSearch !== '' ? $jobManageSearch : null,
                                        ], static fn($value): bool => $value !== null))) ?>">Close</a>
                                    </div>
                                    <?= job_edit_form($selectedManageJob, $companies, false) ?>
                                </div>
                            </details>
                        </div>
                        <?php endif; ?>
                        <div class="hidden">
                            <div class="grid">
                                <?php foreach (array_slice($isCompany ? $companyApplications : $recruiterApplications, 0, 5) as $a): ?>
                                <div class="applicant application-card">
                                    <div class="application-row">
                                    <div>
                                        <strong><?= h($a['applicant_name']) ?></strong><br>
                                        <span class="tiny muted">Applied for <?= h($a['job_title']) ?></span>
                                        <span class="status-pill <?= h(status_class($a['status'])) ?>"><?= h($a['status']) ?></span>
                                    </div>
                                    <form method="post" style="display:flex;gap:8px"><?= csrf_input() ?><input type="hidden" name="action" value="application_status"><input type="hidden" name="redirect_tab" value="manage"><input type="hidden" name="application_id" value="<?= h((string)$a['id']) ?>"><button name="status" value="Accepted" class="btn green">✓ Accept</button><button name="status" value="Rejected" class="btn red">✕ Reject</button></form>
                                    </div>
                                    <?= progress_html($a['status']) ?>
                                    <?= timeline_html((int) $a['id'], $applicationEventsByApplication, $interviewsByApplication) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <form class="form card-pad" method="post" style="background:#f8fafc;border-radius:20px">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="post_job">
                                <?php if ($isCompany): ?>
                                    <input type="hidden" name="company_id" value="<?= h((string) $companyId) ?>">
                                    <div class="profile-box"><strong>Posting as <?= h($company['name'] ?? 'Your Company') ?></strong><p class="tiny muted">This job will be related only to your company.</p></div>
                                <?php else: ?>
                                    <label class="label">Company<select class="select" name="company_id"><?php foreach ($companies as $c): ?><option value="<?= h((string)$c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
                                    <div class="profile-box"><strong>Recruiter Assignment</strong><p class="tiny muted">Applications for this job will be supervised by <?= h($user['full_name'] ?? 'this recruiter') ?>.</p></div>
                                <?php endif; ?>
                                <label class="label">Job Title<select class="select" required name="title"><?= select_options(job_title_options(), 'Select job title') ?></select></label>
                                <label class="label">Location<select class="select" required name="location"><?= select_options(job_location_options(), 'Select location') ?></select></label>
                                <label class="label">Salary<select class="select" required name="salary"><?= select_options(salary_range_options(), 'Select salary range') ?></select></label>
                                <label class="label">Type<select class="select" name="type"><option>Full-time</option><option>Remote</option><option>Hybrid</option><option>Contract</option></select></label>
                                <label class="label">Description
                                    <div class="editor-wrap" data-editor>
                                        <div class="editor-toolbar" aria-label="Description editor tools">
                                            <button type="button" data-command="formatBlock" data-value="h3" title="Heading">H</button>
                                            <button type="button" data-command="bold" title="Bold">B</button>
                                            <button type="button" data-command="italic" title="Italic">I</button>
                                            <button type="button" data-command="underline" title="Underline">U</button>
                                            <button type="button" data-command="insertUnorderedList" title="Bullet list">•</button>
                                            <button type="button" data-command="insertOrderedList" title="Numbered list">1.</button>
                                            <button type="button" data-command="formatBlock" data-value="p" title="Paragraph">P</button>
                                        </div>
                                        <div class="rich-editor" contenteditable="true" data-placeholder="Describe the role, responsibilities, benefits, and working style."></div>
                                        <textarea class="rich-editor-source" name="description"></textarea>
                                    </div>
                                </label>
                                <input type="hidden" name="requirements" value="">
                                <label class="label">Application Deadline<input class="input" type="date" name="expires_at"></label>
                                <label class="label">Tags<input class="input" name="tags" placeholder="React, API, SQL"></label>
                                <button class="btn">Post Job</button>
                            </form>
                        </div>
                    <?php elseif ($tab === 'manage' && is_admin_role()): ?>
                        <div class="manage-overview">
                            <div class="card manage-copy">
                                <h3>Manage Job Posts</h3>
                                <p>This page is for job-post operations only. Candidate review stays in <strong>Applications</strong>, while <strong>Manage</strong> handles job visibility, ownership, editing, and cleanup.</p>
                            </div>
                            <div class="manage-summary">
                                <div class="profile-box">
                                    <strong><?= h((string) count($visibleJobs)) ?></strong>
                                    <span>Visible job posts</span>
                                </div>
                                <div class="profile-box">
                                    <strong><?= h((string) count(array_filter($visibleJobs, static fn(array $job): bool => ($job['status'] ?? 'active') === 'active'))) ?></strong>
                                    <span>Active jobs</span>
                                </div>
                                <div class="profile-box">
                                    <strong><?= h((string) count(array_filter($visibleJobs, static fn(array $job): bool => !empty($job['recruiter_name'])))) ?></strong>
                                    <span>Assigned to recruiters</span>
                                </div>
                            </div>
                        </div>
                        <h3 style="margin-top:0;margin-bottom:16px">Job Posts</h3>
                        <?php jobs_table($visibleJobs, 'admin', $jobManageSearch, 'manage'); ?>
                        <?php if ($selectedManageJob): ?>
                        <div class="manage-edit-stack" id="job-edit-panel">
                            <details class="app-panel" open>
                                <summary>Edit <?= h($selectedManageJob['title'] ?? 'Job') ?></summary>
                                <div class="app-panel-body">
                                    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                                        <span class="tiny muted">The inputs below are loaded for the selected job post.</span>
                                        <a class="btn outline" href="<?= h(app_url('admin', array_filter([
                                            'tab' => 'manage',
                                            'job_q' => $jobManageSearch !== '' ? $jobManageSearch : null,
                                        ], static fn($value): bool => $value !== null))) ?>">Close</a>
                                    </div>
                                    <?= job_edit_form($selectedManageJob, $companies, is_super_admin()) ?>
                                </div>
                            </details>
                        </div>
                        <?php endif; ?>
                        <div class="hidden">
                            <form class="form card-pad" method="post" style="background:#f8fafc;border-radius:20px">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="post_job">
                                <h3>Post Job as Recruiter</h3>
                                <label class="label">Company<select class="select" name="company_id"><?php foreach ($companies as $c): ?><option value="<?= h((string)$c['id']) ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
                                <div class="profile-box"><strong>Assigned to <?= h($user['full_name'] ?? 'this recruiter') ?></strong><p class="tiny muted">Applications for this job will appear only in this recruiter's dashboard. Super admin can see all.</p></div>
                                <label class="label">Job Title<select class="select" required name="title"><?= select_options(job_title_options(), 'Select job title') ?></select></label>
                                <label class="label">Location<select class="select" required name="location"><?= select_options(job_location_options(), 'Select location') ?></select></label>
                                <label class="label">Salary<select class="select" required name="salary"><?= select_options(salary_range_options(), 'Select salary range') ?></select></label>
                                <label class="label">Type<select class="select" name="type"><option>Full-time</option><option>Remote</option><option>Hybrid</option><option>Contract</option></select></label>
                                <label class="label">Description
                                    <div class="editor-wrap" data-editor>
                                        <div class="editor-toolbar" aria-label="Description editor tools">
                                            <button type="button" data-command="formatBlock" data-value="h3" title="Heading">H</button>
                                            <button type="button" data-command="bold" title="Bold">B</button>
                                            <button type="button" data-command="italic" title="Italic">I</button>
                                            <button type="button" data-command="underline" title="Underline">U</button>
                                            <button type="button" data-command="insertUnorderedList" title="Bullet list">•</button>
                                            <button type="button" data-command="insertOrderedList" title="Numbered list">1.</button>
                                            <button type="button" data-command="formatBlock" data-value="p" title="Paragraph">P</button>
                                        </div>
                                        <div class="rich-editor" contenteditable="true" data-placeholder="Describe the role, responsibilities, benefits, and working style."></div>
                                        <textarea class="rich-editor-source" name="description"></textarea>
                                    </div>
                                </label>
                                <input type="hidden" name="requirements" value="">
                                <label class="label">Application Deadline<input class="input" type="date" name="expires_at"></label>
                                <label class="label">Tags<input class="input" name="tags" placeholder="React, API, SQL"></label>
                                <button class="btn">Post Assigned Job</button>
                            </form>
                            <div class="grid">
                                <h3><?= is_super_admin() ? 'All Recruiter Applications' : 'My Assigned Applications' ?></h3>
                                <?php foreach (array_slice($recruiterApplications, 0, 6) as $a): ?>
                                <div class="applicant application-card">
                                    <div class="application-row">
                                        <div>
                                            <strong><?= h($a['applicant_name']) ?></strong><br>
                                            <span class="tiny muted"><?= h($a['job_title']) ?> at <?= h($a['company']) ?><?= !empty($a['recruiter_name']) ? ' · Recruiter: ' . h($a['recruiter_name']) : '' ?></span>
                                            <span class="status-pill <?= h(status_class($a['status'])) ?>"><?= h($a['status']) ?></span>
                                        </div>
                                        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="application_status">
                                            <input type="hidden" name="redirect_tab" value="manage">
                                            <input type="hidden" name="application_id" value="<?= h((string)$a['id']) ?>">
                                            <button name="status" value="Shortlisted" class="btn outline">Shortlist</button>
                                            <button name="status" value="Accepted" class="btn green">Accept</button>
                                            <button name="status" value="Rejected" class="btn red">Reject</button>
                                        </form>
                                    </div>
                                    <?= progress_html($a['status']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="grid grid3 hidden">
                            <?php foreach ([['Manage Companies',$stats['companies']],['Manage Job Posts',$stats['openJobs']]] as [$x,$v]): ?>
                            <div class="profile-box"><div style="font-size:22px">🛡️</div><strong><?= h($x) ?></strong><p class="tiny muted"><?= h($v) ?> records</p></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid hidden">
                            <?php foreach ($users as $managedUser): ?>
                            <div class="applicant application-card">
                                <div>
                                    <strong><?= h($managedUser['full_name'] ?: ($managedUser['company_name'] ?: $managedUser['email'])) ?></strong><br>
                                    <span class="tiny muted"><?= h($managedUser['role']) ?> · <?= h($managedUser['email']) ?> · <?= h($managedUser['status']) ?></span>
                                </div>
                                <?php if ($managedUser['role'] !== 'superadmin' && (!in_array($managedUser['role'], ['admin'], true) || is_super_admin())): ?>
                                <form method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?= h((string) $managedUser['id']) ?>">
                                    <button class="btn outline" name="status" value="<?= $managedUser['status'] === 'blocked' ? 'active' : 'blocked' ?>">
                                        <?= $managedUser['status'] === 'blocked' ? 'Unblock' : 'Block' ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <h3 class="hidden" style="margin-top:28px;margin-bottom:16px">Job Posts</h3>
                        <div class="grid hidden">
                            <?php foreach ($recruiterJobs as $job): ?>
                            <div class="applicant application-card">
                                <div><strong><?= h($job['title']) ?></strong><br><span class="tiny muted"><?= h($job['company']) ?> · <?= h($job['location']) ?></span></div>
                                <form method="post" data-confirm="Delete this job?">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete_job">
                                    <input type="hidden" name="job_id" value="<?= h((string) $job['id']) ?>">
                                    <button class="btn red">Delete</button>
                                </form>
                                <div class="application-panels" style="margin-top:16px">
                                    <details class="app-panel">
                                        <summary>Edit Job Post</summary>
                                        <div class="app-panel-body"><?= job_edit_form($job, $companies, is_super_admin()) ?></div>
                                    </details>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'admins' && is_super_admin()): ?>
                        <div class="auth-grid" style="align-items:start;grid-template-columns:minmax(0,1.45fr) minmax(280px,.8fr);gap:28px">
                            <form class="form card-pad" method="post" style="background:#f8fafc;border-radius:20px">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create_admin">
                                <h3>Create Recruiter/Admin</h3>
                                <label class="label">Full Name<input class="input" required name="full_name" placeholder="Recruiter name"></label>
                                <label class="label">Email<input class="input" required type="email" name="email" placeholder="recruiter@example.com"></label>
                                <label class="label">Phone<input class="input" name="phone" placeholder="+964 ..."></label>
                                <label class="label">Password<input class="input" required type="password" name="password" placeholder="Temporary password"></label>
                                <button class="btn">Create Admin</button>
                            </form>
                            <div class="grid">
                                <h3>Admin Accounts</h3>
                                <?php foreach (array_filter($users, static fn(array $u): bool => in_array($u['role'], ['admin', 'superadmin'], true)) as $adminUser): ?>
                                <div class="applicant">
                                    <div>
                                        <strong><?= h($adminUser['full_name'] ?: $adminUser['email']) ?></strong><br>
                                        <span class="tiny muted"><?= h($adminUser['role']) ?> · <?= h($adminUser['email']) ?> · <?= h($adminUser['status']) ?></span>
                                    </div>
                                    <?php if ($adminUser['role'] === 'admin'): ?>
                                    <form method="post" data-confirm="Remove this admin account?">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="user_id" value="<?= h((string) $adminUser['id']) ?>">
                                        <button class="btn red">Remove Admin</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="badge">Owner</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($tab === 'statistics'): ?>
                        <div class="grid grid3">
                            <div class="profile-box"><strong>Total Users</strong><p class="stat-value"><?= h($stats['users']) ?></p></div>
                            <div class="profile-box"><strong>Companies</strong><p class="stat-value"><?= h($stats['companies']) ?></p></div>
                            <div class="profile-box"><strong>Applications</strong><p class="stat-value"><?= h($stats['applications']) ?></p></div>
                            <div class="profile-box"><strong>Open Jobs</strong><p class="stat-value"><?= h($stats['openJobs']) ?></p></div>
                            <div class="profile-box"><strong>Accepted</strong><p class="stat-value"><?= h((string) count(array_filter($applicants, fn($a) => $a['status'] === 'Accepted'))) ?></p></div>
                            <div class="profile-box"><strong>Rejected</strong><p class="stat-value"><?= h((string) count(array_filter($applicants, fn($a) => $a['status'] === 'Rejected'))) ?></p></div>
                        </div>
                        <div class="grid grid3" style="margin-top:24px">
                            <div class="profile-box">
                                <h3>Application Status</h3>
                                <?php $maxStatus = max(1, ...array_map(static fn($row): int => (int) $row['total'], $analytics['statuses'] ?? [])); ?>
                                <?php foreach (($analytics['statuses'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><?= h($row['status']) ?> - <?= h((string) $row['total']) ?></p>
                                    <div class="analytics-bar"><span style="width:<?= h((string) min(100, ((int) $row['total'] / $maxStatus) * 100)) ?>%"></span></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="profile-box">
                                <h3>Top Locations</h3>
                                <?php $maxLocation = max(1, ...array_map(static fn($row): int => (int) $row['total'], $analytics['locations'] ?? [])); ?>
                                <?php foreach (($analytics['locations'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><?= h($row['location']) ?> - <?= h((string) $row['total']) ?></p>
                                    <div class="analytics-bar"><span style="width:<?= h((string) min(100, ((int) $row['total'] / $maxLocation) * 100)) ?>%"></span></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="profile-box">
                                <h3>Job Deadlines</h3>
                                <?php if (empty($analytics['expiring'])): ?><p class="tiny muted">No deadlines set yet.</p><?php endif; ?>
                                <?php foreach (($analytics['expiring'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><strong><?= h($row['title']) ?></strong><br>Expires <?= h(date('M j, Y', strtotime((string) $row['expires_at']))) ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="grid grid3" style="margin-top:24px">
                            <div class="profile-box">
                                <h3>Jobs by Company</h3>
                                <?php $maxCompanyJobs = max(1, ...array_map(static fn($row): int => (int) $row['total'], $analytics['jobs_by_company'] ?? [])); ?>
                                <?php if (empty($analytics['jobs_by_company'])): ?><p class="tiny muted">No company job data yet.</p><?php endif; ?>
                                <?php foreach (($analytics['jobs_by_company'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><?= h($row['company']) ?> - <?= h((string) $row['total']) ?></p>
                                    <div class="analytics-bar"><span style="width:<?= h((string) min(100, ((int) $row['total'] / $maxCompanyJobs) * 100)) ?>%"></span></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="profile-box">
                                <h3>Top Skills</h3>
                                <?php $maxSkills = max(1, ...array_map(static fn($row): int => (int) $row['total'], $analytics['top_skills'] ?? [])); ?>
                                <?php if (empty($analytics['top_skills'])): ?><p class="tiny muted">No CV skill signals yet.</p><?php endif; ?>
                                <?php foreach (($analytics['top_skills'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><?= h($row['skill']) ?> - <?= h((string) $row['total']) ?></p>
                                    <div class="analytics-bar"><span style="width:<?= h((string) min(100, ((int) $row['total'] / $maxSkills) * 100)) ?>%"></span></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="profile-box">
                                <h3>AI Match Distribution</h3>
                                <?php $maxMatchBucket = max(1, ...array_map(static fn($row): int => (int) $row['total'], $analytics['ai_match_distribution'] ?? [])); ?>
                                <?php foreach (($analytics['ai_match_distribution'] ?? []) as $row): ?>
                                    <p class="tiny muted" style="margin:14px 0 0"><?= h($row['fit']) ?> fit - <?= h((string) $row['total']) ?></p>
                                    <div class="analytics-bar"><span style="width:<?= h((string) min(100, ((int) $row['total'] / $maxMatchBucket) * 100)) ?>%"></span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($tab === 'blog' && is_admin_role()): ?>
                        <div class="auth-grid" style="align-items:start">
                            <form class="form card-pad" method="post" style="background:#f8fafc;border-radius:20px">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="create_blog_post">
                                <h3>Publish Blog Post</h3>
                                <label class="label">Title<input class="input" required name="title" placeholder="Example: How to prepare for your first interview"></label>
                                <label class="label">Category<input class="input" name="category" value="Career Advice" placeholder="Career Advice"></label>
                                <label class="label">Excerpt<textarea class="textarea" name="excerpt" rows="3" placeholder="Short summary shown on the blog cards"></textarea></label>
                                <label class="label">Content
                                    <div class="editor-wrap quill-editor-wrap" data-quill-editor>
                                        <div class="quill-editor" data-placeholder="Write the full blog article with headings, lists, and clear sections."></div>
                                        <textarea class="rich-editor-source" name="content"></textarea>
                                    </div>
                                </label>
                                <label class="label">Status<select class="select" name="status"><option value="published">Published</option><option value="draft">Draft</option></select></label>
                                <button class="btn">Save Blog Post</button>
                            </form>
                            <div class="grid">
                                <h3>Recent Blog Posts</h3>
                                <?php if (!$blogPosts): ?><div class="profile-box"><strong>No posts yet.</strong><p class="tiny muted">Create the first article for the public blog.</p></div><?php endif; ?>
                                <?php foreach (array_slice($blogPosts, 0, 6) as $post): ?>
                                    <div class="applicant">
                                        <div>
                                            <strong><?= h($post['title']) ?></strong><br>
                                            <span class="tiny muted"><?= h($post['category'] ?: 'Career Advice') ?> - <?= h($post['status']) ?> - <?= h(date('M j, Y', strtotime((string) $post['created_at']))) ?></span>
                                        </div>
                                        <?php if (($post['status'] ?? '') === 'published'): ?>
                                            <a class="btn outline" href="<?= h(app_url('blog', ['post' => $post['id']])) ?>">View</a>
                                        <?php else: ?>
                                            <span class="badge">Draft</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($tab === 'inbox' && is_admin_role()): ?>
                        <div class="grid">
                            <?php if (!$contactMessages): ?>
                                <div class="profile-box"><strong>No contact messages yet.</strong><p class="tiny muted">When people send questions from the Contact Us page, they will appear here.</p></div>
                            <?php endif; ?>
                            <?php foreach ($contactMessages as $contactMessage): ?>
                                <div class="card card-pad">
                                    <div style="display:flex;justify-content:space-between;gap:14px;align-items:start">
                                        <div>
                                            <strong><?= h($contactMessage['subject']) ?></strong><br>
                                            <span class="tiny muted"><?= h($contactMessage['full_name']) ?> · <?= h($contactMessage['email']) ?></span>
                                        </div>
                                        <span class="badge"><?= h(date('M j, Y', strtotime((string) $contactMessage['created_at']))) ?></span>
                                    </div>
                                    <p class="muted" style="margin:14px 0 0;line-height:1.8"><?= nl2br(h($contactMessage['message'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($tab === 'settings'): ?>
                        <div class="auth-grid" style="align-items:start">
                            <form class="form" method="post" enctype="multipart/form-data">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_profile">
                                <?php if ($isCompany): ?>
                                    <label class="label">Company Name<input class="input" name="company_name" value="<?= h($user['company_name'] ?? '') ?>"></label>
                                    <label class="label">Industry<input class="input" name="industry" value="<?= h($user['industry'] ?? '') ?>"></label>
                                    <label class="label">Location<input class="input" name="location" value="<?= h($user['location'] ?? '') ?>"></label>
                                <?php else: ?>
                                    <label class="label">Full Name<input class="input" name="full_name" value="<?= h($user['full_name'] ?? '') ?>"></label>
                                    <label class="label">Location<input class="input" name="location" value="<?= h($user['location'] ?? '') ?>" placeholder="Erbil, Baghdad, Remote..."></label>
                                    <div class="label">Skills
                                        <?= skills_checkboxes($chosenSkills) ?>
                                    </div>
                                    <label class="label">Upload CV
                                        <?= cv_upload_field('cv_file', false, 'PDF only. Maximum size: 2 MB.') ?>
                                        <span class="tiny muted">Current: <?= !empty($user['cv_file']) ? cv_link_html($user['cv_file'], uploaded_file_label($user['cv_file'])) : 'No CV uploaded' ?></span>
                                    </label>
                                <?php endif; ?>
                                <label class="label">Phone<input class="input" name="phone" value="<?= h($user['phone'] ?? '') ?>"></label>
                                <?php if ($isUser): ?>
                                    <div class="profile-box">
                                        <strong>Profile Score: <?= h((string) $profileScore) ?>%</strong>
                                        <div class="score-bar"><span style="width:<?= h((string) $profileScore) ?>%"></span></div>
                                        <p class="tiny muted">Score improves when name, email, phone, skills, location, and CV are completed.</p>
                                    </div>
                                <?php endif; ?>
                                <button class="btn">Save Profile</button>
                            </form>
                            <form class="form" method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_password">
                                <label class="label">New Password<input class="input" required type="password" name="password" placeholder="New password"></label>
                                <button class="btn outline">Update Password</button>
                            </form>
                            <?php if (is_admin_role($user['role'] ?? null)): ?>
                                <?php $currentAiMode = ai_matching_mode(); ?>
                                <form class="form" method="post">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_ai_screening_settings">
                                    <input type="hidden" name="redirect_page" value="<?= h($page) ?>">
                                    <input type="hidden" name="redirect_tab" value="settings">
                                    <h3>AI Screening Settings</h3>
                                    <label class="label">Matching Mode
                                        <select class="select" name="ai_matching_mode" onchange="this.form.submit()">
                                            <option value="balanced" <?= $currentAiMode === 'balanced' ? 'selected' : '' ?>>Balanced</option>
                                            <option value="strict" <?= $currentAiMode === 'strict' ? 'selected' : '' ?>>Strict</option>
                                            <option value="flexible" <?= $currentAiMode === 'flexible' ? 'selected' : '' ?>>Flexible</option>
                                        </select>
                                    </label>
                                    <div class="profile-box">
                                        <strong>How modes work</strong>
                                        <p class="tiny muted">Strict requires clear evidence for core requirements. Balanced weighs direct and related evidence fairly. Flexible gives more credit for transferable skills and growth potential.</p>
                                    </div>
                                    <div class="profile-box">
                                        <strong>OCR Support</strong>
                                        <p class="tiny muted">For scanned CVs, install/configure OCRmyPDF or Tesseract and optionally pdftotext using <code>OCRMYPDF_BIN</code>, <code>TESSERACT_BIN</code>, <code>TESSERACT_LANGS</code>, and <code>PDFTOTEXT_BIN</code> in .env. If OCR is missing, screening will warn when a PDF looks image-based.</p>
                                    </div>
                                    <button class="btn">Save AI Settings</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="muted">Choose a section from the sidebar.</p>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="footer">
    <div class="wrap">
        <div class="footer-top">
            <div>
                <div class="footer-brand">KDXJOBS</div>
                <span class="tiny muted">Recruitment tools for candidates, companies, and admins.</span>
            </div>
            <div class="footer-links">
                <strong>Platform</strong>
                <a href="<?= h(app_url('jobs')) ?>">Browse Jobs</a>
                <a href="<?= h(app_url('companies')) ?>">Hiring Companies</a>
            </div>
            <div class="footer-links">
                <strong>Accounts</strong>
                <a href="<?= h(app_url('register')) ?>">Create Account</a>
                <a href="<?= h(app_url('login')) ?>">Login</a>
                <a href="<?= h(app_url('company')) ?>">Company Access</a>
            </div>
            <div class="footer-links">
                <strong>Company</strong>
                <a href="<?= h(app_url('about')) ?>">About Us</a>
                <a href="<?= h(app_url('faq')) ?>">FAQ</a>
                <a href="<?= h(app_url('policy')) ?>">Privacy Policy</a>
                <a href="<?= h(app_url('terms')) ?>">Terms</a>
                <a href="<?= h(app_url('contact')) ?>">Contact Us</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span class="tiny muted">&copy;2026 KDXJOBS Inc. All rights reserved.</span>
            <div class="social-row" aria-label="Social links">
                <span class="language-pill">EN</span>
                <span class="social-dot">in</span>
                <span class="social-dot">f</span>
                <span class="social-dot">ig</span>
                <span class="social-dot">tk</span>
            </div>
        </div>
    </div>
</footer>
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/41.4.2/classic/ckeditor.js"></script>
<script src="<?= h(asset_url('assets/app.js?v=4')) ?>" defer></script>
</body>
</html>
