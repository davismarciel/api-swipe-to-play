<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_publisher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publisher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'publisher_id']);
            $table->index('game_id');
            $table->index('publisher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_publisher');
    }
};
