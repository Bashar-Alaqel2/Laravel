<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('title');
            $table->date('end_date')->nullable()->after('start_date');
            $table->integer('daily_frequency')->default(1)->after('duration');
            $table->decimal('total_cost', 10, 2)->default(0.00)->after('daily_frequency');
            $table->string('package_name', 100)->nullable()->after('total_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'daily_frequency', 'total_cost', 'package_name']);
        });
    }
};
