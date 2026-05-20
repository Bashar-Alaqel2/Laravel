<?php

// Vercel serverless environment does not allow writing to storage, so we point them to /tmp
$storagePath = '/tmp/storage/framework';
$folders = ['/views', '/sessions', '/cache'];
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

define('LARAVEL_START', microtime(true));

// Register Composer Autoloader
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel Application
/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Capture the Request
$request = Illuminate\Http\Request::capture();

// On Vercel, the base URL is mistakenly identified as '/api' due to routing to api/index.php.
// We override base URL and path to empty string so Laravel correctly routes `/api/...` endpoints.
$request->setBaseUrl('');
$request->setBasePath('');

// Handle and send response
$app->handleRequest($request);
