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
        Schema::create('advertisement_screen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('advertisements', 'ad_id')->onDelete('cascade');
            $table->foreignId('screen_id')->constrained('screens', 'screen_id')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisement_screen');
    }
};
