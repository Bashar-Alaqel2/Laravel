<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('transaction_id');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices', 'invoice_id')->nullOnDelete();
            $table->string('payment_method', 50);
            $table->decimal('amount_paid', 10, 2);
            $table->string('payment_status', 50)->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->boolean('is_platform_fee_deducted')->default(false);
            $table->timestamp('deduction_scheduled_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('transactions');
    }
};