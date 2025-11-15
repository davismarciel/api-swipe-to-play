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
            
            $table->unsignedInteger('total_interactions')->default(0);
            $table->json('liked_genres_stats')->nullable();
            $table->json('disliked_genres_stats')->nullable();
            $table->json('liked_categories_stats')->nullable();
            $table->json('disliked_categories_stats')->nullable();
            $table->json('top_developers')->nullable();
            $table->json('top_publishers')->nullable();

            $table->decimal('free_to_play_preference', 3, 2)->default(0.00);
            $table->decimal('mature_content_tolerance', 3, 2)->default(0.50);

            $table->decimal('toxicity_tolerance', 3, 2)->default(0.50);
            $table->decimal('cheater_tolerance', 3, 2)->default(0.50);
            $table->decimal('bug_tolerance', 3, 2)->default(0.50);
            $table->decimal('microtransaction_tolerance', 3, 2)->default(0.50);
            $table->decimal('optimization_tolerance', 3, 2)->default(0.50);
            $table->decimal('not_recommended_tolerance', 3, 2)->default(0.50);
            
            $table->json('adaptive_weights')->nullable();

            $table->unsignedInteger('interactions_since_update')->default(0);
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            
            $table->timestamps();
            
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
