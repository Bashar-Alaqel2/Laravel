<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id('gov_id');
            $table->string('name', 100);
        });
    }
    public function down(): void {
        Schema::dropIfExists('governorates');
    }
};