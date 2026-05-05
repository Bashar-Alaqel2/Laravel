<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id('setting_id');
            $table->string('setting_key', 100)->unique();
            $table->string('setting_value', 255);
            $table->string('description', 255)->nullable();
        });
    }
    public function down(): void {
        Schema::dropIfExists('system_settings');
    }
};