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
        // 1. إنشاء جدول أوقات الذروة والتسعير الديناميكي للشاشات
        if (!Schema::hasTable('screen_pricing_slots')) {
            Schema::create('screen_pricing_slots', function (Blueprint $table) {
                $table->id('slot_id');
                $table->foreignId('screen_id')->constrained('screens', 'screen_id')->cascadeOnDelete();
                $table->time('start_time'); // بداية وقت الذروة (مثلاً 16:00:00)
                $table->time('end_time');   // نهاية وقت الذروة (مثلاً 22:00:00)
                $table->decimal('price_multiplier', 5, 2)->default(1.00); // معامل السعر (1.5 يعني أغلى بمرة ونصف)
                $table->timestamps();
            });
        }

        // 2. تحديث جدول الجدولة لدعم حساب السعة بالثواني
        Schema::table('ad_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_schedules', 'interval_minutes')) {
                $table->integer('interval_minutes')->default(10)->comment('عرض الإعلان كل كم دقيقة');
            }
            if (!Schema::hasColumn('ad_schedules', 'allocated_seconds')) {
                $table->integer('allocated_seconds')->default(0)->comment('مجموع الثواني المحجوزة من هذه الجدولة في الساعة الواحدة');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_schedules', function (Blueprint $table) {
            $table->dropColumn(['interval_minutes', 'allocated_seconds']);
        });

        Schema::dropIfExists('screen_pricing_slots');
    }
};
