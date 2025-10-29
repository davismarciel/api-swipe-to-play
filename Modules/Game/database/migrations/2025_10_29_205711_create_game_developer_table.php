<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_developer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('developer_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['game_id', 'developer_id']);
            $table->index('game_id');
            $table->index('developer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_developer');
    }
};
