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
        Schema::create('duration_discounts', function (Blueprint $table) {
            $table->id('duration_discount_id');
            $table->string('name')->nullable()->comment('اسم الخصم');
            $table->integer('min_days')->comment('الحد الأدنى للأيام');
            $table->decimal('discount_percentage', 5, 2)->comment('نسبة الخصم المئوية');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duration_discounts');
    }
};
