<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->id('category_id');
            $table->string('category_name', 100);
            $table->decimal('price', 10, 2);
            $table->integer('max_duration');
            $table->integer('max_size');
            $table->string('discount_type', 50)->nullable();
            $table->decimal('discount_value', 10, 2)->default(0.00);
        });
    }
    public function down(): void {
        Schema::dropIfExists('categories');
    }
};