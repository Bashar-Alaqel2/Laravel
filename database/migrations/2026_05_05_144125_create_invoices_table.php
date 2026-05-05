<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id('invoice_id');
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('advertiser_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('total_platform_fee', 10, 2);
            $table->decimal('total_owner_share', 10, 2);
            $table->string('status', 50)->default('Unpaid');
            $table->timestamp('issue_date')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('invoices');
    }
};