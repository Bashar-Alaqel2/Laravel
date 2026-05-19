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
        Schema::create('frequency_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('display_interval')->comment('تكرار العرض بالدقائق (مثلا: 10 تعني مرة كل 10 دقائق)');
            $table->decimal('price_multiplier', 5, 2)->default(1.00)->comment('مضاعف السعر');
            $table->timestamps();
        });

        // إدخال باقات افتراضية كبداية
        DB::table('frequency_packages')->insert([
            [
                'name' => 'الباقة الاقتصادية',
                'display_interval' => 10,
                'price_multiplier' => 1.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'الباقة الفضية',
                'display_interval' => 5,
                'price_multiplier' => 1.50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'الباقة الذهبية',
                'display_interval' => 2,
                'price_multiplier' => 2.50,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frequency_packages');
    }
};
