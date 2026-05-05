<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screen_types', function (Blueprint $table) {
            $table->id('type_id');
            $table->string('type_name', 100);
            $table->integer('resolution_width')->default(1920);
            $table->integer('resolution_height')->default(1080);
            $table->string('orientation', 50)->default('Landscape');
        });
    }
    public function down(): void {
        Schema::dropIfExists('screen_types');
    }
};