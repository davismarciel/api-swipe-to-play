<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('media_id');
            $table->string('name');
            $table->string('thumbnail');
            $table->json('webm')->nullable();
            $table->json('mp4')->nullable();
            $table->string('dash_av1')->nullable();
            $table->string('dash_h264')->nullable();
            $table->string('hls_h264')->nullable();
            $table->boolean('highlight')->default(false);
            $table->timestamps();

            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_media');
    }
};
