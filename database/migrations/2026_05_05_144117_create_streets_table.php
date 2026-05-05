<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('streets', function (Blueprint $table) {
            $table->id('street_id');
            $table->foreignId('region_id')->constrained('regions', 'region_id')->cascadeOnDelete();
            $table->string('name', 100);
        });
    }
    public function down(): void {
        Schema::dropIfExists('streets');
    }
};