<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run for PostgreSQL to fix PDO EMULATE_PREPARES mismatch
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $columns = [
            'notifications' => ['is_read', 0],
            'duration_discounts' => ['is_active', 1],
            'payment_methods' => ['is_active', 1],
            'transactions' => ['is_platform_fee_deducted', 0],
            'ad_schedules' => ['is_active', 1],
            'advertisements' => ['is_deleted', 0],
            'user_sessions' => ['is_revoked', 0],
        ];

        foreach ($columns as $table => $data) {
            $column = $data[0];
            $default = $data[1];

            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE smallint USING {$column}::integer");
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$default}");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smallint_for_postgres_compatibility', function (Blueprint $table) {
            //
        });
    }
};
