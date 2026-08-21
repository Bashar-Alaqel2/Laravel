<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
foreach($tables as $table) {
    echo "\nTable: {$table->table_name}\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?", [$table->table_name]);
    foreach($columns as $col) {
        echo " - {$col->column_name} ({$col->data_type})\n";
    }
}
