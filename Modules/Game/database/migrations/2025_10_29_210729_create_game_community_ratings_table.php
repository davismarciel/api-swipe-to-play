<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_community_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->decimal('toxicity_rate', 5, 3)->default(0);
            $table->decimal('cheater_rate', 5, 3)->default(0);
            $table->decimal('bug_rate', 5, 3)->default(0);
            $table->decimal('microtransaction_rate', 5, 3)->default(0);
            $table->decimal('bad_optimization_rate', 5, 3)->default(0);
            $table->decimal('not_recommended_rate', 5, 3)->default(0);
            $table->timestamps();

            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_community_ratings');
    }
};
