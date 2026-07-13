<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    \Illuminate\Support\Facades\DB::statement('alter publication supabase_realtime add table screen_commands');
    echo "Realtime enabled for screen_commands\n";
} catch (\Exception $e) {
    echo "Error screen_commands: " . $e->getMessage() . "\n";
}

try {
    \Illuminate\Support\Facades\DB::statement('alter publication supabase_realtime add table screens');
    echo "Realtime enabled for screens\n";
} catch (\Exception $e) {
    echo "Error screens: " . $e->getMessage() . "\n";
}
