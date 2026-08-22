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
        Schema::create('default_contents', function (Blueprint $table) {
            $table->id('content_id');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type'); // 'image', 'video'
            $table->integer('duration')->default(15); // in seconds
            $table->boolean('is_active')->default(false);
            
            // Allow targeting specific screens. If null, applies to all.
            $table->unsignedBigInteger('screen_id')->nullable();
            
            $table->timestamps();

            // Foreign key for screen_id (if your screens table is named 'screens' and primary key is 'screen_id')
            // Using constrained() depends on exact naming. Let's write it explicitly:
            $table->foreign('screen_id')->references('screen_id')->on('screens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_contents');
    }
};
