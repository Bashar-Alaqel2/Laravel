<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * base_price       = السعر الأساسي اليومي للشاشة ($)
     * screen_size_inch = حجم الشاشة بالإنش (55 / 65 / 75 / 86 / 98)
     *                    يُستخدم كمضاعف في خوارزمية حساب تكلفة الإعلان
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->default(10.00)->after('screen_name');
            $table->unsignedSmallInteger('screen_size_inch')->default(55)->after('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'screen_size_inch']);
        });
    }
};
