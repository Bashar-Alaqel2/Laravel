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
        Schema::create('screen_commands', function (Blueprint $table) {
            $table->id();
            $table->string('target_screen')->default('all')->comment('all, or specific mac_address');
            $table->string('command')->comment('SYNC_PLAYLIST, SLEEP_SCREEN, WAKE_SCREEN, RESTART_APP, etc');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_commands');
    }
};
