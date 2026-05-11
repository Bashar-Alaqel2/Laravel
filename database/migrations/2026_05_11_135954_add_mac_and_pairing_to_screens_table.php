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
        Schema::table('screens', function (Blueprint $table) {
            $table->string('mac_address', 50)->nullable()->unique()->after('status')->comment('عنوان الماك للجهاز الفيزيائي');
            $table->string('pairing_code', 20)->nullable()->unique()->after('mac_address')->comment('كود الربط الذي سيُعطى للفني لربط الشاشة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['mac_address', 'pairing_code']);
        });
    }
};
