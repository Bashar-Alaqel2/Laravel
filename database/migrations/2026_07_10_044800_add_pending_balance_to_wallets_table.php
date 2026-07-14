<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Table 'wallets' was deleted in a previous migration (restructure_financial_system).
        // This migration is obsolete and safely skipped.
    }

    public function down(): void {
        // Obsolete
    }
};
