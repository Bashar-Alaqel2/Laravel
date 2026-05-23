<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::table('screen_commands')->insert([
    'target_screen' => 'SB-Y9N7K7',
    'command' => 'SYNC_PLAYLIST',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "Sent SYNC command to SB-Y9N7K7!\n";
