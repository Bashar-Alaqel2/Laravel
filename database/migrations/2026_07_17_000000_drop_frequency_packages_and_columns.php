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
        Schema::dropIfExists('frequency_packages');

        if (Schema::hasColumn('advertisements', 'daily_frequency')) {
            Schema::table('advertisements', function (Blueprint $table) {
                $table->dropColumn('daily_frequency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('frequency_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->integer('display_interval')->comment('تكرار العرض بالدقائق (مثلا: 10 تعني مرة كل 10 دقائق)');
            $table->decimal('price_multiplier', 5, 2)->default(1.00)->comment('مضاعف السعر');
            $table->timestamps();
        });

        if (!Schema::hasColumn('advertisements', 'daily_frequency')) {
            Schema::table('advertisements', function (Blueprint $table) {
                $table->integer('daily_frequency')->default(1)->after('end_date');
            });
        }
    }
};
