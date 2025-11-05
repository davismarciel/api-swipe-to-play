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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->integer('level')->default(1);
            $table->integer('experience_points')->default(0);
            $table->integer('total_likes')->default(0);
            $table->integer('total_dislikes')->default(0);
            $table->integer('total_favorites')->default(0);
            $table->integer('total_views')->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

