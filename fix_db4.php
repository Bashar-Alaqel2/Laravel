<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ads = App\Models\Advertisement::all();
$fixed = 0;
foreach ($ads as $ad) {
    if (strpos($ad->file_path, '.storage.supabase.co/storage/v1/s3/') !== false) {
        $ad->file_path = str_replace(
            'https://uyvykohckfygsbxbzrpp.storage.supabase.co/storage/v1/s3/ads/',
            'https://uyvykohckfygsbxbzrpp.supabase.co/storage/v1/object/public/ads/ads/',
            $ad->file_path
        );
        $ad->save();
        $fixed++;
    }
}
echo "Fixed $fixed ads to use public REST URL!\n";
