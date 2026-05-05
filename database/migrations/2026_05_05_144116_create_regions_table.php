<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('regions', function (Blueprint $table) {
            $table->id('region_id');
            $table->foreignId('gov_id')->constrained('governorates', 'gov_id')->cascadeOnDelete();
            $table->string('name', 100);
        });
    }
    public function down(): void {
        Schema::dropIfExists('regions');
    }
};