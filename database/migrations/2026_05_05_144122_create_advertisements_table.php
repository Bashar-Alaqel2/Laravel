<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id('ad_id');
            $table->foreignId('advertiser_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories', 'category_id')->nullOnDelete();
            $table->string('title', 150);
            $table->string('file_path', 255);
            $table->integer('duration');
            $table->decimal('file_size', 8, 2);
            $table->string('status', 50)->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->boolean('is_deleted')->default(false);
        });
    }
    public function down(): void {
        Schema::dropIfExists('advertisements');
    }
};