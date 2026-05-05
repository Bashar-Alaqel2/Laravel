<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->foreignId('invoice_id')->constrained('invoices', 'invoice_id')->cascadeOnDelete();
            $table->foreignId('ad_id')->constrained('advertisements', 'ad_id')->cascadeOnDelete();
            $table->decimal('item_price', 10, 2);
        });
    }
    public function down(): void {
        Schema::dropIfExists('invoice_items');
    }
};