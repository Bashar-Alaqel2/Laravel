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
        Schema::table('governorates', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('name');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('gov_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::table('streets', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('region_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('screen_name');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        
        Schema::table('streets', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
        
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
