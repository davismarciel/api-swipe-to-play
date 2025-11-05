<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->boolean('windows')->default(false);
            $table->boolean('mac')->default(false);
            $table->boolean('linux')->default(false);
            $table->timestamps();

            $table->unique('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_platforms');
    }
};
