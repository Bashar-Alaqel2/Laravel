<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id('session_id');
            $table->foreignId('user_id')->constrained('users', 'user_id')->cascadeOnDelete();
            $table->string('device_name', 100);
            $table->string('device_id', 100);
            $table->string('ip_address', 45)->nullable();
            $table->string('fcm_token', 255)->nullable();
            $table->timestamp('last_active')->useCurrent();
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'device_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_sessions');
    }
};