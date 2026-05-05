<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ad_screens', function (Blueprint $table) {
            $table->id('ad_screen_id');
            $table->foreignId('ad_id')->constrained('advertisements', 'ad_id')->cascadeOnDelete();
            $table->foreignId('screen_id')->constrained('screens', 'screen_id')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
        });
    }
    public function down(): void {
        Schema::dropIfExists('ad_screens');
    }
};