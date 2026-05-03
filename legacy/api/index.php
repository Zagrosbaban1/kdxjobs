<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
apply_security_headers(true);

function is_local_request(): bool
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($remote, ['127.0.0.1', '::1'], true);
}

function require_local_request(): void
{
    if (!is_local_request()) {
        json_response(['ok' => false, 'error' => 'This API action is disabled for public access.'], 403);
    }
}

$origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($origin !== '') {
    $originHost = (string) parse_url($origin, PHP_URL_HOST);
    if ($originHost !== '' && ($originHost === $host || in_array($originHost, ['localhost', '127.0.0.1'], true))) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
}

header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $action = $_GET['action'] ?? 'bootstrap';

    match ($action) {
        'bootstrap' => bootstrap(),
        'register' => register_user(),
        'login' => login_user(),
        'apply' => apply_for_job(),
        'create-job' => create_job(),
        'update-application' => update_application(),
        default => json_response(['ok' => false, 'error' => 'Unknown API action.'], 404),
    };
} catch (PDOException $exception) {
    error_log('Legacy API database error: ' . $exception->getMessage());
    json_response(['ok' => false, 'error' => 'Database error. Please try again later.'], 500);
} catch (RuntimeException $exception) {
    json_response(['ok' => false, 'error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('Legacy API error: ' . $exception->getMessage());
    json_response(['ok' => false, 'error' => 'Server error. Please try again later.'], 500);
}

function validate_api_password_strength(string $password): void
{
    if (strlen($password) < 10) {
        throw new RuntimeException('Password must be at least 10 characters.');
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        throw new RuntimeException('Password must include uppercase, lowercase, and number characters.');
    }
}

function bootstrap(): never
{
    $pdo = db();

    $jobs = $pdo->query(
        "SELECT j.*, c.name AS company, c.industry,
                GROUP_CONCAT(t.tag ORDER BY t.tag SEPARATOR ',') AS tags
         FROM jobs j
         JOIN companies c ON c.id = j.company_id
         LEFT JOIN job_tags t ON t.job_id = j.id
         GROUP BY j.id
         ORDER BY j.created_at DESC"
    )->fetchAll();

    foreach ($jobs as &$job) {
        $job['tags'] = $job['tags'] ? explode(',', $job['tags']) : [];
    }

    $companies = $pdo->query(
        "SELECT c.*, COUNT(j.id) AS jobs
         FROM companies c
         LEFT JOIN jobs j ON j.company_id = c.id AND j.status = 'active'
         GROUP BY c.id
         ORDER BY c.name"
    )->fetchAll();

    $stats = [
        'openJobs' => (int) $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'active'")->fetchColumn(),
        'companies' => (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn(),
        'jobSeekers' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'jobseeker'")->fetchColumn(),
        'applications' => (int) $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    ];

    json_response([
        'ok' => true,
        'jobs' => $jobs,
        'companies' => $companies,
        'stats' => $stats,
    ]);
}

function register_user(): never
{
    enforce_rate_limit('api_register', 5, 900, true);
    $data = array_merge(input(), $_POST);
    require_fields($data, ['role', 'email', 'password']);
    $email = require_valid_email_address((string) $data['email']);
    validate_api_password_strength((string) $data['password']);

    $role = in_array($data['role'], ['jobseeker', 'company'], true) ? $data['role'] : 'jobseeker';
    if ($role === 'jobseeker') {
        require_fields($data, ['full_name']);
    } else {
        require_fields($data, ['company_name', 'industry', 'location']);
    }

    if ($role === 'jobseeker' && (!isset($_FILES['cv_file']) || (int) ($_FILES['cv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
        throw new RuntimeException('Please upload your CV as a PDF file.');
    }
    $cvFile = upload_file('cv_file', ['pdf'], MAX_CV_UPLOAD_BYTES);
    $logoFile = upload_file('logo_file', ['png', 'jpg', 'jpeg', 'webp']);
    $pdo = db();

    $emailExists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $emailExists->execute([':email' => $email]);
    if ($emailExists->fetchColumn()) {
        throw new RuntimeException('This email is already registered. Please login instead, or use a different email.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (role, full_name, company_name, email, phone, password_hash, skills, industry, location, cv_file, logo_file)
         VALUES (:role, :full_name, :company_name, :email, :phone, :password_hash, :skills, :industry, :location, :cv_file, :logo_file)'
    );

    $stmt->execute([
        ':role' => $role,
        ':full_name' => $data['full_name'] ?? null,
        ':company_name' => $data['company_name'] ?? null,
        ':email' => $email,
        ':phone' => $data['phone'] ?? null,
        ':password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ':skills' => is_array($data['skills'] ?? null) ? implode(', ', $data['skills']) : ($data['skills'] ?? null),
        ':industry' => $data['industry'] ?? null,
        ':location' => $data['location'] ?? null,
        ':cv_file' => $cvFile,
        ':logo_file' => $logoFile,
    ]);

    $userId = (int) $pdo->lastInsertId();

    if ($role === 'company') {
        $companyStmt = $pdo->prepare(
            'INSERT INTO companies (user_id, name, industry, location, logo_file, description)
             VALUES (:user_id, :name, :industry, :location, :logo_file, :description)'
        );
        $companyStmt->execute([
            ':user_id' => $userId,
            ':name' => $data['company_name'],
            ':industry' => $data['industry'],
            ':location' => $data['location'],
            ':logo_file' => $logoFile,
            ':description' => $data['description'] ?? null,
        ]);
    }

    json_response(['ok' => true, 'message' => 'Account created successfully.', 'userId' => $userId], 201);
}

function login_user(): never
{
    enforce_rate_limit('api_login', 6, 300, true);
    $data = input();
    require_fields($data, ['email', 'password']);
    $email = require_valid_email_address((string) $data['email']);

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND status = "active" LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify((string) $data['password'], $user['password_hash'])) {
        json_response(['ok' => false, 'error' => 'Invalid email or password.'], 401);
    }

    unset($user['password_hash']);
    json_response(['ok' => true, 'message' => 'Login successful.', 'user' => $user]);
}

function apply_for_job(): never
{
    require_local_request();
    enforce_rate_limit('api_apply', 8, 600, true);
    $data = input();
    require_fields($data, ['job_id', 'applicant_name', 'applicant_email', 'role']);
    $applicantEmail = require_valid_email_address((string) $data['applicant_email'], 'Please enter a valid applicant email.');
    $cvFile = upload_file('application_cv', ['pdf'], MAX_CV_UPLOAD_BYTES);

    $stmt = db()->prepare(
        'INSERT INTO applications (job_id, user_id, applicant_name, applicant_email, applicant_phone, role, cover_note, cv_file)
         VALUES (:job_id, :user_id, :applicant_name, :applicant_email, :applicant_phone, :role, :cover_note, :cv_file)'
    );

    $stmt->execute([
        ':job_id' => (int) $data['job_id'],
        ':user_id' => null,
        ':applicant_name' => $data['applicant_name'],
        ':applicant_email' => $applicantEmail,
        ':applicant_phone' => $data['applicant_phone'] ?? null,
        ':role' => $data['role'],
        ':cover_note' => $data['cover_note'] ?? null,
        ':cv_file' => $cvFile,
    ]);

    json_response(['ok' => true, 'message' => 'Application submitted.'], 201);
}

function create_job(): never
{
    require_local_request();
    enforce_rate_limit('api_create_job', 10, 600, true);
    $data = input();
    require_fields($data, ['company_id', 'title', 'location', 'salary', 'type', 'description']);

    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO jobs (company_id, title, location, salary, type, description, requirements)
         VALUES (:company_id, :title, :location, :salary, :type, :description, :requirements)'
    );
    $stmt->execute([
        ':company_id' => (int) $data['company_id'],
        ':title' => $data['title'],
        ':location' => $data['location'],
        ':salary' => $data['salary'],
        ':type' => $data['type'],
        ':description' => $data['description'],
        ':requirements' => $data['requirements'] ?? null,
    ]);

    $jobId = (int) $pdo->lastInsertId();
    $tags = array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))));
    $tagStmt = $pdo->prepare('INSERT INTO job_tags (job_id, tag) VALUES (:job_id, :tag)');
    foreach ($tags as $tag) {
        $tagStmt->execute([':job_id' => $jobId, ':tag' => $tag]);
    }

    json_response(['ok' => true, 'message' => 'Job posted successfully.', 'jobId' => $jobId], 201);
}

function update_application(): never
{
    require_local_request();
    enforce_rate_limit('api_update_application', 20, 600, true);
    $data = input();
    require_fields($data, ['application_id', 'status']);

    $allowed = ['New', 'Reviewed', 'Shortlisted', 'Accepted', 'Rejected'];
    if (!in_array($data['status'], $allowed, true)) {
        json_response(['ok' => false, 'error' => 'Invalid application status.'], 422);
    }

    $stmt = db()->prepare('UPDATE applications SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $data['status'],
        ':id' => (int) $data['application_id'],
    ]);

    json_response(['ok' => true, 'message' => 'Application status updated.']);
}
