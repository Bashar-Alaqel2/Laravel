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

putenv('LOG_CHANNEL=stderr'); // Send logs directly to Vercel logs dashboard

// Forward Vercel request to Laravel public/index.php
require __DIR__ . '/../public/index.php';
