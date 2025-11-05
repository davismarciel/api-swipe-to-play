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
        Schema::create('user_behavior_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            
            // Estatísticas agregadas
            $table->unsignedInteger('total_interactions')->default(0);
            $table->json('liked_genres_stats')->nullable(); // {genre_id: {count, weighted_score, avg_temporal_weight}}
            $table->json('disliked_genres_stats')->nullable(); // {genre_id: {count, rejection_rate}}
            $table->json('liked_categories_stats')->nullable(); // {category_id: {count, weighted_score, avg_temporal_weight}}
            $table->json('disliked_categories_stats')->nullable(); // {category_id: {count, rejection_rate}}
            $table->json('top_developers')->nullable(); // {developer_id: interaction_count}
            $table->json('top_publishers')->nullable(); // {publisher_id: interaction_count}
            
            // Preferências
            $table->decimal('free_to_play_preference', 3, 2)->default(0.00); // -1.0 a 1.0
            $table->decimal('mature_content_tolerance', 3, 2)->default(0.50); // 0.0 a 1.0
            
            // Tolerâncias comunitárias
            $table->decimal('toxicity_tolerance', 3, 2)->default(0.50);
            $table->decimal('cheater_tolerance', 3, 2)->default(0.50);
            $table->decimal('bug_tolerance', 3, 2)->default(0.50);
            $table->decimal('microtransaction_tolerance', 3, 2)->default(0.50);
            $table->decimal('optimization_tolerance', 3, 2)->default(0.50);
            $table->decimal('not_recommended_tolerance', 3, 2)->default(0.50);
            
            // Pesos adaptativos
            $table->json('adaptive_weights')->nullable(); // Pesos personalizados por componente
            
            // Controle de cache
            $table->unsignedInteger('interactions_since_update')->default(0);
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('last_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_behavior_profiles');
    }
};
