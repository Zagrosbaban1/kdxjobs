<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class LegacyController extends Controller
{
    public function handle(Request $request): Response
    {
        $legacyRoot = base_path('legacy');
        $legacyEntry = $legacyRoot . DIRECTORY_SEPARATOR . 'index.php';

        return $this->runLegacyScript($request, $legacyEntry, '/index.php');
    }

    public function api(Request $request, string $path = 'index.php'): Response
    {
        $safePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $legacyRoot = realpath(base_path('legacy/api'));
        $legacyEntry = realpath(base_path('legacy/api') . DIRECTORY_SEPARATOR . $safePath);

        if (!$legacyRoot || !$legacyEntry || !str_starts_with($legacyEntry, $legacyRoot . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        return $this->runLegacyScript($request, $legacyEntry, '/api/' . str_replace(DIRECTORY_SEPARATOR, '/', $safePath));
    }

    public function asset(string $path): Response
    {
        $safePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $legacyRoot = realpath(base_path('legacy/assets'));
        $assetPath = realpath(base_path('legacy/assets') . DIRECTORY_SEPARATOR . $safePath);

        if (!$legacyRoot || !$assetPath || !str_starts_with($assetPath, $legacyRoot . DIRECTORY_SEPARATOR) || !is_file($assetPath)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
        $contentTypes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
        ];

        return response()->file($assetPath, [
            'Content-Type' => $contentTypes[$extension] ?? 'application/octet-stream',
        ]);
    }

    private function runLegacyScript(Request $request, string $legacyEntry, string $scriptName): Response
    {
        if (!is_file($legacyEntry)) {
            abort(500, 'Legacy application entry file is missing.');
        }

        $basePath = rtrim($request->getBasePath(), '/');
        $nativeFiles = $_FILES;

        // Feed the old app the same inputs it expects while letting Laravel host it.
        $_GET = $request->query->all();
        $_POST = $request->request->all();
        $normalizedFiles = $this->normalizeFiles($request->files->all());
        $_FILES = array_replace($nativeFiles, $normalizedFiles);
        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER['SCRIPT_NAME'] = $basePath . $scriptName;
        $_SERVER['PHP_SELF'] = $basePath . $scriptName;
        $_SERVER['REQUEST_URI'] = $request->getRequestUri();
        $_SERVER['QUERY_STRING'] = $request->getQueryString() ?? '';
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['REMOTE_ADDR'] = $request->ip() ?? '127.0.0.1';
        $_SERVER['HTTP_HOST'] = $request->getHost();
        $_SERVER['HTTPS'] = $request->isSecure() ? 'on' : '';

        if ($request->headers->has('origin')) {
            $_SERVER['HTTP_ORIGIN'] = (string) $request->headers->get('origin');
        }

        putenv('RECRU_DB_HOST=' . (string) env('RECRU_DB_HOST', '127.0.0.1'));
        putenv('RECRU_DB_NAME=' . (string) env('RECRU_DB_NAME', env('DB_DATABASE', 'recru_laravel')));
        putenv('RECRU_DB_USER=' . (string) env('RECRU_DB_USER', 'root'));
        putenv('RECRU_DB_PASS=' . (string) env('RECRU_DB_PASS', ''));

        defined('APP_BASE_PATH_OVERRIDE') || define('APP_BASE_PATH_OVERRIDE', $basePath);
        defined('UPLOAD_PUBLIC_ROOT') || define('UPLOAD_PUBLIC_ROOT', public_path('uploads'));
        defined('LEGACY_STORAGE_ROOT') || define('LEGACY_STORAGE_ROOT', storage_path('legacy'));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionPath = storage_path('framework/sessions');
            if (!is_dir($sessionPath)) {
                mkdir($sessionPath, 0775, true);
            }
            ini_set('session.save_path', $sessionPath);
        }

        ob_start();
        require $legacyEntry;
        $content = ob_get_clean();

        return response($content);
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $normalized[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getClientMimeType(),
                    'tmp_name' => $file->getRealPath(),
                    'error' => $file->getError(),
                    'size' => $file->getSize(),
                ];
                continue;
            }

            if (is_array($file)) {
                $normalized[$key] = $this->normalizeFiles($file);
            }
        }

        return $normalized;
    }
}
