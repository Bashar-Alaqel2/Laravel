<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screens', function (Blueprint $table) {
            $table->id('screen_id');
            $table->foreignId('owner_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('type_id')->nullable()->constrained('screen_types', 'type_id')->nullOnDelete();
            $table->foreignId('street_id')->nullable()->constrained('streets', 'street_id')->nullOnDelete();
            $table->string('screen_name', 100);
            $table->string('status', 50)->default('Offline');
            $table->foreignId('linked_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->softDeletes('deleted_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('screens');
    }
};