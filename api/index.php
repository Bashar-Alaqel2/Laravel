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

if ($_SERVER['REQUEST_URI'] === '/api/test2') {
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Hit api/index.php directly!', 'uri' => $_SERVER['REQUEST_URI']]);
    exit;
}

if (str_starts_with($_SERVER['REQUEST_URI'], '/api/debug2')) {
    header('Content-Type: application/json');
    echo json_encode([
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
    ]);
    exit;
}

if ($_SERVER['REQUEST_URI'] === '/api/debug') {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $request = Illuminate\Http\Request::capture();
    header('Content-Type: application/json');
    echo json_encode([
        'path' => $request->path(),
        'decodedPath' => $request->decodedPath(),
        'url' => $request->url(),
        'fullUrl' => $request->fullUrl(),
        'baseUrl' => $request->getBaseUrl(),
        'basePath' => $request->getBasePath(),
        'pathInfo' => $request->getPathInfo(),
    ]);
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

putenv('LOG_CHANNEL=stderr'); // Send logs directly to Vercel logs dashboard

// Forward Vercel request to Laravel public/index.php
require __DIR__ . '/../public/index.php';
