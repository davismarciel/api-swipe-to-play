<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('steam_id')->unique();
            $table->string('name');
            $table->string('type')->default('game');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->integer('required_age')->default(0);
            $table->boolean('is_free')->default(false);
            $table->boolean('have_dlc')->default(false);
            $table->string('icon')->nullable();
            $table->json('supported_languages')->nullable();
            $table->date('release_date')->nullable();
            $table->boolean('coming_soon')->default(false);
            $table->integer('recommendations')->default(0);
            $table->integer('achievements_count')->default(0);
            $table->integer('positive_reviews')->default(0);
            $table->integer('negative_reviews')->default(0);
            $table->integer('total_reviews')->default(0);
            $table->decimal('positive_ratio', 5, 3)->nullable();
            $table->json('content_descriptors')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('steam_id');
            $table->index('slug');
            $table->index('is_free');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
