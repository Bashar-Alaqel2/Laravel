<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
$tableNames = array_map(function($t) { return $t->table_name; }, $tables);

$mermaid = "erDiagram\n";

foreach ($tableNames as $table) {
    if ($table == 'migrations' || $table == 'jobs' || $table == 'cache' || $table == 'cache_locks' || $table == 'personal_access_tokens' || $table == 'sessions' || $table == 'password_reset_tokens' || $table == 'failed_jobs') continue;

    $mermaid .= "    $table {\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? AND table_schema='public'", [$table]);
    foreach ($columns as $column) {
        $type = preg_replace('/[^a-zA-Z0-9_]/', '', $column->data_type);
        $colName = $column->column_name;
        $mermaid .= "        $type $colName\n";
    }
    $mermaid .= "    }\n";
}

$foreignKeys = DB::select("
    SELECT
        tc.table_name, 
        kcu.column_name, 
        ccu.table_name AS foreign_table_name,
        ccu.column_name AS foreign_column_name 
    FROM 
        information_schema.table_constraints AS tc 
        JOIN information_schema.key_column_usage AS kcu
          ON tc.constraint_name = kcu.constraint_name
          AND tc.table_schema = kcu.table_schema
        JOIN information_schema.constraint_column_usage AS ccu
          ON ccu.constraint_name = tc.constraint_name
          AND ccu.table_schema = tc.table_schema
    WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema='public';
");

foreach ($foreignKeys as $fk) {
    $table = $fk->table_name;
    $foreignTable = $fk->foreign_table_name;
    if (in_array($table, ['migrations', 'personal_access_tokens']) || in_array($foreignTable, ['migrations', 'personal_access_tokens'])) continue;
    $mermaid .= "    $table ||--o{ $foreignTable : \"{$fk->column_name} -> {$fk->foreign_column_name}\"\n";
}

file_put_contents('erd.txt', $mermaid);
echo "ERD generated to erd.txt\n";
