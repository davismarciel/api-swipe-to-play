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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Preferências de plataforma
            $table->boolean('prefer_windows')->default(true);
            $table->boolean('prefer_mac')->default(false);
            $table->boolean('prefer_linux')->default(false);

            // Preferências de idioma
            $table->json('preferred_languages')->nullable(); // ['en', 'pt-BR', 'es']

            // Preferências de gameplay
            $table->boolean('prefer_single_player')->default(true);
            $table->boolean('prefer_multiplayer')->default(true);
            $table->boolean('prefer_coop')->default(true);
            $table->boolean('prefer_competitive')->default(false);

            // Preferências de conteúdo
            $table->integer('min_age_rating')->default(0); // 0 = todos, 12, 16, 18
            $table->boolean('avoid_violence')->default(false);
            $table->boolean('avoid_nudity')->default(false);

            // Preferências de preço
            $table->decimal('max_price', 10, 2)->nullable(); // Preço máximo disposto a pagar
            $table->boolean('prefer_free_to_play')->default(false);
            $table->boolean('include_early_access')->default(true);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};

