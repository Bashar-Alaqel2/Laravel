<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');
            $table->foreignId('role_id')->nullable()->constrained('roles', 'role_id')->nullOnDelete();
            $table->string('full_name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->unique(); // إضافة حقل الجوال
            $table->string('location', 255)->nullable(); // إضافة حقل الموقع (كنص أو إحداثيات)
            $table->string('password_hash', 255);
            $table->string('account_status', 50)->default('Active');
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes('deleted_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('users');
    }
};