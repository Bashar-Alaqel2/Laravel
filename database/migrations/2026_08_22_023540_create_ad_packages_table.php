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
        Schema::create('ad_packages', function (Blueprint $table) {
            $table->id('package_id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->integer('interval_minutes');
            $table->decimal('price_multiplier', 5, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default packages
        DB::table('ad_packages')->insert([
            [
                'name_ar' => 'باقة مستمرة (أساسية)',
                'name_en' => 'Continuous Package (Basic)',
                'interval_minutes' => 1,
                'price_multiplier' => 1.50, // Premium price for continuous
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_ar' => 'باقة قياسية (كل 5 دقائق)',
                'name_en' => 'Standard Package (Every 5 mins)',
                'interval_minutes' => 5,
                'price_multiplier' => 1.00, // Base price
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_ar' => 'باقة اقتصادية (كل 15 دقيقة)',
                'name_en' => 'Economy Package (Every 15 mins)',
                'interval_minutes' => 15,
                'price_multiplier' => 0.75, // Cheaper price
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_packages');
    }
};
