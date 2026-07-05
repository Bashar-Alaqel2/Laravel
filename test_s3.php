<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

Config::set('filesystems.disks.s3.key', '0a0c30d8e591773f0db527e6a0515420');
Config::set('filesystems.disks.s3.secret', 'e1a8deff5a6951efad7c28270d493bd9f64c9aac3609646a5cd22984b9da2921');
Config::set('filesystems.disks.s3.region', 'ap-southeast-1');
Config::set('filesystems.disks.s3.bucket', 'ads');
Config::set('filesystems.disks.s3.endpoint', 'https://uyvykohckfygsbxbzrpp.storage.supabase.co/storage/v1/s3');
Config::set('filesystems.disks.s3.use_path_style_endpoint', true);

try {
    Storage::disk('s3')->put('test.txt', 'hello world');
    echo 'Success';
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
