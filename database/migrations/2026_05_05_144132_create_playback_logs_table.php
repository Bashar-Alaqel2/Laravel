<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('playback_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->foreignId('ad_id')->constrained('advertisements', 'ad_id')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens', 'screen_id')->cascadeOnDelete();
            $table->timestamp('played_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('playback_logs');
    }
};