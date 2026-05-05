<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id('wallet_tx_id');
            $table->foreignId('wallet_id')->constrained('wallets', 'wallet_id')->cascadeOnDelete();
            $table->string('transaction_type', 50);
            $table->decimal('amount', 10, 2);
            $table->integer('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('wallet_transactions');
    }
};