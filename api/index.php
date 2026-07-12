<?php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, Accept");
    http_response_code(200);
    exit();
}

// Override script name and php self to prevent Laravel from incorrectly extracting '/api' as the base URL.
// This ensures that Laravel routes starting with '/api/...' match correctly.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Vercel serverless environment does not allow writing to storage, so we point them to /tmp
$storagePath = '/tmp/storage';
$folders = [
    '/app',
    '/framework/cache/data',
    '/framework/sessions',
    '/framework/views',
    '/logs'
];
foreach ($folders as $folder) {
    if (!is_dir($storagePath . $folder)) {
        mkdir($storagePath . $folder, 0755, true);
    }
}

// Modify Laravel compilation and cache paths to write into /tmp
$caches = [
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views'
];
foreach ($caches as $key => $val) {
    putenv("$key=$val");
    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
}

putenv('LOG_CHANNEL=stderr'); // Send logs directly to Vercel logs dashboard

// Force Array Cache and S3 disk for Vercel Serverless
$forceEnvs = [
    'CACHE_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'FILESYSTEM_DISK' => 's3',
    'BROADCAST_CONNECTION' => 'log',
];
foreach ($forceEnvs as $key => $val) {
    putenv("$key=$val");
    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
}

define('LARAVEL_START', microtime(true));

// Register Composer Autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel Application
/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->useStoragePath($storagePath);

// Force removal of cached config so our injected env variables actually take effect
if (file_exists(__DIR__ . '/../bootstrap/cache/config.php')) {
    unlink(__DIR__ . '/../bootstrap/cache/config.php');
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Capture the Request
$request = Illuminate\Http\Request::capture();

// Handle and send response
$response = $kernel->handle($request);
$response->send();

$kernel->terminate($request, $response);
