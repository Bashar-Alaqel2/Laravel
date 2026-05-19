<?php

// Vercel serverless environment does not allow writing to storage, so we point them to /tmp
$storagePath = '/tmp/storage/framework';
$folders = ['/views', '/sessions', '/cache'];
foreach ($folders as $folder) {
    if (!is_dir($storagePath . $folder)) {
        mkdir($storagePath . $folder, 0755, true);
    }
}

// Modify Laravel compilation path to write into /tmp
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('LOG_CHANNEL=stderr'); // Send logs directly to Vercel logs dashboard

// Forward Vercel request to Laravel public/index.php
require __DIR__ . '/../public/index.php';
